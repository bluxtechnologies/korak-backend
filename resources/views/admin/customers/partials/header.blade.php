{{-- Persistent Customer 360 Header & Navigation Bar --}}
@php
    $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username;
    $initial = strtoupper(substr($customer->first_name ?? $customer->username, 0, 1));
    $activeTab = $activeTab ?? 'overview';
@endphp

<div class="card mb-3 border-0 shadow-sm">
    <div class="card-body p-3 p-md-4 pb-2">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
            {{-- Left: Avatar & Identity --}}
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-lg position-relative flex-shrink-0">
                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fw-bold fs-24 d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        {{ $initial }}
                    </span>
                    @if($customer->is_locked)
                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-danger" title="Account Locked">
                            <i class="ti ti-lock fs-11"></i>
                        </span>
                    @else
                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success" title="Account Active">
                            <i class="ti ti-circle-check fs-11"></i>
                        </span>
                    @endif
                </div>

                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <h4 class="fs-18 fw-bold text-dark mb-0">{{ $fullName }}</h4>
                        <span class="badge bg-light text-muted font-monospace fs-11">#{{ $customer->ulid }}</span>
                        @if($customer->is_locked)
                            <span class="badge bg-danger-subtle text-danger fs-11"><i class="ti ti-lock me-1"></i> Locked</span>
                        @else
                            <span class="badge bg-success-subtle text-success fs-11"><i class="ti ti-check me-1"></i> Active</span>
                        @endif

                        @if($customer->email_verified_at)
                            <span class="badge bg-success-subtle text-success fs-11" title="Email Verified on {{ $customer->email_verified_at->format('M d, Y') }}">
                                <i class="ti ti-circle-check-filled me-1"></i> Verified
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning fs-11">Unverified Email</span>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-3 text-muted fs-13 flex-wrap">
                        <span><i class="ti ti-at text-primary me-1"></i>{{ $customer->username }}</span>
                        <span><i class="ti ti-mail text-secondary me-1"></i>{{ $customer->email }}</span>
                        <span><i class="ti ti-phone text-secondary me-1"></i>{{ $customer->phone_number ?: ($customer->phone ?: 'No phone') }}</span>
                        <span><i class="ti ti-calendar text-secondary me-1"></i>Joined {{ $customer->created_at ? $customer->created_at->format('M d, Y') : '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Right: Actions Dropdown & Quick Adjust --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button"
                        class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 btn-open-adjust-modal"
                        data-customer-id="{{ $customer->id }}"
                        data-customer-name="{{ $fullName }}"
                        data-customer-username="{{ $customer->username }}"
                        data-customer-balance="{{ $customer->wallet ? number_format($customer->wallet->balance / 100, 2) : '0.00' }}">
                    <i class="ti ti-plus-minus fs-14"></i> Adjust Balance
                </button>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-inline-flex align-items-center gap-1"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <i class="ti ti-dots-vertical"></i> Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm fs-13">
                        <li>
                            <button type="button"
                                    class="dropdown-item d-flex align-items-center gap-2 btn-open-adjust-modal"
                                    data-customer-id="{{ $customer->id }}"
                                    data-customer-name="{{ $fullName }}"
                                    data-customer-username="{{ $customer->username }}"
                                    data-customer-balance="{{ $customer->wallet ? number_format($customer->wallet->balance / 100, 2) : '0.00' }}">
                                <i class="ti ti-wallet text-primary fs-16"></i> Credit / Debit Balance
                            </button>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('admin.customers.send-password-reset', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="Send a secure password reset link to {{ $customer->email }}?">
                                @csrf
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                    <i class="ti ti-key text-info fs-16"></i> Send Password Reset Link
                                </button>
                            </form>
                        </li>
                        @if(! $customer->email_verified_at)
                            <li>
                                <form method="POST" action="{{ route('admin.customers.verify-email', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="Mark email for @{{ $customer->username }} as verified?">
                                    @csrf
                                    <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                        <i class="ti ti-circle-check text-success fs-16"></i> Manually Verify Email
                                    </button>
                                </form>
                            </li>
                        @endif
                        <li>
                            <form method="POST" action="{{ route('admin.customers.terminate-sessions', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="Terminate all active trusted devices and sessions for @{{ $customer->username }}?">
                                @csrf
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-warning">
                                    <i class="ti ti-device-mobile-off fs-16"></i> Terminate Active Sessions
                                </button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form method="POST" action="{{ route('admin.customers.toggle-lock', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="{{ $customer->is_locked ? 'Unlock customer @' . $customer->username . '?' : 'Lock customer @' . $customer->username . '? User will not be able to log in.' }}">
                                @csrf
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 {{ $customer->is_locked ? 'text-success' : 'text-danger' }}">
                                    <i class="ti {{ $customer->is_locked ? 'ti-lock-open' : 'ti-lock' }} fs-16"></i>
                                    {{ $customer->is_locked ? 'Unlock Account' : 'Lock Account' }}
                                </button>
                            </form>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger btn-delete-profile" data-id="{{ $customer->id }}" data-username="{{ $customer->username }}">
                                <i class="ti ti-trash fs-16"></i> Delete Customer
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Quick Stats Strip --}}
        <div class="row g-2 pt-2 border-top">
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Wallet Balance</span>
                    <h5 class="fs-16 fw-bold text-dark mb-0" id="header-wallet-balance">
                        {{ $headerStats['wallet_balance_formatted'] ?? '₦0.00' }}
                    </h5>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Total Orders</span>
                    <h5 class="fs-16 fw-bold text-dark mb-0">
                        {{ number_format($headerStats['orders_count'] ?? 0) }}
                        <small class="fs-11 fw-normal text-muted">({{ $headerStats['orders_spent_formatted'] ?? '₦0.00' }})</small>
                    </h5>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Support Tickets</span>
                    <h5 class="fs-16 fw-bold text-dark mb-0">
                        {{ number_format($headerStats['tickets_count'] ?? 0) }}
                        @if(($headerStats['open_tickets_count'] ?? 0) > 0)
                            <span class="badge bg-warning-subtle text-warning fs-10">{{ $headerStats['open_tickets_count'] }} open</span>
                        @endif
                    </h5>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 rounded bg-light">
                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Trusted Devices</span>
                    <h5 class="fs-16 fw-bold text-dark mb-0">
                        {{ number_format($headerStats['devices_count'] ?? 0) }}
                        <small class="fs-11 fw-normal text-muted">active</small>
                    </h5>
                </div>
            </div>
        </div>

        {{-- Sub-Navigation Tabs --}}
        <ul class="nav nav-tabs nav-tabs-custom border-bottom-0 mt-3 fs-13">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'overview' ? 'active fw-bold text-primary' : 'text-muted' }}"
                   href="{{ route('admin.customers.show', $customer) }}">
                    <i class="ti ti-id me-1"></i> Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'transactions' ? 'active fw-bold text-primary' : 'text-muted' }}"
                   href="{{ route('admin.customers.transactions', $customer) }}">
                    <i class="ti ti-arrows-left-right me-1"></i> Transactions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'orders' ? 'active fw-bold text-primary' : 'text-muted' }}"
                   href="{{ route('admin.customers.orders', $customer) }}">
                    <i class="ti ti-shopping-cart me-1"></i> Orders
                    @if(($headerStats['orders_count'] ?? 0) > 0)
                        <span class="badge bg-light text-dark rounded-pill ms-1">{{ $headerStats['orders_count'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab === 'tickets' ? 'active fw-bold text-primary' : 'text-muted' }}"
                   href="{{ route('admin.customers.tickets', $customer) }}">
                    <i class="ti ti-headset me-1"></i> Support Tickets
                    @if(($headerStats['open_tickets_count'] ?? 0) > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $headerStats['open_tickets_count'] }}</span>
                    @endif
                </a>
            </li>
        </ul>
    </div>
</div>
