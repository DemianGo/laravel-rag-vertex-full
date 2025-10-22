<?php

namespace App\Modules\VideoProcessing\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VideoJobStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'job_id' => $this->job_id,
            'status' => $this->status,
            'video_info' => [
                'title' => $this->title,
                'duration_seconds' => $this->duration_seconds,
                'channel' => $this->channel_name,
            ],
        ];

        if ($this->status === 'processing') {
            $data['progress'] = [
                'current_step' => $this->getCurrentStep(),
                'steps_completed' => $this->getCompletedSteps(),
                'percentage' => $this->getProgressPercentage(),
            ];
        }

        if ($this->status === 'completed') {
            $data['downloads'] = [
                'audio_url' => $this->audio_url,
                'transcription_url' => $this->transcription_url,
                'expires_at' => $this->urls_expire_at?->toISOString(),
            ];
            $data['rag_document_id'] = $this->rag_document_id;
            $data['completed_at'] = $this->completed_at?->toISOString();
        }

        if ($this->status === 'failed') {
            $data['error_message'] = $this->error_message;
        }

        return $data;
    }

    private function getCurrentStep(): string
    {
        $log = $this->processing_log ?? [];
        $lastEntry = end($log);
        
        if ($lastEntry && isset($lastEntry['context']['step'])) {
            return $lastEntry['context']['step'];
        }
        
        return 'initializing';
    }

    private function getCompletedSteps(): array
    {
        $log = $this->processing_log ?? [];
        $completedSteps = [];
        
        foreach ($log as $entry) {
            if (isset($entry['context']['step'])) {
                $completedSteps[] = $entry['context']['step'];
            }
        }
        
        return array_unique($completedSteps);
    }

    private function getProgressPercentage(): int
    {
        return $this->getProgressPercentage();
    }
}

