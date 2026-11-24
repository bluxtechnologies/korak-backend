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

<div class="row g-3">
    <!-- Left Column: Customer Profile & Quick Actions -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="avatar-lg mx-auto mb-2">
                    <span class="avatar-title bg-light text-primary rounded-circle fw-bold fs-24 d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px;">
                        {{ strtoupper(substr($customer->first_name ?? $customer->username, 0, 1)) }}
                    </span>
                </div>
                <h5 class="fs-16 mb-1 text-dark fw-bold">
                    {{ trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username }}
                </h5>
                <p class="text-muted fs-13 mb-2">@<span>{{ $customer->username }}</span></p>

                <div class="mb-3">
                    @if($customer->is_locked)
                        <span class="badge bg-danger-subtle text-danger fs-12 px-2 py-1">
                            <i class="ti ti-lock me-1"></i> Account Locked
                        </span>
                        @if($customer->locked_at)
                            <small class="text-muted d-block mt-1 fs-11">
                                Since {{ $customer->locked_at->format('M d, Y H:i') }}
                            </small>
                        @endif
                    @else
                        <span class="badge bg-success-subtle text-success fs-12 px-2 py-1">
                            <i class="ti ti-circle-check me-1"></i> Active Account
                        </span>
                    @endif
                </div>

                <div class="d-flex justify-content-center gap-2">
                    <form method="POST" action="{{ route('admin.customers.toggle-lock', $customer) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $customer->is_locked ? 'btn-success' : 'btn-warning' }}">
                            <i class="ti {{ $customer->is_locked ? 'ti-lock-open' : 'ti-lock' }} me-1"></i>
                            {{ $customer->is_locked ? 'Unlock User' : 'Lock User' }}
                        </button>
                    </form>

                    <button type="button" class="btn btn-sm btn-outline-danger btn-delete-profile" data-id="{{ $customer->id }}" data-username="{{ $customer->username }}">
                        <i class="ti ti-trash me-1"></i> Delete
                    </button>
                </div>
            </div>

            <div class="card-body border-top">
                <h6 class="fs-13 text-uppercase text-muted fw-semibold mb-3">Contact Information</h6>
                <ul class="list-unstyled mb-0 fs-13">
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Email:</span>
                        <span class="fw-medium text-dark">{{ $customer->email }}</span>
                    </li>
                    <li class="mb-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted">Email Verified:</span>
                        @if($customer->email_verified_at)
                            <span class="text-success"><i class="ti ti-check-circle me-1"></i> {{ $customer->email_verified_at->format('M d, Y') }}</span>
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
                    <li class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Registered On:</span>
                        <span class="text-dark">{{ $customer->created_at ? $customer->created_at->format('M d, Y H:i') : '—' }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Wallet, Virtual Account & Security Details -->
    <div class="col-lg-8">
        <!-- Wallet Card -->
        <div class="card mb-3">
            <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-wallet me-1 text-primary"></i> Wallet & Ledger
                </h5>
                @if($customer->wallet)
                    <span class="badge bg-light text-dark fs-12">Currency: {{ $customer->wallet->currency }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($customer->wallet)
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded mb-3">
                        <div>
                            <span class="text-muted fs-12 text-uppercase fw-semibold">Available Balance</span>
                            <h2 class="my-1 text-dark fs-28 fw-bold">
                                ₦{{ number_format($customer->wallet->balance / 100, 2) }}
                            </h2>
                            <small class="text-muted fs-11">Raw minor units: {{ number_format($customer->wallet->balance) }} kobo</small>
                        </div>
                        <div class="avatar-md">
                            <span class="avatar-title bg-primary text-white rounded fs-24 p-3">
                                <i class="ti ti-cash"></i>
                            </span>
                        </div>
                    </div>

                    <h6 class="fs-13 text-muted text-uppercase fw-semibold mb-2">Recent Ledger Entries</h6>
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
                                            <td>{{ $entry->created_at->format('M d, Y H:i') }}</td>
                                            <td>
                                                <span class="badge {{ ($entry->type->value ?? '') === 'credit' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                    {{ ucfirst($entry->type->value ?? 'entry') }}
                                                </span>
                                            </td>
                                            <td class="fw-semibold">
                                                ₦{{ number_format($entry->amount / 100, 2) }}
                                            </td>
                                            <td class="text-muted">{{ $entry->description ?? '—' }}</td>
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

        <!-- Dedicated Virtual Account Card -->
        <div class="card mb-3">
            <div class="card-header border-bottom">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-building-bank me-1 text-info"></i> Dedicated Virtual Account
                </h5>
            </div>
            <div class="card-body">
                @if($customer->virtualAccount)
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted fs-12 mb-1">Bank Name</label>
                            <h6 class="fs-15 text-dark fw-bold mb-0">{{ $customer->virtualAccount->bank_name }}</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fs-12 mb-1">Account Number</label>
                            <h6 class="fs-16 font-monospace text-dark fw-bold mb-0">{{ $customer->virtualAccount->account_number }}</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fs-12 mb-1">Account Name</label>
                            <p class="fs-13 text-dark mb-0">{{ $customer->virtualAccount->account_name ?: '—' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fs-12 mb-1">Provider & Reference</label>
                            <p class="fs-12 text-muted font-monospace mb-0">{{ ucfirst($customer->virtualAccount->provider) }} ({{ $customer->virtualAccount->account_reference }})</p>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="ti ti-building-bank fs-32 mb-1 d-block text-secondary"></i>
                        <h6 class="fs-14">No Dedicated Virtual Account</h6>
                        <p class="fs-13 mb-0">The customer has not generated dedicated bank account details yet.</p>
                    </div>
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
                    2FA Device Verification: {{ $customer->device_verification_enabled ? 'Enabled' : 'Disabled' }}
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
                                    <th>Last Seen</th>
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
                                        <td class="text-muted">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : '—' }}</td>
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
@endsection

@section('scripts')
    <script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>
    <script>
        $(document).on('click', '.btn-delete-profile', function () {
            const customerId = $(this).data('id');
            const username = $(this).data('username');

            Swal.fire({
                title: 'Delete Customer?',
                text: `Are you sure you want to delete customer @${username}? This action is irreversible.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ea5455',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, permanently delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = $('<form>', {
                        action: `/admininistator/customers/${customerId}`,
                        method: 'POST'
                    }).append(
                        $('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') }),
                        $('<input>', { type: 'hidden', name: '_method', value: 'DELETE' })
                    );
                    $('body').append(form);
                    form.submit();
                }
            });
        });
    </script>
@endsection
