<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RagHealthMonitor extends Command
{
    protected $signature = 'rag:monitor {--clear-on-fail : Clear cache if health check fails}';
    protected $description = 'Monitor RAG system health and clear cache if corrupted';

    public function handle()
    {
        $this->info('🔍 Starting RAG health check...');

        $startTime = microtime(true);
        $isHealthy = $this->checkRagHealth();
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($isHealthy) {
            $this->info("✅ RAG system healthy (Response: {$responseTime}ms)");
            Log::info('RAG Health Check: HEALTHY', [
                'response_time_ms' => $responseTime,
                'timestamp' => now()
            ]);
        } else {
            $this->error('❌ RAG system unhealthy - cache may be corrupted');
            Log::warning('RAG Health Check: UNHEALTHY', [
                'response_time_ms' => $responseTime,
                'timestamp' => now()
            ]);

            if ($this->option('clear-on-fail')) {
                $this->clearRagCache();
            } else {
                $this->warn('💡 Use --clear-on-fail to automatically clear cache');
            }
        }

        return $isHealthy ? 0 : 1;
    }

    private function checkRagHealth(): bool
    {
        try {
            // Test 1: API health endpoint
            $healthResponse = Http::timeout(5)->get('http://127.0.0.1:8000/api/health');
            if (!$healthResponse->successful()) {
                $this->error('Health endpoint failed');
                return false;
            }

            // Test 2: Embedding stats endpoint (alternative to cache stats)
            $embeddingResponse = Http::timeout(5)->get('http://127.0.0.1:8000/api/rag/embeddings/stats');
            if (!$embeddingResponse->successful()) {
                $this->error('Embedding stats endpoint failed');
                return false;
            }

            // Test 3: Simple query test
            $queryResponse = Http::timeout(10)->get('http://127.0.0.1:8000/api/rag/query', [
                'q' => 'health_test',
                'tenant_slug' => 'system_health_check',
                'top_k' => 1
            ]);

            if (!$queryResponse->successful()) {
                $this->error('Query test failed');
                return false;
            }

            $queryData = $queryResponse->json();
            if (!isset($queryData['search_stats'])) {
                $this->error('Query response malformed - missing search_stats');
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->error('Health check exception: ' . $e->getMessage());
            return false;
        }
    }

    private function clearRagCache(): void
    {
        $this->info('🧹 Clearing RAG cache...');

        try {
            $response = Http::timeout(10)->post('http://127.0.0.1:8000/api/rag/cache/clear');

            if ($response->successful()) {
                $this->info('✅ RAG cache cleared successfully');
                Log::warning('RAG Cache Cleared', [
                    'reason' => 'health_check_failure',
                    'timestamp' => now()
                ]);

                // Wait a moment then re-test
                sleep(2);
                if ($this->checkRagHealth()) {
                    $this->info('✅ RAG system now healthy after cache clear');
                } else {
                    $this->error('❌ RAG system still unhealthy after cache clear');
                }
            } else {
                $this->error('❌ Failed to clear RAG cache');
            }
        } catch (\Exception $e) {
            $this->error('Cache clear exception: ' . $e->getMessage());
        }
    }
}
