<?php

namespace App\Modules\VideoProcessing\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VideoJobResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'job_id' => $this->job_id,
            'status' => $this->status,
            'message' => $this->getStatusMessage(),
            'poll_url' => route('video-processing.status', $this->job_id),
            'video_info' => [
                'title' => $this->title,
                'duration_seconds' => $this->duration_seconds,
                'channel' => $this->channel_name,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function getStatusMessage(): string
    {
        return match($this->status) {
            'pending' => 'Video processing job created successfully',
            'processing' => 'Video is being processed',
            'completed' => 'Video processing completed successfully',
            'failed' => 'Video processing failed',
            default => 'Unknown status',
        };
    }
}

