@extends('layouts.admin')

@php
    $title = 'Customers';
@endphp

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers',
    'subtitle' => 'View, filter, and manage registered customer accounts, wallets, and virtual bank references.',
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => null,
    ],
    'actions' => [
        [
            'label' => 'Refresh',
            'icon' => 'ti ti-refresh',
            'class' => 'btn btn-outline-secondary',
            'url' => route('admin.customers.index'),
        ],
    ],
])

<!-- Summary Stat Cards -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Total Customers</span>
                        <h3 class="my-1 fs-22 text-dark">{{ number_format($stats['total'] ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">All registered platform users</p>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-20 p-2">
                            <i class="ti ti-users"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Verified Emails</span>
                        <h3 class="my-1 fs-22 text-dark">{{ number_format($stats['verified'] ?? 0) }}</h3>
                        <p class="mb-0 text-success fs-12">
                            <i class="ti ti-badge-check"></i>
                            @if(($stats['total'] ?? 0) > 0)
                                {{ round((($stats['verified'] ?? 0) / $stats['total']) * 100) }}% verification rate
                            @else
                                0% verification rate
                            @endif
                        </p>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-20 p-2">
                            <i class="ti ti-mail-check"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Virtual Accounts</span>
                        <h3 class="my-1 fs-22 text-dark">{{ number_format($stats['with_virtual_account'] ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">Customers with dedicated bank accounts</p>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle text-info rounded fs-20 p-2">
                            <i class="ti ti-building-bank"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Search -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="ti ti-search text-muted"></i>
                    </span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           class="form-control border-start-0 ps-0"
                           placeholder="Search by username, email, phone, name, or ULID...">
                </div>
            </div>

            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Verification Statuses</option>
                    <option value="verified" {{ $status === 'verified' ? 'selected' : '' }}>Verified Only</option>
                    <option value="unverified" {{ $status === 'unverified' ? 'selected' : '' }}>Unverified Only</option>
                    <option value="has_account" {{ $status === 'has_account' ? 'selected' : '' }}>Has Virtual Account</option>
                </select>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || $status)
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-light" title="Reset Filters">
                        <i class="ti ti-x"></i> Clear
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-nowrap align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">#</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Wallet Balance</th>
                        <th>Dedicated Account</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td class="ps-3 text-muted fs-12">
                                {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-sm">
                                        <span class="avatar-title bg-light text-primary rounded-circle fw-semibold fs-14">
                                            {{ strtoupper(substr($customer->first_name ?? $customer->username, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="fs-14 mb-0 fw-semibold text-dark">
                                            {{ trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username }}
                                        </h6>
                                        <span class="text-muted fs-12">@<span>{{ $customer->username }}</span></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="fs-13 text-dark">{{ $customer->email }}</span>
                                        @if($customer->email_verified_at)
                                            <i class="ti ti-circle-check-filled text-success fs-14" title="Email Verified"></i>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning fs-10">Unverified</span>
                                        @endif
                                    </div>
                                    <span class="text-muted fs-12">{{ $customer->phone_number ?: ($customer->phone ?: 'No phone') }}</span>
                                </div>
                            </td>
                            <td>
                                @if($customer->wallet)
                                    <span class="fw-semibold text-dark fs-13">
                                        ₦{{ number_format(($customer->wallet->balance ?? 0) / 100, 2) }}
                                    </span>
                                    <span class="text-muted fs-11 d-block">{{ $customer->wallet->currency ?? 'NGN' }}</span>
                                @else
                                    <span class="badge bg-light text-muted">No Wallet</span>
                                @endif
                            </td>
                            <td>
                                @if($customer->virtualAccount)
                                    <div>
                                        <span class="fw-medium text-dark fs-13">{{ $customer->virtualAccount->bank_name }}</span>
                                        <span class="text-muted fs-12 d-block font-monospace">{{ $customer->virtualAccount->account_number }}</span>
                                    </div>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary fs-11">Not Generated</span>
                                @endif
                            </td>
                            <td>
                                <span class="fs-12 text-muted">
                                    {{ $customer->created_at ? $customer->created_at->format('M d, Y') : '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-users-minus fs-36 mb-2 d-block text-secondary"></i>
                                    <h5 class="fs-16">No customers found</h5>
                                    <p class="fs-13 mb-0">
                                        @if($search || $status)
                                            No customers matched your search query or filter.
                                            <a href="{{ route('admin.customers.index') }}" class="d-block mt-2">Clear filters</a>
                                        @else
                                            No customers have registered yet.
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($customers->hasPages())
        <div class="card-footer bg-transparent border-top py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="text-muted fs-12">
                    Showing {{ $customers->firstItem() }} to {{ $customers->lastItem() }} of {{ $customers->total() }} customers
                </span>
                <div>
                    {{ $customers->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
