<?php

namespace App\Http\Controllers;

use App\Support\Health\Check;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    /** Liveness: the process is up and can serve a request. */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /** Readiness: every configured dependency is usable. Failures never leak error details. */
    public function ready(): JsonResponse
    {
        /** @var array<string, class-string<Check>> $checks */
        $checks = config('health.checks');

        $results = [];
        foreach ($checks as $name => $class) {
            $results[$name] = app($class)->passes() ? 'ok' : 'fail';
        }

        $healthy = ! in_array('fail', $results, true);

        return response()->json(
            ['status' => $healthy ? 'ok' : 'fail', 'checks' => $results],
            $healthy ? 200 : 503,
        );
    }
}
