<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Support\SupportTicketDetailResource;
use App\Http\Resources\Api\V1\Support\SupportTicketListResource;
use App\Http\Resources\Api\V1\Support\SupportTicketMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\SupportTicket;
use App\Notifications\SupportTicketReplyNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminSupportTicketController extends Controller
{
    /**
     * List all support tickets for administration.
     *
     * Per Handoff §4.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(['latestMessage', 'customer'])
            ->withCount('messages');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        // Search reference, subject, or customer email
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $sub) use ($search): void {
                        $sub->where('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        $query->latest('updated_at');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $paginator = $query->paginate($perPage);

        $transformed = $paginator->through(
            fn (SupportTicket $ticket): array => (new SupportTicketListResource($ticket))->resolve($request)
        );

        return ApiResponse::paginated($transformed);
    }

    /**
     * View detailed ticket thread for administration.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var SupportTicket|null $ticket */
        $ticket = SupportTicket::where('ulid', $id)
            ->orWhere('reference', $id)
            ->first();

        if (! $ticket) {
            return ApiResponse::error(
                message: 'Support ticket not found.',
                code: 'ticket_not_found',
                status: 404
            );
        }

        $ticket->load(['messages' => fn ($q) => $q->oldest('id'), 'customer'])
            ->loadCount('messages');

        return ApiResponse::success(new SupportTicketDetailResource($ticket));
    }

    /**
     * Update ticket status.
     *
     * Per Handoff §4.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
        ]);

        /** @var SupportTicket|null $ticket */
        $ticket = SupportTicket::where('ulid', $id)
            ->orWhere('reference', $id)
            ->first();

        if (! $ticket) {
            return ApiResponse::error(
                message: 'Support ticket not found.',
                code: 'ticket_not_found',
                status: 404
            );
        }

        $ticket->status = TicketStatus::from($validated['status']);
        $ticket->save();

        $ticket->load(['latestMessage'])->loadCount('messages');

        return ApiResponse::success(new SupportTicketListResource($ticket));
    }

    /**
     * Send admin reply to customer ticket.
     *
     * Per Handoff §4:
     * - Creates admin message.
     * - Sets has_unread_reply = true on ticket.
     * - Dispatches notification to customer.
     */
    public function reply(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        /** @var SupportTicket|null $ticket */
        $ticket = SupportTicket::where('ulid', $id)
            ->orWhere('reference', $id)
            ->first();

        if (! $ticket) {
            return ApiResponse::error(
                message: 'Support ticket not found.',
                code: 'ticket_not_found',
                status: 404
            );
        }

        $admin = $request->user();
        $adminName = $admin && isset($admin->name) ? (string) $admin->name : 'Support Team';

        $message = $ticket->messages()->create([
            'author_type' => 'admin',
            'author_id' => $admin?->getAuthIdentifier(),
            'author_name' => $adminName,
            'body' => $validated['message'],
        ]);

        $ticket->update([
            'has_unread_reply' => true,
            'status' => $ticket->status === TicketStatus::OPEN ? TicketStatus::IN_PROGRESS : $ticket->status,
        ]);

        // Dispatch queued notification to the customer
        $customer = $ticket->customer;
        $customer->notify(new SupportTicketReplyNotification(
            ticket: $ticket,
            replySnippet: Str::limit($validated['message'], 100)
        ));

        return ApiResponse::success(
            data: new SupportTicketMessageResource($message),
            status: 201
        );
    }
}
