<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\VideoProcessingService;
use App\Models\Document;
use App\Models\Chunk;

class VideoController extends Controller
{
    private VideoProcessingService $videoService;
    
    public function __construct(VideoProcessingService $videoService)
    {
        $this->videoService = $videoService;
    }
    
    /**
     * Process video upload or URL
     * POST /api/video/ingest
     */
    public function ingest(Request $request)
    {
        try {
            $userId = $request->input('user_id', 1);
            $language = $request->input('language', 'pt-BR');
            $service = $request->input('service', 'auto');
            
            // Check if it's a URL or file upload
            if ($request->has('url')) {
                $url = $request->input('url');
                
                if (!$this->videoService->isVideoUrl($url)) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Invalid video URL'
                    ], 400);
                }
                
                Log::info("Processing video URL", ['url' => $url]);
                
                // Process URL
                $result = $this->videoService->processVideo($url, true, [
                    'language' => $language,
                    'service' => $service,
                    'audio_only' => true  // Faster download
                ]);
                
                if (!$result['success']) {
                    return response()->json([
                        'ok' => false,
                        'error' => $result['error']
                    ], 500);
                }
                
                // Create document
                $title = $result['metadata']['title'] ?? 'Video from URL';
                $sourceType = 'video_url';
                
            } elseif ($request->hasFile('file')) {
                $file = $request->file('file');
                
                // Validate file type
                if (!$this->videoService->isVideoFile($file->getClientOriginalName())) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Invalid video file format'
                    ], 400);
                }
                
                // Save uploaded file temporarily
                $tempPath = $file->store('temp', 'local');
                $fullPath = storage_path('app/' . $tempPath);
                
                Log::info("Processing video upload", ['file' => $file->getClientOriginalName()]);
                
                // Process video file
                $result = $this->videoService->processVideo($fullPath, false, [
                    'language' => $language,
                    'service' => $service
                ]);
                
                // Clean up temp file
                @unlink($fullPath);
                
                if (!$result['success']) {
                    return response()->json([
                        'ok' => false,
                        'error' => $result['error']
                    ], 500);
                }
                
                $title = $file->getClientOriginalName();
                $sourceType = 'video_upload';
                
            } else {
                return response()->json([
                    'ok' => false,
                    'error' => 'No video file or URL provided'
                ], 400);
            }
            
            // Create document in database
            $document = Document::create([
                'user_id' => $userId,
                'title' => $title,
                'type' => 'video',
                'file_path' => $result['original_input'],
                'extraction_method' => 'video_transcription',
                'quality_score' => $result['metadata']['transcription']['confidence'] ?? 0.9,
                'metadata' => [
                    'source_type' => $sourceType,
                    'language' => $language,
                    'transcription_service' => $result['metadata']['transcription']['service_used'] ?? 'unknown',
                    'duration' => $result['metadata']['audio']['duration'] ?? 0,
                    'video_metadata' => $result['metadata']
                ]
            ]);
            
            // Create chunks from transcription
            $text = $result['text'];
            $chunkSize = 1000;
            $overlapSize = 200;
            
            $chunks = $this->chunkText($text, $chunkSize, $overlapSize);
            
            $chunkRecords = [];
            foreach ($chunks as $idx => $chunkText) {
                $chunkRecords[] = [
                    'document_id' => $document->id,
                    'content' => $chunkText,
                    'chunk_index' => $idx,
                    'metadata' => json_encode([
                        'source' => 'video_transcription',
                        'language' => $language
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            Chunk::insert($chunkRecords);
            
            Log::info("Video processed successfully", [
                'document_id' => $document->id,
                'chunks_created' => count($chunks)
            ]);
            
            return response()->json([
                'ok' => true,
                'document_id' => $document->id,
                'title' => $title,
                'chunks_created' => count($chunks),
                'transcription_length' => strlen($text),
                'duration' => $result['metadata']['audio']['duration'] ?? 0,
                'language' => $language,
                'service_used' => $result['metadata']['transcription']['service_used'] ?? 'unknown',
                'confidence' => $result['metadata']['transcription']['confidence'] ?? 0.0
            ]);
            
        } catch (\Exception $e) {
            Log::error("Video ingest failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'ok' => false,
                'error' => 'Video processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get video info without downloading
     * POST /api/video/info
     */
    public function getInfo(Request $request)
    {
        try {
            $url = $request->input('url');
            
            if (!$url || !$this->videoService->isVideoUrl($url)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid video URL'
                ], 400);
            }
            
            $infoScript = base_path('scripts/video_processing/video_downloader.py');
            $cmd = "python3 " . escapeshellarg($infoScript) . " info " . escapeshellarg($url) . " 2>&1";
            
            $output = shell_exec($cmd);
            $result = json_decode($output, true);
            
            if (!$result || !$result['success']) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Failed to get video info'
                ], 500);
            }
            
            return response()->json([
                'ok' => true,
                'video' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Chunk text helper
     */
    private function chunkText(string $text, int $chunkSize = 1000, int $overlapSize = 200): array
    {
        $chunks = [];
        $length = strlen($text);
        $start = 0;
        
        while ($start < $length) {
            $chunk = substr($text, $start, $chunkSize);
            
            // Try to end at sentence boundary
            if ($start + $chunkSize < $length) {
                $lastPeriod = strrpos($chunk, '.');
                $lastQuestion = strrpos($chunk, '?');
                $lastExclamation = strrpos($chunk, '!');
                
                $boundary = max($lastPeriod, $lastQuestion, $lastExclamation);
                
                if ($boundary !== false && $boundary > $chunkSize * 0.7) {
                    $chunk = substr($chunk, 0, $boundary + 1);
                }
            }
            
            $chunks[] = trim($chunk);
            $start += strlen($chunk) - $overlapSize;
        }
        
        return array_filter($chunks);
    }
}


