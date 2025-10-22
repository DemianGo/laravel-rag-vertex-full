<?php

return [
    'enabled' => env('VIDEO_PROCESSING_ENABLED', false),
    'test_mode' => env('VIDEO_PROCESSING_TEST_MODE', true),
    
    'storage' => [
        'disk' => env('VIDEO_PROCESSING_DISK', 'gcs_videos'),
        'bucket' => env('VIDEO_PROCESSING_BUCKET', 'rag-videos-test'),
        'audio_folder' => 'audio',
        'transcription_folder' => 'transcriptions',
    ],
    
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
        'python_script' => base_path('scripts/youtube_processor.py'),
        'download_timeout' => 600, // 10 minutes
    ],
    
    'vertex_ai' => [
        'project_id' => env('GOOGLE_CLOUD_PROJECT'),
        'location' => env('VERTEX_AI_LOCATION', 'us-central1'),
        'language_code' => 'pt-BR',
        'model' => 'latest_long',
        'timeout' => 1800, // 30 minutes
        'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],
    
    'limits' => [
        'test_mode_duration' => 180, // 3 minutes
        'test_mode_whitelist' => [
            'dQw4w9WgXcQ', // Example test video IDs
            'test_video_1',
        ],
        'rate_limit_per_minute' => 5,
        'job_timeout' => 1800, // 30 minutes
        'max_retries' => 2,
        'retry_delay' => 60, // seconds
    ],
    
    'queue' => [
        'name' => 'video_processing',
        'connection' => env('VIDEO_PROCESSING_QUEUE', 'database'),
    ],
    
    'signed_url_ttl' => 7 * 24 * 60 * 60, // 7 days in seconds
];

