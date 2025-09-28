<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\EnterpriseRagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('chat.index', compact('user'));
    }

    public function query(Request $request, EnterpriseRagService $ragService)
    {
        $user = Auth::user();

        $request->validate([
            'query' => 'required|string|max:1000'
        ]);

        $query = $request->input('query');

        // Check token limits first
        $tokensNeeded = $this->calculateTokenCost($user->plan);
        if ($user->tokens_used + $tokensNeeded > $user->tokens_limit) {
            return response()->json([
                'error' => 'Token limit exceeded. Upgrade your plan for more usage.',
                'tokens_remaining' => max(0, $user->tokens_limit - $user->tokens_used),
                'upgrade_url' => route('plans.index')
            ], 429);
        }

        // Find user's most recent REAL document (not fixtures) - prioritize bypass documents
        $latestDoc = DB::table('documents')
            ->where(function($query) use ($user) {
                $query->where('tenant_slug', $user->email)
                      ->orWhere('tenant_slug', $user->slug ?? "user_{$user->id}")
                      ->orWhere('tenant_slug', "user_{$user->id}");
            })
            ->whereNotNull('created_at') // Excluir fixtures sem data
            ->orderByRaw("CASE WHEN source IN ('bypass_upload', 'enterprise_upload') THEN 0 ELSE 1 END") // Prioritize bypass docs
            ->orderBy('created_at', 'desc')
            ->first();

        // Debug logging para investigar seleção
        Log::info('Document Selection Debug', [
            'tenant_search' => [$user->email, $user->slug ?? "user_{$user->id}", "user_{$user->id}"],
            'user_id' => $user->id,
            'selected_doc_id' => $latestDoc->id ?? null,
            'selected_doc_title' => $latestDoc->title ?? 'No document found',
            'selected_doc_tenant' => $latestDoc->tenant_slug ?? null,
            'selected_doc_source' => $latestDoc->source ?? null,
            'selected_doc_created' => $latestDoc->created_at ?? null
        ]);

        if (!$latestDoc) {
            return response()->json([
                'answer' => 'Nenhum documento processado encontrado. Faça upload de um arquivo primeiro.',
                'suggestions' => [
                    'Faça upload de um documento PDF, TXT ou DOCX',
                    'Aguarde o processamento completo',
                    'Verifique se o documento foi processado com sucesso'
                ],
                'tokens_remaining' => $user->tokens_limit - $user->tokens_used
            ]);
        }

        try {
            // Use Enterprise RAG Service
            $params = [
                'user' => $user,
                'query' => $query,
                'document' => $latestDoc
            ];

            // Log the search attempt
            $ragService->logSearchAttempt($params);

            // Perform advanced RAG query
            $result = $ragService->performAdvancedQuery($params);

            // Update token usage
            $user->increment('tokens_used', $tokensNeeded);

            // Add token info to response
            $result['tokens_used'] = $tokensNeeded;
            $result['tokens_remaining'] = $user->fresh()->tokens_limit - $user->fresh()->tokens_used;
            $result['plan_features'] = $this->getPlanFeatures($user->plan);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Enterprise RAG query failed', [
                'user_id' => $user->id,
                'query' => substr($query, 0, 100),
                'error' => $e->getMessage(),
                'document_id' => $latestDoc->id ?? null
            ]);

            return response()->json([
                'error' => 'Erro interno do sistema. Tente novamente em alguns instantes.',
                'debug_info' => 'System error for tenant: ' . $user->email,
                'tokens_remaining' => $user->tokens_limit - $user->tokens_used
            ], 500);
        }
    }

    private function calculateTokenCost(string $plan): int
    {
        return match($plan) {
            'enterprise' => 2, // Premium features use more tokens
            'pro' => 1,
            default => 1
        };
    }

    private function getPlanFeatures(string $plan): array
    {
        return match($plan) {
            'enterprise' => [
                'advanced_generation' => true,
                'reranking' => true,
                'citations' => true,
                'max_results' => 15,
                'premium_models' => true
            ],
            'pro' => [
                'advanced_generation' => true,
                'reranking' => true,
                'citations' => true,
                'max_results' => 10,
                'premium_models' => false
            ],
            default => [
                'advanced_generation' => false,
                'reranking' => false,
                'citations' => false,
                'max_results' => 5,
                'premium_models' => false
            ]
        };
    }

    private function callRagApi(string $query, string $mode, $user)
    {
        $tenantSlug = $user->email;
        $baseUrl = 'http://127.0.0.1:8000/api';

        // Buscar documento mais recente
        $latestDoc = DB::table('documents')
            ->where('tenant_slug', $tenantSlug)
            ->orderBy('id', 'desc')
            ->first();

        $params = [
            'q' => $query,
            'tenant_slug' => $tenantSlug,
            'top_k' => $user->plan === 'enterprise' ? 10 : 5
        ];

        if ($latestDoc) {
            $params['document_id'] = $latestDoc->id;
        }

        if ($mode === 'advanced') {
            // Use advanced generation API for Pro+ users
            return Http::timeout(30)->post("{$baseUrl}/rag/generate-answer", array_merge($params, [
                'model' => 'gemini-1.5-flash',
                'temperature' => 0.1,
                'max_tokens' => $user->plan === 'enterprise' ? 4096 : 2048,
                'citations' => true,
                'search_limit' => $user->plan === 'enterprise' ? 10 : 5
            ]));
        } else {
            // Basic query API
            return Http::timeout(15)->get("{$baseUrl}/rag/query", $params);
        }
    }

    private function formatResults(array $results): string
    {
        if (empty($results)) {
            return 'No relevant information found in your documents.';
        }

        $answer = "Based on your documents, here's what I found:\n\n";
        foreach ($results as $index => $result) {
            $answer .= ($index + 1) . ". " . substr($result['content'], 0, 200) . "...\n\n";
        }

        return $answer;
    }
}