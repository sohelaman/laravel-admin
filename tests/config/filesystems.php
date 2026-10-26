<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Only local disks are used by the test suite.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL', 'http://localhost').'/storage',
            'visibility' => 'public',
        ],

        'admin' => [
            'driver'     => 'local',
            'root'       => public_path('uploads'),
            'visibility' => 'public',
            'url'        => 'http://localhost:8000/uploads/',
        ],

    ],

];
