<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'base' => [
            'driver' => 'local',
            'root' => base_path(),
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'throw' => false,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'throw' => false,
        ],

        'debian_docker' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => public_path('storage'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'permissions' => [
                'file' => [
                    'public' => 0664,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'visibility' => 'public',
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION'),
            'bucket' => env('R2_BUCKET'),
            'url' => env('R2_URL'),
            'visibility' => 'private',
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'your-project-id'),
            'key_file' => env('GOOGLE_CLOUD_KEY_FILE', null), // optional: /path/to/service-account.json
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'your-bucket'),
            'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', null), // optional: /default/path/to/apply/in/bucket
            'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI', null), // see: Public URLs below
            'visibility' => 'public', // optional: public|private
        ],

        's3p' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_IDP'),
            'secret' => env('AWS_SECRET_ACCESS_KEYP'),
            'region' => env('AWS_DEFAULT_REGIONP'),
            'bucket' => env('AWS_BUCKETP'),
            'url' => env('AWS_URLP'),
            'visibility' => 'private',
            'endpoint' => env('AWS_ENDPOINTP'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINTP', false),
            'throw' => false,
        ],

        'backup' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID_BACKUP'),
            'secret' => env('R2_SECRET_ACCESS_KEY_BACKUP'),
            'region' => env('R2_DEFAULT_REGION_BACKUP','auto'),
            'bucket' => env('R2_BUCKET_BACKUP','auto'),
            'url' => env('R2_URL_BACKUP'),
            'visibility' => 'private',
            'endpoint' => env('R2_ENDPOINT_BACKUP'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT_BACKUP', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
