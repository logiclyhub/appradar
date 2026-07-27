<?php

return [
    'route' => [
        'path' => 'status',
        'middleware' => ['web'],
        'name' => 'appradar.status',
        'tests_name' => 'appradar.status.tests.run',
    ],

    'only_local' => false,

    'storage_path' => 'app/status',

    'scheduler' => [
        'heartbeat_name' => 'appradar-heartbeat',
    ],
];
