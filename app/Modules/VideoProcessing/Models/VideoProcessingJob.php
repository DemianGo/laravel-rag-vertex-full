<?php

namespace App\Modules\VideoProcessing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VideoProcessingJob extends Model
{
    protected $fillable = [
        'job_id',
        'tenant_slug',
        'video_id',
        'video_url',
        'status',
        'title',
        'description',
        'duration_seconds',
        'channel_name',
        'published_at',
        'audio_path',
        'transcription_path',
        'audio_url',
        'transcription_url',
        'urls_expire_at',
        'error_message',
        'processing_log',
        'retry_count',
        'rag_document_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'processing_log' => 'array',
        'published_at' => 'datetime',
        'urls_expire_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Mark job as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
        
        $this->addLog('Job started processing');
    }

    /**
     * Mark job as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        
        $this->addLog('Job completed successfully');
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
        
        $this->addLog('Job failed', ['error' => $error]);
    }

    /**
     * Add log entry
     */
    public function addLog(string $message, array $context = []): void
    {
        $log = $this->processing_log ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'message' => $message,
            'context' => $context,
        ];
        
        $this->update(['processing_log' => $log]);
    }

    /**
     * Increment retry count
     */
    public function incrementRetry(): bool
    {
        $newCount = $this->retry_count + 1;
        $maxRetries = config('video_processing.limits.max_retries', 2);
        
        if ($newCount > $maxRetries) {
            return false;
        }
        
        $this->update(['retry_count' => $newCount]);
        return true;
    }

    /**
     * Check if job can retry
     */
    public function canRetry(): bool
    {
        $maxRetries = config('video_processing.limits.max_retries', 2);
        return $this->retry_count < $maxRetries;
    }

    /**
     * Get progress percentage based on steps
     */
    public function getProgressPercentage(): int
    {
        $steps = [
            'downloading' => 20,
            'uploading_audio' => 40,
            'transcribing' => 70,
            'saving_transcription' => 90,
            'integrating_rag' => 100,
        ];
        
        $log = $this->processing_log ?? [];
        $completedSteps = [];
        
        foreach ($log as $entry) {
            if (isset($entry['context']['step'])) {
                $completedSteps[] = $entry['context']['step'];
            }
        }
        
        $lastStep = end($completedSteps);
        return $steps[$lastStep] ?? 0;
    }

    /**
     * Refresh signed URLs if expired
     */
    public function refreshSignedUrls(): void
    {
        if ($this->urls_expire_at && $this->urls_expire_at->isPast()) {
            // Regenerate signed URLs logic here
            $this->addLog('Signed URLs refreshed');
        }
    }

    /**
     * Scope for tenant
     */
    public function scopeForTenant(Builder $query, string $tenantSlug): Builder
    {
        return $query->where('tenant_slug', $tenantSlug);
    }

    /**
     * Scope for pending jobs
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for stale jobs (stuck in processing > 1 hour)
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query->where('status', 'processing')
                    ->where('started_at', '<', now()->subHour());
    }
}

