<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */
    'default' => env('FILESYSTEM_DISK', 'public'), // Changé de 'local' à 'public' par défaut

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'private' => [ // Renommé de 'local' à 'private' pour plus de clarté
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => true, // Active les exceptions pour mieux déboguer
            'visibility' => 'private' // Fichiers non accessibles publiquement
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => true, // Active les exceptions
            'permissions' => [
                'file' => [
                    'public' => 0664, // Permissions plus sécurisées
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775, // Permissions plus sécurisées
                    'private' => 0700,
                ],
            ]
        ],

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
            'visibility' => 'private' // Par défaut privé sur S3
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */
    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('private') => storage_path('app/private'), // Optionnel pour accès contrôlé
    ],
];