<?php

namespace App\Modules\VideoProcessing\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AudioExtractionService
{
    private string $pythonScript;
    private int $timeout;

    public function __construct()
    {
        $this->pythonScript = config('video_processing.youtube.python_script');
        $this->timeout = config('video_processing.youtube.download_timeout', 600);
    }

    /**
     * Download audio from YouTube video
     */
    public function download(string $videoId): string
    {
        $tempDir = sys_get_temp_dir() . '/video_processing_' . uniqid();
        @mkdir($tempDir, 0755, true);

        try {
            $outputPath = $tempDir . '/' . $videoId . '.mp3';
            
            $result = Process::timeout($this->timeout)
                ->run([
                    'python3',
                    $this->pythonScript,
                    'download',
                    $videoId,
                    $outputPath
                ]);

            if (!$result->successful()) {
                throw new \Exception('Audio download failed: ' . $result->errorOutput());
            }

            $output = $result->output();
            $data = json_decode($output, true);

            if (!$data || !$data['success']) {
                throw new \Exception($data['error'] ?? 'Unknown download error');
            }

            if (!file_exists($data['data']['file_path'])) {
                throw new \Exception('Downloaded file not found');
            }

            return $data['data']['file_path'];
        } catch (\Exception $e) {
            // Cleanup on failure
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->cleanup($tempDir);
            }
            
            Log::error('Audio extraction failed', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Upload audio file to cloud storage
     */
    public function uploadToStorage(string $localPath, string $remotePath): bool
    {
        try {
            $disk = Storage::disk(config('video_processing.storage.disk'));
            $success = $disk->put($remotePath, file_get_contents($localPath));
            
            if ($success) {
                Log::info('Audio uploaded to storage', [
                    'remote_path' => $remotePath,
                    'file_size' => filesize($localPath),
                ]);
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error('Audio upload failed', [
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Generate signed URL for audio file
     */
    public function generateSignedUrl(string $path, int $ttl = null): string
    {
        $ttl = $ttl ?? config('video_processing.signed_url_ttl', 7 * 24 * 60 * 60);
        
        try {
            $disk = Storage::disk(config('video_processing.storage.disk'));
            return $disk->temporaryUrl($path, now()->addSeconds($ttl));
        } catch (\Exception $e) {
            Log::error('Signed URL generation failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Cleanup temporary files
     */
    public function cleanup(string $path): void
    {
        try {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $files = glob($path . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($path);
            }
        } catch (\Exception $e) {
            Log::warning('Cleanup failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

