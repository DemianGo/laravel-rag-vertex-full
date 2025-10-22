<?php

namespace App\Modules\VideoProcessing\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\VideoProcessing\Services\YoutubeService;
use App\Modules\VideoProcessing\Models\VideoProcessingQuota;

class ProcessVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return config('video_processing.enabled', false);
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                function ($attribute, $value, $fail) {
                    // Simple YouTube URL validation
                    if (!preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $value)) {
                        $fail('The URL must be a valid YouTube video URL.');
                    }
                },
            ],
            'tenant_slug' => 'required|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $url = $this->input('url');
            $tenantSlug = $this->input('tenant_slug');
            
            if (!$url || !$tenantSlug) {
                return;
            }
            
            // Extract video ID
            preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches);
            $videoId = $matches[1] ?? null;
            
            if (!$videoId) {
                $validator->errors()->add('url', 'Could not extract video ID from URL.');
                return;
            }
            
            // Check quota (simplified)
            $quota = VideoProcessingQuota::firstOrCreate(
                ['tenant_slug' => $tenantSlug],
                ['last_reset_date' => now()->toDateString()]
            );
            
            if (!$quota->canProcess(0)) { // Skip duration check for now
                if ($quota->used_today >= $quota->daily_limit) {
                    $validator->errors()->add('quota', 'Daily processing limit exceeded.');
                } elseif ($quota->used_this_month >= $quota->monthly_limit) {
                    $validator->errors()->add('quota', 'Monthly processing limit exceeded.');
                }
                return;
            }
        });
    }

    public function messages(): array
    {
        return [
            'url.required' => 'YouTube video URL is required.',
            'url.url' => 'Please provide a valid URL.',
            'tenant_slug.required' => 'Tenant slug is required.',
        ];
    }
}

