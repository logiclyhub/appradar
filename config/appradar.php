<?php

return [
    'route' => [
        'path' => 'local/status',
        'middleware' => ['web'],
        'name' => 'local.status',
        'tests_name' => 'local.status.tests.run',
    ],

    'only_local' => true,

    'storage_path' => 'app/status',

    'scheduler' => [
        'heartbeat_name' => 'appradar-heartbeat',
    ],
];
