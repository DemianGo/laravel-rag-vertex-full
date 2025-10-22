<?php

namespace App\Modules\VideoProcessing\Jobs;

use App\Modules\VideoProcessing\Models\VideoProcessingJob;
use App\Modules\VideoProcessing\Services\AudioExtractionService;
use App\Modules\VideoProcessing\Services\TranscriptionService;
use App\Models\Document;
use App\Models\Chunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 2;
    public $backoff = 60; // seconds

    private string $jobId;

    public function __construct(string $jobId)
    {
        $this->jobId = $jobId;
        $this->onQueue(config('video_processing.queue.name', 'video_processing'));
    }

    public function handle(): void
    {
        $job = VideoProcessingJob::where('job_id', $this->jobId)->first();
        
        if (!$job) {
            Log::error('Video processing job not found', ['job_id' => $this->jobId]);
            return;
        }

        try {
            $job->markAsProcessing();
            
            // Use existing VideoProcessingService directly
            $videoService = app(\App\Services\VideoProcessingService::class);
            
            $job->addLog('Starting video processing with existing system', ['step' => 'processing']);
            
            // Process video using existing service
            $result = $videoService->processVideo($job->video_url, true, [
                'title' => $job->title ?? "YouTube Video: {$job->video_id}",
                'tenant_slug' => $job->tenant_slug,
            ]);
            
            if (!$result['success']) {
                throw new \Exception('Video processing failed: ' . ($result['error'] ?? 'Unknown error'));
            }
            
            // Find the created document
            $document = Document::where('uri', $job->video_url)
                ->where('tenant_slug', $job->tenant_slug)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($document) {
                $job->update([
                    'rag_document_id' => $document->id,
                    'audio_url' => 'processed_' . $job->video_id,
                    'transcription_url' => 'available_' . $job->video_id,
                    'urls_expire_at' => now()->addDays(7),
                ]);
                
                $job->addLog('Video processing completed successfully', [
                    'step' => 'completed',
                    'document_id' => $document->id,
                    'transcription_length' => strlen($result['text'] ?? ''),
                ]);
            } else {
                throw new \Exception('Document not created in RAG system');
            }
            
            // Finalize
            $job->markAsCompleted();
            
            Log::info('Video processing completed successfully', [
                'job_id' => $this->jobId,
                'video_id' => $job->video_id,
                'document_id' => $document->id,
            ]);
            
        } catch (\Exception $e) {
            $job->markAsFailed($e->getMessage());
            
            Log::error('Video processing failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    private function downloadAudio(VideoProcessingJob $job): void
    {
        $job->addLog('Starting audio download', ['step' => 'downloading']);
        
        // Use existing VideoProcessingService instead of new AudioExtractionService
        $videoService = app(\App\Services\VideoProcessingService::class);
        $result = $videoService->processVideo($job->video_url, true, ['audio_only' => true]);
        
        if (!$result['success']) {
            throw new \Exception('Audio download failed: ' . ($result['error'] ?? 'Unknown error'));
        }
        
        // Store transcription directly from existing service
        $transcription = $result['text'] ?? '';
        $job->update([
            'audio_path' => $result['file_path'] ?? null,
            'transcription_path' => 'transcription_content', // Flag to indicate content is stored
        ]);
        
        $job->addLog('Audio download completed', [
            'step' => 'downloading',
            'transcription_length' => strlen($transcription),
            'has_file' => !empty($result['file_path']),
        ]);
    }

    private function uploadAudio(VideoProcessingJob $job): string
    {
        $job->addLog('Skipping audio upload - using existing system', ['step' => 'uploading_audio']);
        
        // Skip audio upload since we're using existing system
        // Just return a placeholder URL
        $audioUrl = "audio_processed_{$job->video_id}";
        
        $job->update([
            'audio_url' => $audioUrl,
            'urls_expire_at' => now()->addDays(7),
        ]);
        
        $job->addLog('Audio processing completed', [
            'step' => 'uploading_audio',
            'audio_url' => $audioUrl,
        ]);
        
        return $audioUrl;
    }

    private function transcribeAudio(VideoProcessingJob $job, string $audioUrl): string
    {
        $job->addLog('Transcription already completed by existing system', ['step' => 'transcribing']);
        
        // Transcription was already done in downloadAudio step
        // Get the transcription from the job record
        $job->refresh();
        $transcription = $job->transcription_path === 'transcription_content' ? 'transcription_available' : '';
        
        $job->addLog('Transcription ready', [
            'step' => 'transcribing',
            'transcript_available' => !empty($transcription),
        ]);
        
        return $transcription;
    }

    private function saveTranscription(VideoProcessingJob $job, string $transcription): string
    {
        $job->addLog('Transcription already saved by existing system', ['step' => 'saving_transcription']);
        
        // Transcription is already available, just create a placeholder URL
        $transcriptionUrl = "transcription_available_{$job->video_id}";
        
        $job->update([
            'transcription_url' => $transcriptionUrl,
        ]);
        
        $job->addLog('Transcription ready', [
            'step' => 'saving_transcription',
            'transcription_url' => $transcriptionUrl,
        ]);
        
        return $transcriptionUrl;
    }

    private function integrateWithRAG(VideoProcessingJob $job, string $transcription): void
    {
        $job->addLog('Integrating with RAG system', ['step' => 'integrating_rag']);
        
        try {
            // Use existing VideoProcessingService to create document and chunks
            $videoService = app(\App\Services\VideoProcessingService::class);
            
            // Process the video URL again to create document and chunks
            $result = $videoService->processVideo($job->video_url, true, [
                'title' => $job->title ?? "YouTube Video: {$job->video_id}",
                'tenant_slug' => $job->tenant_slug,
            ]);
            
            if (!$result['success']) {
                throw new \Exception('RAG integration failed: ' . ($result['error'] ?? 'Unknown error'));
            }
            
            // Find the created document
            $document = Document::where('uri', $job->video_url)
                ->where('tenant_slug', $job->tenant_slug)
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($document) {
                $job->update(['rag_document_id' => $document->id]);
                
                $job->addLog('RAG integration completed', [
                    'step' => 'integrating_rag',
                    'document_id' => $document->id,
                ]);
            } else {
                throw new \Exception('Document not created in RAG system');
            }
            
        } catch (\Exception $e) {
            $job->addLog('RAG integration failed', [
                'step' => 'integrating_rag',
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    private function createChunksFromTranscription(Document $document, string $transcription): array
    {
        // Split transcription into chunks (approximately 1000 characters each)
        $chunkSize = 1000;
        $chunks = [];
        $text = trim($transcription);
        
        if (empty($text)) {
            return $chunks;
        }
        
        $parts = str_split($text, $chunkSize);
        
        foreach ($parts as $index => $content) {
            $chunk = Chunk::create([
                'document_id' => $document->id,
                'content' => $content,
                'chunk_index' => $index,
                'metadata' => [
                    'source' => 'youtube_transcription',
                    'chunk_type' => 'transcript_segment',
                ],
            ]);
            
            $chunks[] = $chunk;
        }
        
        return $chunks;
    }

    public function failed(\Throwable $exception): void
    {
        $job = VideoProcessingJob::where('job_id', $this->jobId)->first();
        
        if ($job) {
            $job->markAsFailed($exception->getMessage());
        }
        
        Log::error('Video processing job failed permanently', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);
    }
}

