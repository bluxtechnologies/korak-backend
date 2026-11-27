<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminServiceController extends Controller
{
    /**
     * Display a listing of website services.
     */
    public function index(): View
    {
        $services = WebsiteService::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $totalServices = $services->count();
        $activeServices = $services->where('is_active', true)->count();
        $inactiveServices = $services->where('is_active', false)->count();

        return view('admin.services.index', [
            'services' => $services,
            'totalServices' => $totalServices,
            'activeServices' => $activeServices,
            'inactiveServices' => $inactiveServices,
        ]);
    }

    /**
     * Store a newly created website service in storage.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:website_services,code'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
            'unavailable_headline' => ['nullable', 'string', 'max:150'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $code = strtolower(trim($validated['code']));

        $service = WebsiteService::create([
            'name' => trim($validated['name']),
            'code' => $code,
            'category' => strtolower(trim($validated['category'])),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'icon' => ! empty($validated['icon']) ? trim($validated['icon']) : 'ti ti-apps',
            'unavailable_headline' => ! empty($validated['unavailable_headline']) ? trim($validated['unavailable_headline']) : 'Service Unavailable',
            'maintenance_message' => ! empty($validated['maintenance_message']) ? trim($validated['maintenance_message']) : 'This service is currently unavailable. Please check back shortly.',
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $message = "Service '{$service->name}' was added successfully.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'service' => $service,
            ], 201);
        }

        return redirect()->route('admin.services.index')->with('success', $message);
    }

    /**
     * Toggle active/inactive state of a website service.
     */
    public function toggle(WebsiteService $service, Request $request): JsonResponse|RedirectResponse
    {
        $service->is_active = ! $service->is_active;
        $service->save();

        $statusLabel = $service->is_active ? 'activated' : 'deactivated';
        $message = "Service '{$service->name}' has been {$statusLabel}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'is_active' => $service->is_active,
                'message' => $message,
                'service' => $service,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Update the specified website service in storage.
     */
    public function update(Request $request, WebsiteService $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:100'],
            'unavailable_headline' => ['required', 'string', 'max:150'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $service->update([
            'name' => trim($validated['name']),
            'category' => strtolower(trim($validated['category'])),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'icon' => ! empty($validated['icon']) ? trim($validated['icon']) : $service->icon,
            'unavailable_headline' => trim($validated['unavailable_headline']),
            'maintenance_message' => isset($validated['maintenance_message']) ? trim($validated['maintenance_message']) : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $service->is_active,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : $service->sort_order,
        ]);

        $message = "Service '{$service->name}' settings updated successfully.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'service' => $service,
            ]);
        }

        return redirect()->route('admin.services.index')->with('success', $message);
    }

    /**
     * Preview the public "Service Unavailable" screen for this service.
     */
    public function preview(WebsiteService $service): View
    {
        return view('services.unavailable', [
            'service' => $service,
            'isPreview' => true,
        ]);
    }
}
