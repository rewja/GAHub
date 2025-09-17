<?php

return [
    // Default filesystem disk
    'default' => env('FILESYSTEM_DISK', 'local'),

    // Filesystem Disks
    'disks' => [
        // Local private storage (default)
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => true,
        ],

        // Public storage for general public files
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => true,
        ],

        // Dedicated disk for evidence uploads
        'evidence' => [
            'driver' => 'local',
            'root' => storage_path('app/public/evidence'),
            'url' => env('APP_URL').'/storage/evidence',
            'visibility' => 'public',
            'throw' => true,
        ],

        // Optional: Cloud storage configuration (if needed)
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
        ],
    ],

    // Symbolic links configuration
    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('evidence') => storage_path('app/public/evidence'),
    ],

    // Maximum file upload sizes
    'max_upload_size' => [
        'evidence' => 10 * 1024 * 1024, // 10MB
        'default' => 5 * 1024 * 1024,   // 5MB default
    ],

    // Allowed file types
    'allowed_mime_types' => [
        'evidence' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/tiff'
        ],
        'default' => [
            'application/pdf',
            'image/jpeg',
            'image/png'
        ]
    ],

    // Security configurations for file uploads
    'security' => [
        'evidence' => [
            'max_filename_length' => 255,
            'allowed_characters' => '/^[a-zA-Z0-9_\-\.]+$/',
            'prevent_overwrite' => true,
            'unique_prefix' => true
        ],
        'default' => [
            'max_filename_length' => 200,
            'allowed_characters' => '/^[a-zA-Z0-9_\-\.]+$/',
            'prevent_overwrite' => false
        ]
    ]
];
