<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Auth\LoginLockoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        // When DataTables is active, fetch all filtered records or a generous collection for table manipulation
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
     * Display customer details profile.
     */
    public function show(Customer $customer, Request $request): View
    {
        $customer->load([
            'wallet.ledgerEntries' => fn ($q) => $q->latest('id')->limit(10),
            'virtualAccount',
            'trustedDevices' => fn ($q) => $q->latest('last_seen_at')->limit(10),
            'supportTickets' => fn ($q) => $q->latest('id')->limit(5),
        ]);

        return view('admin.customers.show', [
            'admin' => $request->user('admin'),
            'customer' => $customer,
        ]);
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
}
