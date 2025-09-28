<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Consume existing APIs
        $health = $this->getHealthStatus();
        $cacheStats = $this->getCacheStats();
        $embeddingStats = $this->getEmbeddingStats();
        $documents = $this->getDocuments($user);

        return view('dashboard', compact('user', 'health', 'cacheStats', 'embeddingStats', 'documents'));
    }

    private function getHealthStatus(): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/health');
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch health status', ['error' => $e->getMessage()]);
        }

        return ['ok' => false, 'status' => 'offline'];
    }

    private function getCacheStats(): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/rag/cache/stats');
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch cache stats', ['error' => $e->getMessage()]);
        }

        return ['ok' => false, 'cache_stats' => []];
    }

    private function getEmbeddingStats(): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/rag/embeddings/stats');
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch embedding stats', ['error' => $e->getMessage()]);
        }

        return ['ok' => false, 'embedding_cache' => []];
    }

    private function getDocuments($user): array
    {
        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:8000/api/docs/list', [
                'tenant_slug' => 'user_' . $user->id
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['docs'] ?? [];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch documents', ['error' => $e->getMessage()]);
        }

        return [];
    }
}