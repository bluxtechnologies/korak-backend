<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\WebsiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    /**
     * Public web page for viewing a service or its unavailable state.
     * When deactivated, renders the clean "Service Unavailable" page showing nothing else.
     */
    public function show(string $code, Request $request): View|JsonResponse
    {
        $code = strtolower(trim($code));
        $service = WebsiteService::findByCode($code);

        if (! $service) {
            abort(404, 'Service not found.');
        }

        if (! $service->is_active) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    message: $service->maintenance_message ?: "{$service->name} is currently unavailable.",
                    code: 'service_unavailable',
                    status: 503,
                    errors: [
                        'service' => $service->code,
                        'name' => $service->name,
                        'is_active' => false,
                        'unavailable_headline' => $service->unavailable_headline,
                        'maintenance_message' => $service->maintenance_message,
                    ]
                );
            }

            return view('services.unavailable', [
                'service' => $service,
                'isPreview' => false,
            ]);
        }

        if ($request->expectsJson()) {
            return ApiResponse::success([
                'service' => $service->code,
                'name' => $service->name,
                'category' => $service->category,
                'is_active' => true,
                'description' => $service->description,
                'icon' => $service->icon,
            ]);
        }

        // When active and accessed via web browser, render service page or redirect
        return view('services.show', [
            'service' => $service,
        ]);
    }

    /**
     * Public API: List all website services with active/inactive status and maintenance notices.
     * GET /api/v1/services
     */
    public function apiIndex(): JsonResponse
    {
        $services = WebsiteService::allCached()->map(function (WebsiteService $s): array {
            return [
                'name' => $s->name,
                'code' => $s->code,
                'category' => $s->category,
                'description' => $s->description,
                'icon' => $s->icon,
                'is_active' => (bool) $s->is_active,
                'unavailable_headline' => $s->unavailable_headline,
                'maintenance_message' => $s->maintenance_message,
            ];
        });

        return ApiResponse::success($services);
    }

    /**
     * Public API: Retrieve status for a single service.
     * GET /api/v1/services/{code}
     */
    public function apiShow(string $code): JsonResponse
    {
        $code = strtolower(trim($code));
        $service = WebsiteService::findByCode($code);

        if (! $service) {
            return ApiResponse::error(
                message: 'Service not found.',
                code: 'service_not_found',
                status: 404
            );
        }

        $payload = [
            'name' => $service->name,
            'code' => $service->code,
            'category' => $service->category,
            'description' => $service->description,
            'icon' => $service->icon,
            'is_active' => (bool) $service->is_active,
            'unavailable_headline' => $service->unavailable_headline,
            'maintenance_message' => $service->maintenance_message,
        ];

        if (! $service->is_active) {
            return ApiResponse::error(
                message: $service->maintenance_message ?: "{$service->name} is currently unavailable.",
                code: 'service_unavailable',
                status: 503,
                errors: [
                    'service' => [$service->code],
                    'unavailable_headline' => [$service->unavailable_headline],
                ]
            );
        }

        return ApiResponse::success($payload);
    }
}
