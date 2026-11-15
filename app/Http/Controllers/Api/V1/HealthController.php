<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController extends Controller
{
    /**
     * Check API service health and database connectivity.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $dbStatus = 'error';
        }

        $isHealthy = $dbStatus === 'ok';

        $payload = [
            'status' => $isHealthy ? 'ok' : 'degraded',
            'services' => [
                'database' => $dbStatus,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        if (! $isHealthy) {
            return ApiResponse::success(
                data: $payload,
                status: 503
            );
        }

        return ApiResponse::success(
            data: $payload,
            status: 200
        );
    }
}
