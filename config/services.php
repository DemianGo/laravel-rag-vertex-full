<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // -------- Vertex AI (custom) --------
    'vertex' => [
        'project'          => env('VERTEX_PROJECT', env('GOOGLE_CLOUD_PROJECT')),
        'location'         => env('VERTEX_LOCATION', 'us-central1'),
        'embedding_model'  => env('VERTEX_EMBEDDING_MODEL', 'text-embedding-004'),
        'generation_model' => env('VERTEX_GENERATION_MODEL', 'gemini-2.5-flash'),
    ],

    // -------- Mercado Pago --------
    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'sandbox' => env('MERCADOPAGO_SANDBOX', true),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

];
