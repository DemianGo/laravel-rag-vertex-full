<?php

namespace App\Http\Controllers;

use App\Models\RagFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RagFeedbackController extends Controller
{
    /**
     * Salva feedback do usuário sobre uma resposta RAG
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|max:10000',
                'document_id' => 'nullable|integer|exists:documents,id',
                'rating' => 'required|integer|in:-1,1',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Validação falhou',
                    'details' => $validator->errors()
                ], 422);
            }

            $feedback = RagFeedback::create([
                'query' => $request->input('query'),
                'document_id' => $request->input('document_id'),
                'rating' => $request->input('rating'),
                'metadata' => $request->input('metadata', []),
            ]);

            Log::info('Feedback salvo', [
                'id' => $feedback->id,
                'rating' => $feedback->rating,
                'document_id' => $feedback->document_id
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Feedback salvo com sucesso',
                'feedback_id' => $feedback->id
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao salvar feedback: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Erro ao salvar feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna estatísticas de feedback
     */
    public function stats(): JsonResponse
    {
        try {
            // Estatísticas gerais
            $totalFeedbacks = RagFeedback::count();
            $positiveFeedbacks = RagFeedback::where('rating', 1)->count();
            $negativeFeedbacks = RagFeedback::where('rating', -1)->count();
            
            $satisfactionRate = $totalFeedbacks > 0 
                ? round(($positiveFeedbacks / $totalFeedbacks) * 100, 2) 
                : 0;

            // Top 5 queries com melhor avaliação
            $topQueries = RagFeedback::select('query', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as count'))
                ->groupBy('query')
                ->havingRaw('COUNT(*) >= 2')
                ->orderByDesc('avg_rating')
                ->limit(5)
                ->get();

            // Top 5 queries com pior avaliação
            $worstQueries = RagFeedback::select('query', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as count'))
                ->groupBy('query')
                ->havingRaw('COUNT(*) >= 2')
                ->orderBy('avg_rating')
                ->limit(5)
                ->get();

            // Documentos com melhor performance
            $topDocuments = RagFeedback::select('document_id', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as count'))
                ->whereNotNull('document_id')
                ->groupBy('document_id')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('avg_rating')
                ->limit(5)
                ->with('document:id,title')
                ->get();

            // Tendência nos últimos 7 dias
            $dailyStats = RagFeedback::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as positive'),
                    DB::raw('SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) as negative')
                )
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'ok' => true,
                'stats' => [
                    'total_feedbacks' => $totalFeedbacks,
                    'positive_feedbacks' => $positiveFeedbacks,
                    'negative_feedbacks' => $negativeFeedbacks,
                    'satisfaction_rate' => $satisfactionRate,
                ],
                'top_queries' => $topQueries,
                'worst_queries' => $worstQueries,
                'top_documents' => $topDocuments,
                'daily_trend' => $dailyStats,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar stats de feedback: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna feedbacks recentes (últimos 50)
     */
    public function recent(): JsonResponse
    {
        try {
            $feedbacks = RagFeedback::with('document:id,title')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'ok' => true,
                'feedbacks' => $feedbacks
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar feedbacks recentes: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'error' => 'Erro ao buscar feedbacks: ' . $e->getMessage()
            ], 500);
        }
    }
}
