<?php

use Illuminate\Support\Facades\Route;
use AppRadar\Agent\Laravel\Http\Controllers\LocalStatusController;
use AppRadar\Agent\Laravel\Http\Middleware\EnsureAppRadarStatusToken;

$middleware = array_values(array_filter([
    ...((array) config('appradar.route.middleware', ['web'])),
    EnsureAppRadarStatusToken::class,
]));

Route::middleware($middleware)->group(function (): void {
    $path = trim((string) config('appradar.route.path', 'local/status'), '/');

    Route::get('/'.$path, LocalStatusController::class)
        ->name((string) config('appradar.route.name', 'local.status'));

    Route::post('/'.$path.'/tests/run', [LocalStatusController::class, 'runTests'])
        ->name((string) config('appradar.route.tests_name', 'local.status.tests.run'));
});
