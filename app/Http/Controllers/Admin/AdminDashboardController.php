<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerVirtualAccount;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(Request $request): View
    {
        return view('admin.dashboard', [
            'admin' => $request->user('admin'),
            'totalCustomers' => Customer::count(),
            'totalVirtualAccounts' => CustomerVirtualAccount::count(),
            'totalWallets' => Wallet::count(),
        ]);
    }
}
