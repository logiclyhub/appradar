<?php

namespace AppRadar\Agent\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use AppRadar\Agent\Core\AdapterFactory;
use AppRadar\Agent\Core\ProjectTypeDetector;

class LocalStatusController
{
    public function __invoke(
        ProjectTypeDetector $detector,
        AdapterFactory $factory,
    ): JsonResponse {
        $this->abortUnlessAllowed();

        return response()->json(
            $factory->make($detector->detect())->statusPayload()
        );
    }

    public function runTests(
        Request $request,
        ProjectTypeDetector $detector,
        AdapterFactory $factory,
    ): JsonResponse {
        $this->abortUnlessAllowed();

        return response()->json(
            $factory->make($detector->detect())->runTests((int) $request->integer('timeout', 600))
        );
    }

    private function abortUnlessAllowed(): void
    {
        if ((bool) config('appradar.only_local', true)) {
            abort_unless(app()->environment('local'), 404);
        }
    }
}
