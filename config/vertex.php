<?php
return [
    'project_id' => env('GCP_PROJECT_ID', 'liberai-ai'),

    // DEFAULTS "CONGELADOS" — não somem após reboot
    'location'   => env('GCP_LOCATION', 'global'),
    'model'      => env('GCP_VERTEX_MODEL', 'gemini-2.5-flash'),

    // Embeddings (RAG)
    'embedding' => [
        'location' => env('GCP_EMBEDDING_LOCATION', 'us-central1'),
        'model'    => env('GCP_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'dim'      => (int) env('GCP_EMBEDDING_DIM', 768),
    ],
];
