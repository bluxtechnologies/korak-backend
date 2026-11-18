<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Support\CreateTicketRequest;
use App\Http\Requests\Api\V1\Support\ReplyTicketRequest;
use App\Http\Resources\Api\V1\Support\SupportTicketDetailResource;
use App\Http\Resources\Api\V1\Support\SupportTicketListResource;
use App\Http\Resources\Api\V1\Support\SupportTicketMessageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends Controller
{
    /**
     * List tickets belonging to the authenticated customer.
     *
     * Per Handoff §3.1 & Doc 06 standards.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        $query = $customer->supportTickets()
            ->with(['latestMessage'])
            ->withCount('messages');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        } elseif ($request->filled('filter.category')) {
            $query->where('category', $request->input('filter.category'));
        }

        // Search by reference or subject
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Sorting: default newest updated first
        $query->latest('updated_at');

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $paginator = $query->paginate($perPage);

        $transformed = $paginator->through(
            fn (SupportTicket $ticket): array => (new SupportTicketListResource($ticket))->resolve($request)
        );

        return ApiResponse::paginated($transformed);
    }

    /**
     * Create a new support ticket with initial message.
     *
     * Per Handoff §3.2.
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var string $categoryValue */
        $categoryValue = $request->input('category');
        $category = TicketCategory::from($categoryValue);

        $ticket = DB::transaction(function () use ($customer, $request, $category): SupportTicket {
            $ticket = SupportTicket::create([
                'customer_id' => $customer->id,
                'subject' => (string) $request->input('subject'),
                'category' => $category,
                'status' => TicketStatus::OPEN,
                'has_unread_reply' => false,
            ]);

            $ticket->messages()->create([
                'author_type' => 'customer',
                'author_id' => $customer->id,
                'author_name' => $customer->username,
                'body' => (string) $request->input('message'),
            ]);

            return $ticket;
        });

        $ticket->load(['latestMessage'])->loadCount('messages');

        return ApiResponse::success(
            data: new SupportTicketListResource($ticket),
            status: 201
        );
    }

    /**
     * Get ticket details with full message thread and mark admin replies as read.
     *
     * Per Handoff §3.3.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var SupportTicket|null $ticket */
        $ticket = $customer->supportTickets()
            ->where(function (Builder $q) use ($id): void {
                $q->where('ulid', $id)
                    ->orWhere('reference', $id);
            })
            ->first();

        if (! $ticket) {
            return ApiResponse::error(
                message: 'Support ticket not found.',
                code: 'ticket_not_found',
                status: 404
            );
        }

        // Side effect: Mark all unread admin messages as read and reset unread indicator
        $ticket->messages()
            ->where('author_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($ticket->has_unread_reply) {
            $ticket->update(['has_unread_reply' => false]);
        }

        $ticket->load(['messages' => fn ($q) => $q->oldest('id')])
            ->loadCount('messages');

        return ApiResponse::success(new SupportTicketDetailResource($ticket));
    }

    /**
     * Send customer reply to an existing support ticket.
     *
     * Per Handoff §3.4.
     */
    public function reply(ReplyTicketRequest $request, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        /** @var SupportTicket|null $ticket */
        $ticket = $customer->supportTickets()
            ->where(function (Builder $q) use ($id): void {
                $q->where('ulid', $id)
                    ->orWhere('reference', $id);
            })
            ->first();

        if (! $ticket) {
            return ApiResponse::error(
                message: 'Support ticket not found.',
                code: 'ticket_not_found',
                status: 404
            );
        }

        // Rejection if resolved or closed per §3.4
        if ($ticket->status->isLockedForReplies()) {
            return ApiResponse::error(
                message: "This ticket is {$ticket->status->value} and cannot receive further replies.",
                code: 'ticket_locked',
                status: 422
            );
        }

        $message = $ticket->messages()->create([
            'author_type' => 'customer',
            'author_id' => $customer->id,
            'author_name' => $customer->username,
            'body' => (string) $request->input('message'),
        ]);

        $ticket->touch();

        return ApiResponse::success(
            data: new SupportTicketMessageResource($message),
            status: 201
        );
    }
}
