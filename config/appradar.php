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

    'queue' => [
        'activity_window_seconds' => 900,
        'problem_window_seconds' => 3600,
        'problem_threshold' => 3,
        'timeout_threshold' => 2,
        'max_problem_jobs' => 5,
        'incident_retention_hours' => 24,
    ],
];
