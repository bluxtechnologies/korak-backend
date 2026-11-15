<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Get paginated notifications for the authenticated customer.
     *
     * Per Doc 11 §5 & Roadmap B10.3.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $query = $customer->notifications();

        // Filter by read / unread status
        if ($request->has('unread')) {
            if ($request->boolean('unread')) {
                $query->whereNull('read_at');
            } else {
                $query->whereNotNull('read_at');
            }
        } elseif ($request->has('filter.read')) {
            $filterRead = $request->input('filter.read');
            if ($filterRead === 'false' || $filterRead === '0' || $filterRead === false) {
                $query->whereNull('read_at');
            } elseif ($filterRead === 'true' || $filterRead === '1' || $filterRead === true) {
                $query->whereNotNull('read_at');
            }
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $paginator = $query->latest()->paginate($perPage);

        $transformed = $paginator->through(
            fn (DatabaseNotification $n): array => (new NotificationResource($n))->resolve($request)
        );

        return ApiResponse::paginated($transformed);
    }

    /**
     * Get the unread notifications count for the authenticated customer.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $count = $customer->unreadNotifications()->count();

        return ApiResponse::success([
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a specific notification as read.
     *
     * Scoped strictly to the authenticated customer per Doc 11 §5.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var DatabaseNotification|null $notification */
        $notification = $customer->notifications()->where('id', $id)->first();

        if (! $notification) {
            return ApiResponse::error(
                'Notification not found.',
                'notification_not_found',
                404
            );
        }

        // If explicitly requested to mark unread:
        if ($request->has('read') && ! $request->boolean('read')) {
            $notification->markAsUnread();
        } else {
            $notification->markAsRead();
        }

        return ApiResponse::success(
            new NotificationResource($notification->fresh())
        );
    }

    /**
     * Mark all unread notifications as read for the authenticated customer.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $customer->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return ApiResponse::success([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
