<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpar cache RAG diariamente às 3:00 AM
        $schedule->call(function () {
            try {
                $response = Http::timeout(10)->post('http://127.0.0.1:8000/api/rag/cache/clear');
                Log::info('RAG Daily Cache Clear', [
                    'success' => $response->successful(),
                    'timestamp' => now()
                ]);
            } catch (\Exception $e) {
                Log::error('RAG Daily Cache Clear Failed', [
                    'error' => $e->getMessage(),
                    'timestamp' => now()
                ]);
            }
        })->dailyAt('03:00');

        // Verificar saúde do sistema RAG a cada hora com limpeza automática
        $schedule->command('rag:monitor --clear-on-fail')->hourly();

        // Health check rápido a cada 15 minutos (sem limpeza automática)
        $schedule->command('rag:monitor')->everyFifteenMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}