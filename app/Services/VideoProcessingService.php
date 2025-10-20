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
                
                // Verify file exists
                if (!file_exists($videoPath)) {
                    Log::error("Downloaded file not found", [
                        'expected_path' => $videoPath,
                        'download_result' => $downloadResult
                    ]);
                    return [
                        'success' => false,
                        'error' => 'Downloaded file not found'
                    ];
                }
                
                $metadata = array_merge($metadata, $downloadResult);
            }
            
            // Step 2: Extract audio from video (skip if already audio)
            $extension = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
            $isAudioFile = in_array($extension, ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac']);
            
            if ($isAudioFile) {
                // Already audio, skip extraction
                Log::info("File is already audio, skipping extraction", ['path' => $videoPath]);
                $audioPath = $videoPath;
                $metadata = array_merge($metadata, ['audio' => [
                    'format' => $extension,
                    'skipped_extraction' => true
                ]]);
            } else {
                // Need to extract audio from video
                Log::info("Extracting audio from video", ['video_path' => $videoPath]);
                $audioResult = $this->extractAudio($videoPath);
                
                if (!$audioResult['success']) {
                    return $audioResult;
                }
                
                $audioPath = $audioResult['audio_path'];
                $metadata = array_merge($metadata, ['audio' => $audioResult]);
            }
            
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
        $cmd = "timeout 300 {$this->pythonPath} " . escapeshellarg($downloaderScript) . 
               " " . escapeshellarg($url) . 
               " " . escapeshellarg($tempDir) . 
               " {$audioOnlyFlag} 2>/dev/null";
        
        Log::info("Executing video download command", ['cmd' => $cmd]);
        $output = shell_exec($cmd);
        
        if (!$output) {
            Log::error("Video download command returned empty output", ['cmd' => $cmd]);
            return [
                'success' => false,
                'error' => 'Download command failed or timed out'
            ];
        }
        
        // Extract JSON from output (yt-dlp prints progress before JSON)
        // Strategy: Find the position of the first { and extract from there to the end
        $jsonStart = strpos($output, '{');
        
        if ($jsonStart !== false) {
            $jsonOutput = substr($output, $jsonStart);
            
            // Find the last } to close the JSON
            $jsonEnd = strrpos($jsonOutput, '}');
            if ($jsonEnd !== false) {
                $jsonOutput = substr($jsonOutput, 0, $jsonEnd + 1);
            }
        } else {
            // No JSON found, return error
            return [
                'success' => false,
                'error' => 'No JSON response from downloader'
            ];
        }
        
        $result = json_decode($jsonOutput, true);
        
        if (!$result || !isset($result['success']) || !$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Download failed'
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
               " " . escapeshellarg($videoPath) . " 2>/dev/null";
        
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
        
        // Pass API keys via environment variables
        $geminiKey = env('GOOGLE_GENAI_API_KEY', '');
        $openaiKey = env('OPENAI_API_KEY', '');
        
        $envVars = '';
        if ($geminiKey) {
            $envVars .= 'GOOGLE_GENAI_API_KEY=' . escapeshellarg($geminiKey) . ' ';
        }
        if ($openaiKey) {
            $envVars .= 'OPENAI_API_KEY=' . escapeshellarg($openaiKey) . ' ';
        }
        
        $cmd = $envVars . "{$this->pythonPath} " . escapeshellarg($transcriptionScript) . 
               " " . escapeshellarg($audioPath) . 
               " " . escapeshellarg($language) . 
               " " . escapeshellarg($service) . " 2>/dev/null";
        
        $output = shell_exec($cmd);
        
        // Extract JSON from output (ignore warnings and progress messages)
        $jsonStart = strpos($output, '{');
        
        if ($jsonStart !== false) {
            $jsonOutput = substr($output, $jsonStart);
            $jsonEnd = strrpos($jsonOutput, '}');
            if ($jsonEnd !== false) {
                $jsonOutput = substr($jsonOutput, 0, $jsonEnd + 1);
            }
        } else {
            return [
                'success' => false,
                'error' => 'No JSON response from transcription service'
            ];
        }
        
        $result = json_decode($jsonOutput, true);
        
        if (!$result || !isset($result['success']) || !$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Transcription failed'
            ];
        }
        
        return $result;
    }
    
    /**
     * Check if input is a URL
     */
    public function isVideoUrl(string $input): bool
    {
        if (filter_var($input, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        
        // Check for common video hosting platforms
        $videoDomains = [
            'youtube.com', 'youtu.be', 'vimeo.com', 'dailymotion.com',
            'facebook.com', 'instagram.com', 'tiktok.com', 'twitter.com',
            'twitch.tv', 'bilibili.com', 'rutube.ru', 'ok.ru'
        ];
        
        $host = parse_url($input, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        
        foreach ($videoDomains as $domain) {
            if (strpos($host, $domain) !== false) {
                return true;
            }
        }
        
        return false;
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
    
    /**
     * Get video information without downloading
     */
    public function getVideoInfo(string $url): ?array
    {
        $downloaderScript = $this->videoScriptsPath . '/video_downloader.py';
        
        if (!file_exists($downloaderScript)) {
            return null;
        }
        
        $cmd = "timeout 60 {$this->pythonPath} " . escapeshellarg($downloaderScript) . 
               " --info-only " . escapeshellarg($url) . " 2>/dev/null";
        
        Log::info("Executing video info command", ['cmd' => $cmd]);
        $output = shell_exec($cmd);
        
        if (!$output) {
            Log::error("Video info command returned empty output", ['cmd' => $cmd]);
            return null;
        }
        
        // Extract JSON from output
        $jsonStart = strpos($output, '{');
        if ($jsonStart !== false) {
            $jsonOutput = substr($output, $jsonStart);
            $jsonEnd = strrpos($jsonOutput, '}');
            if ($jsonEnd !== false) {
                $jsonOutput = substr($jsonOutput, 0, $jsonEnd + 1);
            }
            
            $result = json_decode($jsonOutput, true);
            
            if ($result && isset($result['success']) && $result['success']) {
                return $result;
            }
        }
        
        return null;
    }
}


