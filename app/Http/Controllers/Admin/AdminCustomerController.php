<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEventName;
use App\Enums\FundingStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\TicketStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditEvent;
use App\Models\BillOrder;
use App\Models\Customer;
use App\Models\FundingRecord;
use App\Models\LedgerEntry;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Notifications\SupportTicketReplyNotification;
use App\Notifications\WalletAdjustmentNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\LoginLockoutService;
use App\Services\Providers\FinityPayService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminCustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $query = Customer::query()
            ->with(['wallet', 'virtualAccount'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ulid', $search);
            });
        }

        if ($status === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($status === 'unverified') {
            $query->whereNull('email_verified_at');
        } elseif ($status === 'has_account') {
            $query->whereHas('virtualAccount');
        } elseif ($status === 'locked') {
            $query->where('is_locked', true);
        } elseif ($status === 'active') {
            $query->where('is_locked', false);
        }

        $customers = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => Customer::count(),
            'verified' => Customer::whereNotNull('email_verified_at')->count(),
            'with_virtual_account' => Customer::whereHas('virtualAccount')->count(),
            'locked' => Customer::where('is_locked', true)->count(),
        ];

        return view('admin.customers.index', [
            'admin' => $request->user('admin'),
            'customers' => $customers,
            'search' => $search,
            'status' => $status,
            'stats' => $stats,
        ]);
    }

    /**
     * Display customer details profile (Overview).
     */
    public function show(Customer $customer, Request $request): View
    {
        $customer->load([
            'wallet.ledgerEntries' => fn ($q) => $q->latest('id')->limit(10),
            'virtualAccount',
            'trustedDevices' => fn ($q) => $q->latest('id')->limit(10),
            'supportTickets' => fn ($q) => $q->latest('id')->limit(5),
            'billOrders' => fn ($q) => $q->latest('id')->limit(5),
        ]);

        return view('admin.customers.show', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'headerStats' => $this->getCustomerHeaderStats($customer),
            'activeTab' => 'overview',
        ]);
    }

    /**
     * Display customer dedicated transactions view.
     */
    public function transactions(Customer $customer, Request $request): View
    {
        $customer->load(['wallet', 'virtualAccount']);

        $wallet = $customer->wallet;
        $search = $request->string('search')->trim()->value();
        $direction = $request->string('direction')->trim()->value();
        $type = $request->string('type')->trim()->value();
        $dateFrom = $request->string('date_from')->trim()->value();
        $dateTo = $request->string('date_to')->trim()->value();

        $query = LedgerEntry::query()
            ->where('wallet_id', $wallet ? $wallet->id : 0)
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($direction !== '') {
            $query->where('direction', $direction);
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $ledgerEntries = $query->paginate(20)->withQueryString();

        $walletId = $wallet ? $wallet->id : 0;
        $transStats = [
            'total_credits' => LedgerEntry::where('wallet_id', $walletId)->where('direction', LedgerDirection::CREDIT)->sum('amount'),
            'total_debits' => LedgerEntry::where('wallet_id', $walletId)->where('direction', LedgerDirection::DEBIT)->sum('amount'),
            'manual_adjustments' => LedgerEntry::where('wallet_id', $walletId)->where('type', LedgerEntryType::MANUAL_ADJUSTMENT)->count(),
        ];

        return view('admin.customers.transactions', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'wallet' => $wallet,
            'ledgerEntries' => $ledgerEntries,
            'transStats' => $transStats,
            'search' => $search,
            'direction' => $direction,
            'type' => $type,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'headerStats' => $this->getCustomerHeaderStats($customer),
            'activeTab' => 'transactions',
        ]);
    }

    /**
     * Display customer dedicated orders view.
     */
    public function orders(Customer $customer, Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $category = $request->string('category')->trim()->value();

        $query = BillOrder::query()
            ->where('customer_id', $customer->id)
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('provider_reference', 'like', "%{$search}%")
                    ->orWhere('customer_identifier', 'like', "%{$search}%")
                    ->orWhere('token', 'like', "%{$search}%")
                    ->orWhere('service_name', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        $orders = $query->paginate(20)->withQueryString();

        $orderStats = [
            'total_count' => BillOrder::where('customer_id', $customer->id)->count(),
            'total_amount' => BillOrder::where('customer_id', $customer->id)->where('status', 'completed')->sum('amount'),
            'completed' => BillOrder::where('customer_id', $customer->id)->where('status', 'completed')->count(),
            'failed' => BillOrder::where('customer_id', $customer->id)->whereIn('status', ['failed', 'refunded'])->count(),
        ];

        return view('admin.customers.orders', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'orders' => $orders,
            'orderStats' => $orderStats,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'headerStats' => $this->getCustomerHeaderStats($customer),
            'activeTab' => 'orders',
        ]);
    }

    /**
     * Display customer dedicated support tickets view.
     */
    public function tickets(Customer $customer, Request $request): View
    {
        $status = $request->string('status')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $query = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->with(['latestMessage'])
            ->withCount('messages')
            ->latest('updated_at');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $tickets = $query->paginate(15)->withQueryString();

        $ticketStats = [
            'total' => SupportTicket::where('customer_id', $customer->id)->count(),
            'open' => SupportTicket::where('customer_id', $customer->id)->where('status', TicketStatus::OPEN)->count(),
            'in_progress' => SupportTicket::where('customer_id', $customer->id)->where('status', TicketStatus::IN_PROGRESS)->count(),
            'resolved' => SupportTicket::where('customer_id', $customer->id)->whereIn('status', [TicketStatus::RESOLVED, TicketStatus::CLOSED])->count(),
        ];

        return view('admin.customers.tickets.index', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'tickets' => $tickets,
            'ticketStats' => $ticketStats,
            'status' => $status,
            'category' => $category,
            'search' => $search,
            'headerStats' => $this->getCustomerHeaderStats($customer),
            'activeTab' => 'tickets',
        ]);
    }

    /**
     * Display detailed conversation thread for a customer's support ticket.
     */
    public function showTicket(Customer $customer, SupportTicket $ticket, Request $request): View
    {
        abort_if($ticket->customer_id !== $customer->id, 404);

        $ticket->load([
            'messages' => fn ($q) => $q->oldest('id'),
            'customer',
        ])->loadCount('messages');

        // Mark unread messages as read by admin if needed
        $ticket->messages()
            ->where('author_type', 'customer')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('admin.customers.tickets.show', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'ticket' => $ticket,
            'headerStats' => $this->getCustomerHeaderStats($customer),
            'activeTab' => 'tickets',
        ]);
    }

    /**
     * Reply to a customer's support ticket.
     */
    public function replyTicket(Customer $customer, SupportTicket $ticket, Request $request): JsonResponse|RedirectResponse
    {
        abort_if($ticket->customer_id !== $customer->id, 404);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
        ]);

        $admin = $request->user('admin');
        $authorName = $admin instanceof Admin ? $admin->username : 'Support Agent';

        DB::transaction(function () use ($ticket, $validated, $admin, $authorName, $customer): void {
            SupportTicketMessage::create([
                'ulid' => (string) Str::ulid(),
                'ticket_id' => $ticket->id,
                'author_type' => 'admin',
                'author_id' => $admin?->id,
                'author_name' => $authorName,
                'body' => $validated['message'],
                'created_at' => now(),
            ]);

            $newStatus = $validated['status'] ?? null;
            if ($newStatus) {
                $ticket->status = TicketStatus::from($newStatus);
            }

            $ticket->has_unread_reply = true;
            $ticket->touch();
            $ticket->save();

            // Dispatch notification to customer
            $snippet = Str::limit($validated['message'], 100);
            $customer->notify(new SupportTicketReplyNotification($ticket, $snippet));
        });

        $message = 'Reply sent to customer successfully.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Update customer support ticket status.
     */
    public function updateTicketStatus(Customer $customer, SupportTicket $ticket, Request $request): JsonResponse|RedirectResponse
    {
        abort_if($ticket->customer_id !== $customer->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
        ]);

        $ticket->update([
            'status' => TicketStatus::from($validated['status']),
        ]);

        $message = 'Ticket status updated to '.ucfirst(str_replace('_', ' ', $validated['status'])).'.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Manually credit or debit customer wallet.
     */
    public function adjustWallet(Customer $customer, Request $request, WalletService $walletService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $action = $validated['action'];
        $amountInKobo = (int) round(((float) $validated['amount']) * 100);
        $reason = trim($validated['reason']);
        $admin = $request->user('admin');

        $wallet = $walletService->getOrCreateWallet($customer);

        // Overdraw protection on debit
        if ($action === 'debit' && $wallet->balance < $amountInKobo) {
            $formattedBal = number_format($wallet->balance / 100, 2);
            $errorMessage = "Insufficient customer balance. The customer currently has ₦{$formattedBal} available.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage,
                ], 422);
            }

            return back()->withErrors(['amount' => $errorMessage])->withInput();
        }

        $reference = trim((string) ($validated['reference'] ?? ''));
        if ($reference === '') {
            $prefix = $action === 'credit' ? 'MANUAL-CR-' : 'MANUAL-DR-';
            $reference = $prefix.strtoupper(Str::random(10));
        }

        $metadata = [
            'channel' => 'admin_console',
            'admin_id' => $admin?->id,
            'admin_username' => $admin?->username,
            'reason' => $reason,
            'adjusted_at' => now()->toIso8601String(),
        ];

        try {
            if ($action === 'credit') {
                $walletService->credit(
                    wallet: $wallet,
                    amount: $amountInKobo,
                    type: LedgerEntryType::MANUAL_ADJUSTMENT,
                    description: "Manual Admin Credit: {$reason}",
                    reference: $reference,
                    metadata: $metadata
                );
            } else {
                $walletService->debit(
                    wallet: $wallet,
                    amount: $amountInKobo,
                    type: LedgerEntryType::MANUAL_ADJUSTMENT,
                    description: "Manual Admin Debit: {$reason}",
                    reference: $reference,
                    metadata: $metadata
                );
            }
        } catch (InsufficientBalanceException $e) {
            $errorMessage = $e->getMessage();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage,
                ], 422);
            }

            return back()->withErrors(['amount' => $errorMessage])->withInput();
        } catch (Throwable $e) {
            $errorMessage = 'Failed to adjust wallet balance: '.$e->getMessage();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $errorMessage,
                ], 500);
            }

            return back()->withErrors(['amount' => $errorMessage])->withInput();
        }

        $wallet->refresh();
        $formattedAmount = number_format($amountInKobo / 100, 2);
        $newBalanceFormatted = number_format($wallet->balance / 100, 2);
        $verb = $action === 'credit' ? 'credited with' : 'debited of';
        $successMessage = "Successfully {$verb} ₦{$formattedAmount}. New wallet balance: ₦{$newBalanceFormatted}.";

        // Dispatch notification if requested
        if ($request->boolean('notify', true)) {
            $customer->notify(new WalletAdjustmentNotification(
                action: $action,
                amountInKobo: $amountInKobo,
                reason: $reason,
                currency: $wallet->currency,
                reference: $reference
            ));
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'data' => [
                    'balance_kobo' => $wallet->balance,
                    'balance_formatted' => '₦'.$newBalanceFormatted,
                    'reference' => $reference,
                ],
            ]);
        }

        return back()->with('success', $successMessage);
    }

    /**
     * Manually mark customer email as verified.
     */
    public function verifyEmail(Customer $customer, Request $request): JsonResponse|RedirectResponse
    {
        if ($customer->email_verified_at !== null) {
            $message = 'Customer email is already verified.';
        } else {
            $customer->update(['email_verified_at' => now()]);
            $message = "Customer email for @{$customer->username} has been verified successfully.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'verified_at' => $customer->email_verified_at?->format('M d, Y H:i'),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Dispatch password reset link to customer email.
     */
    public function sendPasswordReset(Customer $customer, Request $request): JsonResponse|RedirectResponse
    {
        Password::broker('customers')->sendResetLink(['email' => $customer->email]);

        $message = "A password reset link has been dispatched to {$customer->email}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Terminate active sessions and revoke trusted devices.
     */
    public function terminateSessions(Customer $customer, Request $request): JsonResponse|RedirectResponse
    {
        $devicesCount = $customer->trustedDevices()->count();
        $customer->trustedDevices()->delete();
        $customer->deviceChallenges()->delete();

        // Invalidate database session store if active
        try {
            DB::table('sessions')->where('user_id', $customer->id)->delete();
        } catch (Throwable) {
            // Non-fatal if session driver is not database
        }

        $message = "Terminated {$devicesCount} trusted device(s) and reset active sessions for @{$customer->username}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Toggle lock/suspended state for a customer.
     */
    public function toggleLock(Customer $customer, Request $request, LoginLockoutService $lockoutService): JsonResponse|RedirectResponse
    {
        $nowLocked = ! $customer->is_locked;

        if ($nowLocked) {
            $customer->update([
                'is_locked' => true,
                'locked_at' => now(),
                'lock_reason' => $request->string('reason', 'Administrative lock')->value(),
            ]);
            $message = "Customer @{$customer->username} has been locked.";
        } else {
            $customer->update([
                'is_locked' => false,
                'locked_at' => null,
                'lock_reason' => null,
            ]);
            $lockoutService->unlock($customer);
            $message = "Customer @{$customer->username} has been unlocked successfully.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'is_locked' => $customer->is_locked,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Delete a customer and cascade relations.
     */
    public function destroy(Customer $customer, Request $request): JsonResponse|RedirectResponse
    {
        $username = $customer->username;
        $customer->delete();

        $message = "Customer @{$username} and associated records were deleted successfully.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return redirect()->route('admin.customers.index')->with('success', $message);
    }

    /**
     * Display customer analytics, trends, and product breakdown.
     */
    public function analytics(Customer $customer, Request $request): View
    {
        $headerStats = $this->getCustomerHeaderStats($customer);

        $hasGrossAmount = Schema::hasColumn('funding_records', 'gross_amount');
        $hasFee = Schema::hasColumn('funding_records', 'fee');

        // Financial KPIs
        $totalDepositsKobo = 0;
        if ($hasGrossAmount) {
            $totalDepositsKobo = (int) FundingRecord::where('customer_id', $customer->id)
                ->where('status', FundingStatus::SUCCESSFUL)
                ->sum('gross_amount');
        }

        if ($totalDepositsKobo === 0) {
            $totalDepositsKobo = (int) FundingRecord::where('customer_id', $customer->id)
                ->where('status', FundingStatus::SUCCESSFUL)
                ->sum('amount');
        }

        $totalSpentKobo = (int) BillOrder::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->sum('amount');

        $totalFeesKobo = $hasFee
            ? (int) FundingRecord::where('customer_id', $customer->id)
                ->where('status', FundingStatus::SUCCESSFUL)
                ->sum('fee')
            : 0;

        $totalOrders = BillOrder::where('customer_id', $customer->id)->count();
        $completedOrders = BillOrder::where('customer_id', $customer->id)->where('status', 'completed')->count();
        $failedOrders = BillOrder::where('customer_id', $customer->id)->where('status', 'failed')->count();
        $refundedOrders = BillOrder::where('customer_id', $customer->id)->where('status', 'refunded')->count();

        $successRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;
        $averageOrderValueKobo = $completedOrders > 0 ? (int) round($totalSpentKobo / $completedOrders) : 0;

        // 6-Month Monthly Trend (Inflows vs Outflows)
        $monthlyLabels = [];
        $monthlyInflows = [];
        $monthlyOutflows = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyLabels[] = $date->format('M Y');

            $inflowMonth = (float) (FundingRecord::where('customer_id', $customer->id)
                ->where('status', FundingStatus::SUCCESSFUL)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount') / 100);

            $outflowMonth = (float) (BillOrder::where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount') / 100);

            $monthlyInflows[] = $inflowMonth;
            $monthlyOutflows[] = $outflowMonth;
        }

        // Spend by Category
        $categoriesRaw = BillOrder::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->select('type', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('type')
            ->get();

        $categoryLabels = [];
        $categorySeries = [];
        foreach ($categoriesRaw as $cat) {
            $categoryLabels[] = ucfirst(str_replace('_', ' ', (string) $cat->type));
            $categorySeries[] = (float) round($cat->total_amount / 100, 2);
        }

        // Top 5 Purchased Services
        $topServices = BillOrder::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->select('service_name', 'type', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(amount) as total_spent'))
            ->groupBy('service_name', 'type')
            ->orderByDesc('order_count')
            ->limit(5)
            ->get();

        return view('admin.customers.analytics', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'headerStats' => $headerStats,
            'metrics' => [
                'total_deposits' => $totalDepositsKobo / 100,
                'total_spent' => $totalSpentKobo / 100,
                'total_fees' => $totalFeesKobo / 100,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'failed_orders' => $failedOrders,
                'refunded_orders' => $refundedOrders,
                'success_rate' => $successRate,
                'average_order_value' => $averageOrderValueKobo / 100,
            ],
            'chartData' => [
                'monthly_labels' => $monthlyLabels,
                'monthly_inflows' => $monthlyInflows,
                'monthly_outflows' => $monthlyOutflows,
                'category_labels' => $categoryLabels,
                'category_series' => $categorySeries,
                'order_outcomes' => [$completedOrders, $failedOrders, $refundedOrders],
            ],
            'topServices' => $topServices,
        ]);
    }

    /**
     * Display customer bank inflows and fee breakdown.
     */
    public function inflows(Customer $customer, Request $request): View
    {
        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $query = FundingRecord::query()
            ->where('customer_id', $customer->id)
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('provider_reference', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $inflows = $query->paginate(20)->withQueryString();

        $headerStats = $this->getCustomerHeaderStats($customer);

        $hasGrossAmount = Schema::hasColumn('funding_records', 'gross_amount');
        $hasFee = Schema::hasColumn('funding_records', 'fee');

        $totalGross = $hasGrossAmount
            ? FundingRecord::where('customer_id', $customer->id)->where('status', FundingStatus::SUCCESSFUL)->sum('gross_amount')
            : FundingRecord::where('customer_id', $customer->id)->where('status', FundingStatus::SUCCESSFUL)->sum('amount');

        $totalFee = $hasFee
            ? FundingRecord::where('customer_id', $customer->id)->where('status', FundingStatus::SUCCESSFUL)->sum('fee')
            : 0;

        $summary = [
            'total_inflow_count' => FundingRecord::where('customer_id', $customer->id)->count(),
            'total_gross' => $totalGross,
            'total_fee' => $totalFee,
            'total_net' => FundingRecord::where('customer_id', $customer->id)->where('status', FundingStatus::SUCCESSFUL)->sum('amount'),
            'pending_count' => FundingRecord::where('customer_id', $customer->id)->where('status', FundingStatus::PENDING)->count(),
        ];

        return view('admin.customers.inflows', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'headerStats' => $headerStats,
            'inflows' => $inflows,
            'summary' => $summary,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Display security center and audit trail.
     */
    public function auditLogs(Customer $customer, Request $request, LoginLockoutService $lockoutService): View
    {
        $category = $request->string('category')->trim()->value();
        $ip = $request->string('ip')->trim()->value();

        $query = AuditEvent::query()
            ->where(function ($q) use ($customer): void {
                $q->where(function ($sq) use ($customer): void {
                    $sq->where('actor_type', Customer::class)
                        ->orWhere('actor_type', 'customer')
                        ->where('actor_id', $customer->id);
                })
                    ->orWhere(function ($sq) use ($customer): void {
                        $sq->where('subject_type', Customer::class)
                            ->orWhere('subject_type', 'customer')
                            ->where('subject_id', $customer->id);
                    });
            })
            ->latest('occurred_at');

        if ($category !== '') {
            $query->where('event', 'like', "{$category}.%");
        }

        if ($ip !== '') {
            $query->where('ip_address', 'like', "%{$ip}%");
        }

        $auditLogs = $query->paginate(25)->withQueryString();

        $headerStats = $this->getCustomerHeaderStats($customer);

        $securityState = [
            'device_verification_enabled' => (bool) $customer->device_verification_enabled,
            'is_locked' => (bool) $customer->is_locked,
            'is_lockout_active' => $lockoutService->isLocked($customer),
            'failed_attempts' => $lockoutService->failedAttempts($customer),
            'trusted_devices_count' => $customer->trustedDevices()->count(),
        ];

        return view('admin.customers.audit-logs', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
            'headerStats' => $headerStats,
            'auditLogs' => $auditLogs,
            'securityState' => $securityState,
            'category' => $category,
            'ip' => $ip,
        ]);
    }

    /**
     * Update customer profile contact details.
     */
    public function updateProfile(Customer $customer, Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email,'.$customer->id],
        ]);

        $changes = [];
        foreach ($validated as $key => $val) {
            if ($customer->{$key} !== $val) {
                $changes[$key] = ['old' => $customer->{$key}, 'new' => $val];
            }
        }

        $customer->fill($validated);
        if (isset($validated['phone_number'])) {
            $customer->phone = $validated['phone_number'];
        }
        $customer->save();

        if (! empty($changes)) {
            /** @var Admin|null $admin */
            $admin = $request->user('admin');
            app(AuditLogger::class)->log(
                event: AuditEventName::PROFILE_UPDATED,
                actor: $admin,
                subject: $customer,
                metadata: [
                    'updated_by' => 'admin',
                    'admin_username' => $admin?->username,
                    'changes' => $changes,
                ]
            );
        }

        $message = "Profile for @{$customer->username} updated successfully.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'customer' => $customer,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Generate or re-sync dedicated virtual account.
     */
    public function generateVirtualAccount(
        Customer $customer,
        Request $request,
        FinityPayService $finityPayService,
        WalletService $walletService
    ): JsonResponse|RedirectResponse {
        $wallet = $walletService->getOrCreateWallet($customer);
        $phone = $customer->phone_number ?: $customer->phone;

        $res = $finityPayService->createVirtualAccount($customer, $wallet, $phone);

        if (($res['status'] ?? '') === 'success') {
            $accNumber = $res['data']['account_number'] ?? 'Virtual Account';
            $bankName = $res['data']['bank_name'] ?? 'Bank';
            $message = "Dedicated account generated successfully: {$bankName} - {$accNumber}.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $message,
                    'virtual_account' => $customer->fresh()->virtualAccount,
                ]);
            }

            return back()->with('success', $message);
        }

        $errorMsg = $res['message'] ?? 'Failed to generate dedicated virtual account with provider.';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => $errorMsg,
            ], 422);
        }

        return back()->with('error', $errorMsg);
    }

    /**
     * Toggle 2FA device verification.
     */
    public function toggleDeviceVerification(Customer $customer, Request $request): JsonResponse|RedirectResponse
    {
        $customer->device_verification_enabled = ! $customer->device_verification_enabled;
        $customer->save();

        $statusStr = $customer->device_verification_enabled ? 'enabled' : 'disabled';
        $message = "2FA Device Verification has been {$statusStr} for @{$customer->username}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'device_verification_enabled' => $customer->device_verification_enabled,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Clear login lockouts and failed attempts counter.
     */
    public function clearLockout(Customer $customer, Request $request, LoginLockoutService $lockoutService): JsonResponse|RedirectResponse
    {
        $lockoutService->unlock($customer);

        $message = "Login lockout and failed attempts counter cleared for @{$customer->username}.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Reconcile a bank inflow directly with the payment provider.
     */
    public function reconcileInflow(
        Customer $customer,
        FundingRecord $fundingRecord,
        Request $request,
        FinityPayService $finityPayService,
        WalletService $walletService
    ): JsonResponse|RedirectResponse {
        if ($fundingRecord->customer_id !== $customer->id) {
            abort(404);
        }

        if ($fundingRecord->status === FundingStatus::SUCCESSFUL) {
            $msg = "Inflow #{$fundingRecord->reference} is already completed.";

            return $request->expectsJson() ? response()->json(['status' => 'info', 'message' => $msg]) : back()->with('info', $msg);
        }

        $lookupRef = $fundingRecord->provider_reference ?: $fundingRecord->reference;
        $verifyRes = $finityPayService->verifyPayment($lookupRef);

        if (($verifyRes['status'] ?? '') === 'success') {
            $fundingRecord->update([
                'status' => FundingStatus::SUCCESSFUL,
                'processed_at' => now(),
            ]);

            $wallet = $walletService->getOrCreateWallet($customer);
            $walletService->credit(
                wallet: $wallet,
                amount: $fundingRecord->amount,
                type: LedgerEntryType::FUNDING,
                reference: $fundingRecord->reference,
                description: "Bank transfer deposit reconciled (Ref: {$fundingRecord->reference})"
            );

            $msg = "Inflow #{$fundingRecord->reference} verified and customer wallet credited successfully.";

            return $request->expectsJson() ? response()->json(['status' => 'success', 'message' => $msg]) : back()->with('success', $msg);
        }

        $errMsg = $verifyRes['message'] ?? 'Provider reported transaction as unconfirmed or not found.';

        return $request->expectsJson()
            ? response()->json(['status' => 'error', 'message' => $errMsg], 422)
            : back()->with('error', $errMsg);
    }

    /**
     * Stream CSV export of customer ledger transactions.
     */
    public function exportTransactions(Customer $customer): StreamedResponse
    {
        $fileName = 'transactions_'.$customer->username.'_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($customer): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Reference', 'Date', 'Type', 'Direction', 'Amount (NGN)', 'Balance Before (NGN)', 'Balance After (NGN)', 'Description']);

            $customer->wallet?->ledgerEntries()->orderBy('id')->chunk(500, function ($entries) use ($handle): void {
                foreach ($entries as $e) {
                    $balanceBefore = ($e->direction === LedgerDirection::CREDIT)
                        ? ($e->balance_after - $e->amount)
                        : ($e->balance_after + $e->amount);

                    fputcsv($handle, [
                        $e->id,
                        $e->reference,
                        $e->created_at->toDateTimeString(),
                        $e->type->value ?? 'entry',
                        $e->direction->value ?? '',
                        number_format($e->amount / 100, 2, '.', ''),
                        number_format($balanceBefore / 100, 2, '.', ''),
                        number_format($e->balance_after / 100, 2, '.', ''),
                        $e->description ?? '',
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Stream CSV export of customer bill orders.
     */
    public function exportOrders(Customer $customer): StreamedResponse
    {
        $fileName = 'orders_'.$customer->username.'_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($customer): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Reference', 'Date', 'Service', 'Type', 'Identifier', 'Amount (NGN)', 'Status']);

            $customer->billOrders()->orderBy('id')->chunk(500, function ($orders) use ($handle): void {
                foreach ($orders as $o) {
                    fputcsv($handle, [
                        $o->id,
                        $o->reference,
                        $o->created_at->toDateTimeString(),
                        $o->service_name,
                        $o->type,
                        $o->customer_identifier,
                        number_format($o->amount / 100, 2, '.', ''),
                        $o->status,
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Stream CSV export of customer bank inflows.
     */
    public function exportInflows(Customer $customer): StreamedResponse
    {
        $fileName = 'inflows_'.$customer->username.'_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($customer): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Reference', 'Provider Ref', 'Date', 'Gross (NGN)', 'Fee (NGN)', 'Net (NGN)', 'Sender Name', 'Status']);

            FundingRecord::where('customer_id', $customer->id)->orderBy('id')->chunk(500, function ($inflows) use ($handle): void {
                foreach ($inflows as $inflow) {
                    fputcsv($handle, [
                        $inflow->id,
                        $inflow->reference,
                        $inflow->provider_reference ?? '',
                        $inflow->created_at->toDateTimeString(),
                        number_format(($inflow->gross_amount ?? $inflow->amount) / 100, 2, '.', ''),
                        number_format(($inflow->fee ?? 0) / 100, 2, '.', ''),
                        number_format($inflow->amount / 100, 2, '.', ''),
                        $inflow->sender_name ?? '',
                        $inflow->status->value ?? (string) $inflow->status,
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Helper to compute customer 360 quick stats.
     *
     * @return array<string, mixed>
     */
    private function getCustomerHeaderStats(Customer $customer): array
    {
        $wallet = $customer->wallet;

        return [
            'wallet_balance_formatted' => '₦'.number_format(($wallet ? $wallet->balance : 0) / 100, 2),
            'orders_count' => $customer->billOrders()->count(),
            'orders_spent_formatted' => '₦'.number_format($customer->billOrders()->where('status', 'completed')->sum('amount') / 100, 2),
            'tickets_count' => $customer->supportTickets()->count(),
            'open_tickets_count' => $customer->supportTickets()->whereIn('status', [TicketStatus::OPEN, TicketStatus::IN_PROGRESS])->count(),
            'devices_count' => $customer->trustedDevices()->count(),
        ];
    }
}
