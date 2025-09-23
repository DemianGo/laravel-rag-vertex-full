<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RAG Enterprise Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the Enterprise RAG
    | (Retrieval-Augmented Generation) system including caching, metrics,
    | background processing, and performance optimizations.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | General Settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('RAG_ENABLED', true),
    'tenant_isolation' => env('RAG_TENANT_ISOLATION', true),
    'debug_mode' => env('RAG_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Document Processing
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'max_size' => env('RAG_DOC_MAX_SIZE', 50 * 1024 * 1024), // 50MB
        'allowed_types' => ['pdf', 'txt', 'docx', 'doc', 'rtf', 'md'],
        'chunk_size' => env('RAG_CHUNK_SIZE', 1000),
        'chunk_overlap' => env('RAG_CHUNK_OVERLAP', 200),
        'min_chunk_length' => env('RAG_MIN_CHUNK_LENGTH', 100),
        'max_chunks_per_doc' => env('RAG_MAX_CHUNKS_PER_DOC', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    */
    'embeddings' => [
        'provider' => env('RAG_EMBEDDING_PROVIDER', 'vertex'),
        'model' => env('RAG_EMBEDDING_MODEL', 'textembedding-gecko@003'),
        'dimensions' => env('RAG_EMBEDDING_DIMENSIONS', 768),
        'batch_size' => env('RAG_EMBEDDING_BATCH_SIZE', 20),
        'max_retries' => env('RAG_EMBEDDING_MAX_RETRIES', 3),
        'retry_delay' => env('RAG_EMBEDDING_RETRY_DELAY', 1000), // milliseconds
        'timeout' => env('RAG_EMBEDDING_TIMEOUT', 30), // seconds
        'rate_limit' => [
            'requests_per_minute' => env('RAG_EMBEDDING_RPM', 300),
            'tokens_per_minute' => env('RAG_EMBEDDING_TPM', 1000000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Search Configuration
    |--------------------------------------------------------------------------
    */
    'search' => [
        'default_top_k' => env('RAG_DEFAULT_TOP_K', 10),
        'max_top_k' => env('RAG_MAX_TOP_K', 100),
        'similarity_threshold' => env('RAG_SIMILARITY_THRESHOLD', 0.7),
        'enable_reranking' => env('RAG_ENABLE_RERANKING', true),
        'reranking_top_k' => env('RAG_RERANKING_TOP_K', 50),
        'hybrid_search' => [
            'enabled' => env('RAG_HYBRID_SEARCH', true),
            'vector_weight' => env('RAG_VECTOR_WEIGHT', 0.8),
            'text_weight' => env('RAG_TEXT_WEIGHT', 0.2),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Configuration
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'provider' => env('RAG_GENERATION_PROVIDER', 'vertex'),
        'model' => env('RAG_GENERATION_MODEL', 'gemini-1.5-flash'),
        'max_tokens' => env('RAG_MAX_TOKENS', 2048),
        'temperature' => env('RAG_TEMPERATURE', 0.3),
        'top_p' => env('RAG_TOP_P', 0.8),
        'max_context_chunks' => env('RAG_MAX_CONTEXT_CHUNKS', 10),
        'max_context_tokens' => env('RAG_MAX_CONTEXT_TOKENS', 8000),
        'system_prompt' => env('RAG_SYSTEM_PROMPT', 'You are a helpful AI assistant that answers questions based on the provided context. Always cite your sources and be concise.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('RAG_CACHE_ENABLED', true),
        'store' => env('RAG_CACHE_STORE', 'redis'),
        'prefix' => env('RAG_CACHE_PREFIX', 'rag'),
        'ttl' => [
            'embeddings' => env('RAG_CACHE_EMBEDDINGS_TTL', 86400 * 7), // 7 days
            'search_results' => env('RAG_CACHE_SEARCH_TTL', 3600), // 1 hour
            'generated_answers' => env('RAG_CACHE_ANSWERS_TTL', 3600 * 6), // 6 hours
            'document_metadata' => env('RAG_CACHE_METADATA_TTL', 86400 * 30), // 30 days
        ],
        'compression' => [
            'enabled' => env('RAG_CACHE_COMPRESSION', true),
            'algorithm' => env('RAG_CACHE_COMPRESSION_ALGO', 'gzip'),
            'level' => env('RAG_CACHE_COMPRESSION_LEVEL', 6),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Background Processing
    |--------------------------------------------------------------------------
    */
    'jobs' => [
        'queue' => env('RAG_QUEUE', 'rag'),
        'connection' => env('RAG_QUEUE_CONNECTION', 'redis'),
        'batch_processing' => [
            'enabled' => env('RAG_BATCH_PROCESSING', true),
            'chunk_size' => env('RAG_BATCH_CHUNK_SIZE', 100),
            'max_concurrent' => env('RAG_BATCH_MAX_CONCURRENT', 5),
        ],
        'retry' => [
            'max_attempts' => env('RAG_JOB_MAX_ATTEMPTS', 3),
            'backoff_strategy' => env('RAG_JOB_BACKOFF', 'exponential'),
            'base_delay' => env('RAG_JOB_BASE_DELAY', 5), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics and Monitoring
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'enabled' => env('RAG_METRICS_ENABLED', true),
        'store' => env('RAG_METRICS_STORE', 'database'),
        'retention_days' => env('RAG_METRICS_RETENTION', 90),
        'sampling_rate' => env('RAG_METRICS_SAMPLING', 1.0),
        'track' => [
            'queries' => env('RAG_TRACK_QUERIES', true),
            'embeddings' => env('RAG_TRACK_EMBEDDINGS', true),
            'generation' => env('RAG_TRACK_GENERATION', true),
            'performance' => env('RAG_TRACK_PERFORMANCE', true),
            'errors' => env('RAG_TRACK_ERRORS', true),
        ],
        'alerts' => [
            'enabled' => env('RAG_ALERTS_ENABLED', false),
            'channels' => ['slack', 'email'],
            'thresholds' => [
                'error_rate' => env('RAG_ALERT_ERROR_RATE', 0.05), // 5%
                'latency_p95' => env('RAG_ALERT_LATENCY_P95', 5000), // 5 seconds
                'queue_length' => env('RAG_ALERT_QUEUE_LENGTH', 1000),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security and Rate Limiting
    |--------------------------------------------------------------------------
    */
    'security' => [
        'rate_limiting' => [
            'enabled' => env('RAG_RATE_LIMITING', true),
            'requests_per_minute' => env('RAG_RPM_LIMIT', 60),
            'requests_per_hour' => env('RAG_RPH_LIMIT', 1000),
            'burst_limit' => env('RAG_BURST_LIMIT', 10),
        ],
        'content_filtering' => [
            'enabled' => env('RAG_CONTENT_FILTER', true),
            'max_query_length' => env('RAG_MAX_QUERY_LENGTH', 2000),
            'blocked_patterns' => [],
        ],
        'tenant_isolation' => [
            'strict_mode' => env('RAG_STRICT_TENANT_ISOLATION', true),
            'default_tenant' => env('RAG_DEFAULT_TENANT', 'default'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'connection_pooling' => [
            'enabled' => env('RAG_CONNECTION_POOLING', true),
            'max_connections' => env('RAG_MAX_CONNECTIONS', 50),
            'idle_timeout' => env('RAG_IDLE_TIMEOUT', 300), // seconds
        ],
        'query_optimization' => [
            'use_prepared_statements' => env('RAG_USE_PREPARED_STATEMENTS', true),
            'enable_query_cache' => env('RAG_QUERY_CACHE', true),
            'index_hints' => env('RAG_INDEX_HINTS', true),
        ],
        'memory_management' => [
            'max_memory_per_request' => env('RAG_MAX_MEMORY', '512M'),
            'gc_probability' => env('RAG_GC_PROBABILITY', 1),
            'gc_divisor' => env('RAG_GC_DIVISOR', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Experimental Features
    |--------------------------------------------------------------------------
    */
    'experimental' => [
        'multi_modal' => env('RAG_MULTI_MODAL', false),
        'graph_rag' => env('RAG_GRAPH_MODE', false),
        'adaptive_chunking' => env('RAG_ADAPTIVE_CHUNKING', false),
        'auto_optimization' => env('RAG_AUTO_OPTIMIZATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'level' => env('RAG_LOG_LEVEL', 'info'),
        'channels' => [
            'single' => env('RAG_LOG_SINGLE', true),
            'daily' => env('RAG_LOG_DAILY', false),
            'slack' => env('RAG_LOG_SLACK', false),
        ],
        'structured_logging' => env('RAG_STRUCTURED_LOGGING', true),
        'include_stack_trace' => env('RAG_LOG_STACK_TRACE', false),
    ],
];