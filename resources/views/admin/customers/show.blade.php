@extends('layouts.admin')

@php
    $title = 'Customer - ' . $customer->username;
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customer: @' . $customer->username,
    'subtitle' => 'Comprehensive profile, wallet activity, dedicated accounts, and device security.',
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => null,
    ],
    'actions' => [
        [
            'label' => 'Back to Customers',
            'icon' => 'ti ti-arrow-left',
            'class' => 'btn btn-outline-secondary',
            'url' => route('admin.customers.index'),
        ],
    ],
])

{{-- Persistent Customer 360 Header --}}
@include('admin.customers.partials.header', ['activeTab' => 'overview'])

<div class="row g-3">
    <!-- Left Column: Detailed Contact & Profile Info -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-id me-1 text-primary"></i> Profile & Contact
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 fs-13">
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Full Name:</span>
                        <span class="fw-semibold text-dark">{{ trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Username:</span>
                        <span class="font-monospace text-dark">@<span>{{ $customer->username }}</span></span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Email:</span>
                        <span class="fw-medium text-dark">{{ $customer->email }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Email Status:</span>
                        @if($customer->email_verified_at)
                            <span class="text-success"><i class="ti ti-check-circle me-1"></i> Verified ({{ $customer->email_verified_at->format('M d, Y') }})</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning">Unverified</span>
                        @endif
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Phone:</span>
                        <span class="fw-medium text-dark">{{ $customer->phone_number ?: ($customer->phone ?: 'Not provided') }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Public ULID:</span>
                        <span class="font-monospace text-dark fs-12">{{ $customer->ulid }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Registered On:</span>
                        <span class="text-dark">{{ $customer->created_at ? $customer->created_at->format('M d, Y H:i') : '—' }}</span>
                    </li>
                    @if($customer->is_locked && $customer->locked_at)
                        <li class="d-flex justify-content-between align-items-center text-danger">
                            <span>Locked Since:</span>
                            <span>{{ $customer->locked_at->format('M d, Y H:i') }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        <!-- Dedicated Virtual Account Card -->
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-building-bank me-1 text-info"></i> Dedicated Virtual Account
                </h5>
            </div>
            <div class="card-body">
                @if($customer->virtualAccount)
                    <div class="row g-2 fs-13">
                        <div class="col-12 mb-2">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Bank Name</span>
                            <span class="fs-15 text-dark fw-bold">{{ $customer->virtualAccount->bank_name }}</span>
                        </div>
                        <div class="col-12 mb-2">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Account Number</span>
                            <span class="fs-18 font-monospace text-dark fw-bold">{{ $customer->virtualAccount->account_number }}</span>
                        </div>
                        <div class="col-12 mb-2">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Account Name</span>
                            <span class="fs-13 text-dark">{{ $customer->virtualAccount->account_name ?: '—' }}</span>
                        </div>
                        <div class="col-12">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Provider & Ref</span>
                            <span class="fs-12 text-muted font-monospace">{{ ucfirst($customer->virtualAccount->provider) }} ({{ $customer->virtualAccount->account_reference }})</span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-3 text-muted">
                        <i class="ti ti-building-bank fs-28 mb-1 d-block text-secondary"></i>
                        <p class="fs-12 mb-0">No dedicated virtual account generated yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Wallet Snapshot, Recent Orders & Security -->
    <div class="col-lg-8">
        <!-- Wallet & Recent Ledger Snapshot -->
        <div class="card mb-3">
            <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-wallet me-1 text-primary"></i> Wallet & Recent Ledger
                </h5>
                <a href="{{ route('admin.customers.transactions', $customer) }}" class="btn btn-sm btn-link text-primary p-0 text-decoration-none fs-12">
                    View All Transactions <i class="ti ti-arrow-right"></i>
                </a>
            </div>
            <div class="card-body">
                @if($customer->wallet)
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded mb-3">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-semibold">Available Balance</span>
                            <h2 class="my-1 text-dark fs-28 fw-bold">
                                ₦{{ number_format($customer->wallet->balance / 100, 2) }}
                            </h2>
                            <small class="text-muted fs-11">Currency: {{ $customer->wallet->currency }}</small>
                        </div>
                        <button type="button"
                                class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 btn-open-adjust-modal"
                                data-customer-id="{{ $customer->id }}"
                                data-customer-name="{{ trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username }}"
                                data-customer-username="{{ $customer->username }}"
                                data-customer-balance="{{ number_format($customer->wallet->balance / 100, 2) }}">
                            <i class="ti ti-plus-minus fs-14"></i> Adjust Balance
                        </button>
                    </div>

                    @if($customer->wallet->ledgerEntries->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 fs-12">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($customer->wallet->ledgerEntries as $entry)
                                        <tr>
                                            <td>{{ $entry->created_at->format('M d, H:i') }}</td>
                                            <td>
                                                <span class="badge {{ ($entry->type->value ?? '') === 'credit' || ($entry->direction->value ?? '') === 'credit' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                    {{ ucfirst($entry->type->value ?? 'entry') }}
                                                </span>
                                            </td>
                                            <td class="fw-semibold">
                                                {{ ($entry->direction->value ?? '') === 'credit' ? '+' : '-' }}₦{{ number_format($entry->amount / 100, 2) }}
                                            </td>
                                            <td class="text-muted">{{ Str::limit($entry->description ?? '—', 45) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted fs-13 mb-0">No ledger transactions recorded yet.</p>
                    @endif
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="ti ti-wallet-off fs-32 mb-1 d-block text-secondary"></i>
                        <p class="mb-0 fs-13">No wallet created for this customer.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Orders Snapshot -->
        <div class="card mb-3">
            <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-shopping-cart me-1 text-info"></i> Recent Orders
                </h5>
                <a href="{{ route('admin.customers.orders', $customer) }}" class="btn btn-sm btn-link text-primary p-0 text-decoration-none fs-12">
                    View All Orders <i class="ti ti-arrow-right"></i>
                </a>
            </div>
            <div class="card-body">
                @if($customer->billOrders->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 fs-12">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Service</th>
                                    <th>Identifier</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->billOrders as $order)
                                    <tr>
                                        <td>{{ $order->created_at->format('M d, H:i') }}</td>
                                        <td class="fw-medium text-dark">{{ $order->service_name }}</td>
                                        <td class="font-monospace text-muted">{{ $order->customer_identifier }}</td>
                                        <td class="fw-semibold">₦{{ number_format($order->amount / 100, 2) }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($order->status) {
                                                    'completed' => 'bg-success-subtle text-success',
                                                    'processing' => 'bg-info-subtle text-info',
                                                    'failed' => 'bg-danger-subtle text-danger',
                                                    'refunded' => 'bg-warning-subtle text-warning',
                                                    default => 'bg-secondary-subtle text-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ ucfirst($order->status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted fs-13 mb-0">No orders placed by this customer yet.</p>
                @endif
            </div>
        </div>

        <!-- Security & Trusted Devices -->
        <div class="card mb-3">
            <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-shield-lock me-1 text-success"></i> Security & Devices
                </h5>
                <span class="badge {{ $customer->device_verification_enabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                    2FA Verification: {{ $customer->device_verification_enabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="card-body">
                @if($customer->trustedDevices->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 fs-12">
                            <thead class="table-light">
                                <tr>
                                    <th>Device Name</th>
                                    <th>IP Address</th>
                                    <th>Last Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->trustedDevices as $device)
                                    <tr>
                                        <td>
                                            <i class="ti ti-device-mobile me-1 text-muted"></i>
                                            {{ $device->device_name ?: 'Unknown Device' }}
                                        </td>
                                        <td class="font-monospace text-muted">{{ $device->ip_address ?? '—' }}</td>
                                        <td class="text-muted">{{ ($device->last_used_at ?? $device->updated_at)?->diffForHumans() ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted fs-13 mb-0">No registered trusted devices.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Adjustment Modal & Script Partials --}}
@include('admin.customers.partials.credit-debit-modal')
@endsection

@section('scripts')
    <script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>
    @include('admin.customers.partials.scripts')
@endsection
