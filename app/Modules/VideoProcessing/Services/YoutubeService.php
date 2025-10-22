<?php

namespace App\Modules\VideoProcessing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoutubeService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('video_processing.youtube.api_key');
    }

    /**
     * Extract video ID from various YouTube URL formats
     */
    public function extractVideoId(string $url): ?string
    {
        $patterns = [
            '/^(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/',
            '/^(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Get video metadata from YouTube Data API v3
     */
    public function getMetadata(string $videoId): array
    {
        try {
            $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'key' => $this->apiKey,
                'id' => $videoId,
                'part' => 'snippet,contentDetails',
            ]);

            if (!$response->successful()) {
                throw new \Exception('YouTube API request failed: ' . $response->body());
            }

            $data = $response->json();
            
            if (empty($data['items'])) {
                throw new \Exception('Video not found or private');
            }

            $video = $data['items'][0];
            $snippet = $video['snippet'];
            $contentDetails = $video['contentDetails'];

            return [
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'duration_seconds' => $this->parseDuration($contentDetails['duration']),
                'channel_name' => $snippet['channelTitle'],
                'published_at' => $snippet['publishedAt'],
                'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('YouTube metadata fetch failed', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Validate test mode restrictions
     */
    public function validateTestMode(string $videoId, int $duration): bool
    {
        if (!config('video_processing.test_mode', true)) {
            return true;
        }

        $whitelist = config('video_processing.limits.test_mode_whitelist', []);
        $maxDuration = config('video_processing.limits.test_mode_duration', 180);

        // Check whitelist
        if (in_array($videoId, $whitelist)) {
            return true;
        }

        // Check duration limit
        return $duration <= $maxDuration;
    }

    /**
     * Validate YouTube URL format
     */
    public function isValidYoutubeUrl(string $url): bool
    {
        $videoId = $this->extractVideoId($url);
        return $videoId !== null;
    }

    /**
     * Parse ISO 8601 duration to seconds
     */
    private function parseDuration(string $duration): int
    {
        $interval = new \DateInterval($duration);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }
}

