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
                
                // Get video info first to check duration
                $videoInfo = $this->videoService->getVideoInfo($url);
                
                if ($videoInfo && isset($videoInfo['duration'])) {
                    $durationMinutes = $videoInfo['duration'] / 60;
                    
                    // LIMIT: 60 minutes (1 hour) max
                    if ($durationMinutes > 60) {
                        return response()->json([
                            'ok' => false,
                            'error' => sprintf(
                                '❌ Vídeo muito longo (%.0f minutos). Limite máximo: 60 minutos (1 hora). Para vídeos mais longos, divida em partes menores.',
                                $durationMinutes
                            )
                        ], 400);
                    }
                }
                
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
            
            // Get tenant_slug from authenticated user
            $user = auth('sanctum')->user();
            $tenantSlug = $user ? "user_{$user->id}" : 'default';
            
            // Extract video info from metadata
            $videoMetadata = $result['metadata'];
            $videoDuration = $videoMetadata['duration'] ?? 
                            ($videoMetadata['audio']['duration'] ?? 0);
            $videoThumbnail = $videoMetadata['thumbnail'] ?? '';
            
            // Create document in database (using correct schema)
            $document = Document::create([
                'title' => $title,
                'source' => $sourceType,
                'uri' => $result['original_input'],
                'tenant_slug' => $tenantSlug,
                'metadata' => json_encode([
                    'type' => 'video',
                    'source_type' => $sourceType,
                    'language' => $language,
                    'extraction_method' => 'video_transcription',
                    'quality_score' => $videoMetadata['transcription']['confidence'] ?? 0.9,
                    'transcription_service' => $videoMetadata['transcription']['service_used'] ?? 'unknown',
                    'duration' => $videoDuration,
                    'thumbnail' => $videoThumbnail,
                    'video_metadata' => $videoMetadata
                ])
            ]);
            
            // Create chunks from transcription
            Log::info("Starting chunk creation", [
                'document_id' => $document->id,
                'text_length' => strlen($result['text'])
            ]);
            
            // Clean transcription text to avoid UTF-8 issues in chunks
            $text = $this->cleanUtf8($result['text']);
            $chunkSize = 1000;
            $overlapSize = 200;
            
            Log::info("Calling chunkText", [
                'text_length' => strlen($text),
                'chunk_size' => $chunkSize
            ]);
            
            $chunks = $this->chunkText($text, $chunkSize, $overlapSize);
            
            Log::info("Chunks generated", [
                'chunks_count' => count($chunks)
            ]);
            
            $chunkRecords = [];
            foreach ($chunks as $idx => $chunkText) {
                // Clean each chunk to avoid UTF-8 issues
                $cleanChunk = $this->cleanUtf8($chunkText);
                
                $chunkRecords[] = [
                    'document_id' => $document->id,
                    'content' => $cleanChunk,
                    'ord' => $idx,
                    'meta' => json_encode([
                        'source' => 'video_transcription',
                        'language' => $language
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            Log::info("Inserting chunks into database", [
                'records_count' => count($chunkRecords)
            ]);
            
            if (count($chunkRecords) > 0) {
                Chunk::insert($chunkRecords);
                Log::info("Chunks inserted successfully");
            } else {
                Log::warning("No chunks to insert");
            }
            
            Log::info("Video processed successfully", [
                'document_id' => $document->id,
                'chunks_created' => count($chunks)
            ]);
            
            // Use previously extracted video info for response
            $duration = $videoDuration;
            $thumbnail = $videoThumbnail;
            
            // Clean ALL strings to avoid UTF-8 issues
            $cleanData = [
                'ok' => true,
                'document_id' => $document->id,
                'title' => $this->cleanUtf8($title),
                'chunks_created' => count($chunks),
                'transcription_length' => strlen($text),
                'transcription_text' => $text, // Full transcription for display
                'duration' => $duration,
                'thumbnail' => $this->cleanUtf8($thumbnail),
                'language' => $language,
                'service_used' => $this->cleanUtf8($videoMetadata['transcription']['service_used'] ?? 'unknown'),
                'confidence' => $videoMetadata['transcription']['confidence'] ?? 0.0
            ];
            
            // Double-check: encode and decode to ensure valid UTF-8
            $jsonString = json_encode($cleanData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            
            return response($jsonString, 200)
                ->header('Content-Type', 'application/json');
            
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
     * Clean UTF-8 string to avoid encoding issues
     */
    private function cleanUtf8(?string $str): string
    {
        if (!$str) {
            return '';
        }
        
        // Remove invalid UTF-8 characters
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        
        // Remove control characters except newline and tab
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
        
        return $str;
    }
    
    /**
     * Chunk text helper
     */
    private function chunkText(string $text, int $chunkSize = 1000, int $overlapSize = 200): array
    {
        $chunks = [];
        $length = strlen($text);
        $start = 0;
        $minChunkSize = 100; // Minimum chunk size to avoid tiny fragments
        
        while ($start < $length) {
            $chunk = substr($text, $start, $chunkSize);
            $chunkLength = strlen($chunk);
            
            // Try to end at sentence boundary
            if ($start + $chunkSize < $length) {
                $lastPeriod = strrpos($chunk, '.');
                $lastQuestion = strrpos($chunk, '?');
                $lastExclamation = strrpos($chunk, '!');
                
                $boundary = max($lastPeriod, $lastQuestion, $lastExclamation);
                
                if ($boundary !== false && $boundary > $chunkSize * 0.7) {
                    $chunk = substr($chunk, 0, $boundary + 1);
                    $chunkLength = strlen($chunk);
                }
            }
            
            // Only add chunk if it's large enough
            $trimmedChunk = trim($chunk);
            if (strlen($trimmedChunk) >= $minChunkSize) {
                $chunks[] = $trimmedChunk;
            }
            
            // Ensure we always advance (prevent infinite loop)
            $advance = max($chunkLength - $overlapSize, 1);
            $start += $advance;
            
            // If remaining text is too small, stop
            if ($length - $start < $minChunkSize) {
                break;
            }
        }
        
        return array_filter($chunks);
    }
}


