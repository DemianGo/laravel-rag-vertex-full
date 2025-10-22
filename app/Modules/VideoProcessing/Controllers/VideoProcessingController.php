<?php

namespace App\Modules\VideoProcessing\Controllers;

use App\Modules\VideoProcessing\Requests\ProcessVideoRequest;
use App\Modules\VideoProcessing\Resources\VideoJobResource;
use App\Modules\VideoProcessing\Resources\VideoJobStatusResource;
use App\Modules\VideoProcessing\Models\VideoProcessingJob;
use App\Modules\VideoProcessing\Models\VideoProcessingQuota;
use App\Modules\VideoProcessing\Services\YoutubeService;
use App\Modules\VideoProcessing\Jobs\ProcessVideoJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class VideoProcessingController
{
    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Video Processing Module is working',
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Video processing endpoint is working',
            'timestamp' => now()->toISOString()
        ]);
    }

    public function status(Request $request, string $jobId): JsonResponse
    {
        $job = VideoProcessingJob::where('job_id', $jobId)->first();
        
        if (!$job) {
            return response()->json([
                'error' => 'Job not found',
            ], 404);
        }
        
        return response()->json(
            new VideoJobStatusResource($job)
        );
    }

    public function list(Request $request): JsonResponse
    {
        $tenantSlug = $request->get('tenant_slug');
        
        if (!$tenantSlug) {
            return response()->json([
                'error' => 'Tenant slug is required',
            ], 400);
        }
        
        $jobs = VideoProcessingJob::forTenant($tenantSlug)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        
        return response()->json([
            'jobs' => VideoJobStatusResource::collection($jobs),
        ]);
    }

    public function quota(Request $request): JsonResponse
    {
        $tenantSlug = $request->get('tenant_slug');
        
        if (!$tenantSlug) {
            return response()->json([
                'error' => 'Tenant slug is required',
            ], 400);
        }
        
        $quota = VideoProcessingQuota::firstOrCreate(
            ['tenant_slug' => $tenantSlug],
            ['last_reset_date' => now()->toDateString()]
        );
        
        return response()->json([
            'quota' => [
                'daily_limit' => $quota->daily_limit,
                'monthly_limit' => $quota->monthly_limit,
                'max_duration_seconds' => $quota->max_duration_seconds,
                'used_today' => $quota->used_today,
                'used_this_month' => $quota->used_this_month,
                'remaining_daily' => $quota->getRemainingDaily(),
                'remaining_monthly' => $quota->getRemainingMonthly(),
            ],
        ]);
    }
}

