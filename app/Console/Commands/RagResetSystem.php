<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class RagResetSystem extends Command
{
    protected $signature = 'rag:reset-system {--force : Skip confirmation prompt}';
    protected $description = 'Emergency RAG system reset - clear cache, restart services';

    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  This will reset the entire RAG system. Continue?')) {
                $this->info('Reset cancelled');
                return 0;
            }
        }

        $this->info('🚨 Starting emergency RAG system reset...');

        $steps = [
            'Clearing RAG cache' => [$this, 'clearRagCache'],
            'Clearing Laravel cache' => [$this, 'clearLaravelCache'],
            'Checking system health' => [$this, 'checkSystemHealth'],
        ];

        foreach ($steps as $description => $method) {
            $this->info("⚡ {$description}...");

            try {
                $success = call_user_func($method);
                if ($success) {
                    $this->info("   ✅ {$description} completed");
                } else {
                    $this->error("   ❌ {$description} failed");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ {$description} error: " . $e->getMessage());
            }
        }

        $this->info('🎯 RAG system reset complete');
        Log::warning('RAG System Reset Performed', [
            'user' => get_current_user(),
            'timestamp' => now(),
            'force' => $this->option('force')
        ]);

        return 0;
    }

    private function clearRagCache(): bool
    {
        $response = Http::timeout(15)->post('http://127.0.0.1:8000/api/rag/cache/clear');
        return $response->successful();
    }

    private function clearLaravelCache(): bool
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkSystemHealth(): bool
    {
        sleep(3); // Wait for systems to stabilize

        $response = Http::timeout(10)->get('http://127.0.0.1:8000/api/health');
        if (!$response->successful()) {
            return false;
        }

        // Quick test query
        $queryResponse = Http::timeout(10)->get('http://127.0.0.1:8000/api/rag/query', [
            'q' => 'system_reset_test',
            'tenant_slug' => 'system_test',
            'top_k' => 1
        ]);

        return $queryResponse->successful();
    }
}
