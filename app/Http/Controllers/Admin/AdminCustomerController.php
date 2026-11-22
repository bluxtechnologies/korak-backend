<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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
        }

        $customers = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Customer::count(),
            'verified' => Customer::whereNotNull('email_verified_at')->count(),
            'with_virtual_account' => Customer::whereHas('virtualAccount')->count(),
        ];

        return view('admin.customers.index', [
            'admin' => $request->user('admin'),
            'customers' => $customers,
            'search' => $search,
            'status' => $status,
            'stats' => $stats,
        ]);
    }
}
