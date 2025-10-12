<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoProcessingService
{
    private string $pythonPath = 'python3';
    private string $videoScriptsPath;
    
    public function __construct()
    {
        $this->videoScriptsPath = base_path('scripts/video_processing');
    }
    
    /**
     * Process video: download (if URL), extract audio, transcribe
     */
    public function processVideo(string $input, bool $isUrl = false, array $options = []): array
    {
        try {
            $videoPath = $input;
            $metadata = [];
            
            // Step 1: Download video if URL
            if ($isUrl) {
                Log::info("Downloading video from URL", ['url' => $input]);
                $downloadResult = $this->downloadVideo($input, $options['audio_only'] ?? false);
                
                if (!$downloadResult['success']) {
                    return $downloadResult;
                }
                
                $videoPath = $downloadResult['file_path'];
                $metadata = array_merge($metadata, $downloadResult);
            }
            
            // Step 2: Extract audio from video
            Log::info("Extracting audio from video", ['video_path' => $videoPath]);
            $audioResult = $this->extractAudio($videoPath);
            
            if (!$audioResult['success']) {
                return $audioResult;
            }
            
            $audioPath = $audioResult['audio_path'];
            $metadata = array_merge($metadata, ['audio' => $audioResult]);
            
            // Step 3: Transcribe audio
            Log::info("Transcribing audio", ['audio_path' => $audioPath]);
            $transcriptionResult = $this->transcribeAudio(
                $audioPath, 
                $options['language'] ?? 'pt-BR',
                $options['service'] ?? 'auto'
            );
            
            if (!$transcriptionResult['success']) {
                return $transcriptionResult;
            }
            
            $metadata = array_merge($metadata, ['transcription' => $transcriptionResult]);
            
            // Clean up temporary files
            if ($isUrl && file_exists($videoPath)) {
                @unlink($videoPath);
            }
            if (file_exists($audioPath)) {
                @unlink($audioPath);
            }
            
            return [
                'success' => true,
                'text' => $transcriptionResult['text'],
                'metadata' => $metadata,
                'source_type' => $isUrl ? 'video_url' : 'video_upload',
                'original_input' => $input
            ];
            
        } catch (\Exception $e) {
            Log::error("Video processing failed", [
                'input' => $input,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Video processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Download video from URL using yt-dlp
     */
    public function downloadVideo(string $url, bool $audioOnly = false): array
    {
        $downloaderScript = $this->videoScriptsPath . '/video_downloader.py';
        
        if (!file_exists($downloaderScript)) {
            return [
                'success' => false,
                'error' => 'Video downloader script not found'
            ];
        }
        
        $tempDir = sys_get_temp_dir() . '/video_rag_' . uniqid();
        @mkdir($tempDir, 0755, true);
        
        $audioOnlyFlag = $audioOnly ? '--audio-only' : '';
        $cmd = "{$this->pythonPath} " . escapeshellarg($downloaderScript) . 
               " " . escapeshellarg($url) . 
               " " . escapeshellarg($tempDir) . 
               " {$audioOnlyFlag} 2>&1";
        
        $output = shell_exec($cmd);
        $result = json_decode($output, true);
        
        if (!$result || !$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Download failed: ' . $output
            ];
        }
        
        return $result;
    }
    
    /**
     * Extract audio from video using FFmpeg
     */
    public function extractAudio(string $videoPath): array
    {
        $extractorScript = $this->videoScriptsPath . '/audio_extractor.py';
        
        if (!file_exists($extractorScript)) {
            return [
                'success' => false,
                'error' => 'Audio extractor script not found'
            ];
        }
        
        $cmd = "{$this->pythonPath} " . escapeshellarg($extractorScript) . 
               " " . escapeshellarg($videoPath) . " 2>&1";
        
        $output = shell_exec($cmd);
        $result = json_decode($output, true);
        
        if (!$result || !$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Audio extraction failed: ' . $output
            ];
        }
        
        return $result;
    }
    
    /**
     * Transcribe audio using Google/Gemini/OpenAI
     */
    public function transcribeAudio(string $audioPath, string $language = 'pt-BR', string $service = 'auto'): array
    {
        $transcriptionScript = $this->videoScriptsPath . '/transcription_service.py';
        
        if (!file_exists($transcriptionScript)) {
            return [
                'success' => false,
                'error' => 'Transcription service script not found'
            ];
        }
        
        $cmd = "{$this->pythonPath} " . escapeshellarg($transcriptionScript) . 
               " " . escapeshellarg($audioPath) . 
               " " . escapeshellarg($language) . 
               " " . escapeshellarg($service) . " 2>&1";
        
        $output = shell_exec($cmd);
        $result = json_decode($output, true);
        
        if (!$result || !$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Transcription failed: ' . $output
            ];
        }
        
        return $result;
    }
    
    /**
     * Check if input is a URL
     */
    public function isVideoUrl(string $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Check if file is a video
     */
    public function isVideoFile(string $path): bool
    {
        $videoExtensions = [
            'mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv', 
            'webm', 'm4v', 'mpg', 'mpeg', '3gp', 'ogv'
        ];
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, $videoExtensions);
    }
}


