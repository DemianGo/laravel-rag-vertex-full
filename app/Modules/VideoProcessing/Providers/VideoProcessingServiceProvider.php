<?php

namespace App\Modules\VideoProcessing\Providers;

use App\Modules\VideoProcessing\Services\YoutubeService;
use App\Modules\VideoProcessing\Services\AudioExtractionService;
use App\Modules\VideoProcessing\Services\TranscriptionService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Queue;

class VideoProcessingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load module routes only if enabled
        if (config('video_processing.enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        }
        
        // Register migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/video_processing.php' => config_path('video_processing.php'),
        ], 'video-processing-config');
        
        // Register queue worker timeout
        Queue::after(function ($event) {
            if ($event->job->timeout() > 1800) {
                Queue::timeout(1800); // 30 minutes max
            }
        });
        
        // Schedule quota reset commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Commands will be added here if needed
            ]);
        }
    }

    public function register(): void
    {
        // Bind service interfaces
        $this->app->singleton(YoutubeService::class);
        $this->app->singleton(AudioExtractionService::class);
        $this->app->singleton(TranscriptionService::class);
        
        // Configure GCS disk if not exists
        $this->configureGCSDisk();
    }

    private function configureGCSDisk(): void
    {
        $diskName = config('video_processing.storage.disk', 'gcs_videos');
        
        if (!config("filesystems.disks.{$diskName}")) {
            config([
                "filesystems.disks.{$diskName}" => [
                    'driver' => 'gcs',
                    'project_id' => config('video_processing.vertex_ai.project_id'),
                    'key_file' => config('video_processing.vertex_ai.key_file'),
                    'bucket' => config('video_processing.storage.bucket'),
                    'path_prefix' => '',
                    'storage_api_uri' => null,
                    'visibility' => 'public',
                ],
            ]);
        }
    }
}

