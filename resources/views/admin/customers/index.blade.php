@extends('layouts.admin')

@php
    $title = 'Customers';
@endphp

@section('styles')
    <!-- SweetAlert2 css -->
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
    <!-- DataTables Bootstrap 5 css -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" />
    <style>
        .btn-soft-primary {
            background-color: rgba(var(--osen-primary-rgb, 114, 102, 237), 0.12);
            color: var(--osen-primary, #7266ed);
            border: 1px solid transparent;
        }
        .btn-soft-primary:hover {
            background-color: var(--osen-primary, #7266ed);
            color: #fff;
        }
        .btn-soft-warning {
            background-color: rgba(255, 171, 0, 0.12);
            color: #d99b00;
            border: 1px solid transparent;
        }
        .btn-soft-warning:hover {
            background-color: #ffab00;
            color: #fff;
        }
        .btn-soft-success {
            background-color: rgba(40, 199, 111, 0.12);
            color: #28c76f;
            border: 1px solid transparent;
        }
        .btn-soft-success:hover {
            background-color: #28c76f;
            color: #fff;
        }
        .btn-soft-danger {
            background-color: rgba(234, 84, 85, 0.12);
            color: #ea5455;
            border: 1px solid transparent;
        }
        .btn-soft-danger:hover {
            background-color: #ea5455;
            color: #fff;
        }
        .dataTables_wrapper .dataTables_paginate .page-link {
            font-size: 0.8125rem;
        }
    </style>
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Customers',
    'subtitle' => 'View, filter, manage, and audit registered platform customers, balances, and accounts.',
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

<!-- 4 Stat Cards Row -->
<div class="row g-3 mb-3">
    <!-- Card 1: Total Customers -->
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Total Customers</span>
                        <h3 class="my-1 fs-22 text-dark">{{ number_format($stats['total'] ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">Registered users</p>
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

    <!-- Card 2: Verified Emails -->
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Verified Emails</span>
                        <h3 class="my-1 fs-22 text-dark">{{ number_format($stats['verified'] ?? 0) }}</h3>
                        <p class="mb-0 text-success fs-12">
                            <i class="ti ti-badge-check"></i>
                            @if(($stats['total'] ?? 0) > 0)
                                {{ round((($stats['verified'] ?? 0) / $stats['total']) * 100) }}% rate
                            @else
                                0% rate
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

    <!-- Card 3: Virtual Accounts -->
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Virtual Accounts</span>
                        <h3 class="my-1 fs-22 text-dark">{{ number_format($stats['with_virtual_account'] ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">Dedicated bank accounts</p>
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

    <!-- Card 4: Locked Accounts -->
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Locked Accounts</span>
                        <h3 class="my-1 fs-22 text-danger">{{ number_format($stats['locked'] ?? 0) }}</h3>
                        <p class="mb-0 text-danger fs-12">
                            <i class="ti ti-lock"></i> Suspended users
                        </p>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-20 p-2">
                            <i class="ti ti-lock"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Status Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="ti ti-search text-muted"></i>
                    </span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           class="form-control border-start-0 ps-0"
                           placeholder="Search username, email, phone, name, or ULID...">
                </div>
            </div>

            <div class="col-md-4">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active Accounts</option>
                    <option value="locked" {{ $status === 'locked' ? 'selected' : '' }}>Locked Accounts</option>
                    <option value="verified" {{ $status === 'verified' ? 'selected' : '' }}>Verified Email Only</option>
                    <option value="unverified" {{ $status === 'unverified' ? 'selected' : '' }}>Unverified Email Only</option>
                    <option value="has_account" {{ $status === 'has_account' ? 'selected' : '' }}>Has Virtual Account</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if($search || $status)
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-light" title="Reset Filters">
                        <i class="ti ti-x"></i> Clear
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Customers DataTable Card -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="customers-datatable" class="table table-hover table-nowrap align-middle mb-0 w-100">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Wallet Balance</th>
                        <th>Dedicated Account</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end" style="min-width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr id="customer-row-{{ $customer->id }}">
                            <td class="text-muted fs-12">
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
                            <td class="status-cell">
                                @if($customer->is_locked)
                                    <span class="badge bg-danger-subtle text-danger fs-11">
                                        <i class="ti ti-lock me-1"></i> Locked
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success fs-11">
                                        <i class="ti ti-circle-check me-1"></i> Active
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="fs-12 text-muted">
                                    {{ $customer->created_at ? $customer->created_at->format('M d, Y') : '—' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <!-- View Action -->
                                    <a href="{{ route('admin.customers.show', $customer) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="View customer profile">
                                        <i class="ti ti-eye"></i> View
                                    </a>

                                    <!-- Lock / Unlock Action -->
                                    <button type="button"
                                            class="btn btn-sm {{ $customer->is_locked ? 'btn-soft-success' : 'btn-soft-warning' }} btn-toggle-lock"
                                            data-id="{{ $customer->id }}"
                                            data-username="{{ $customer->username }}"
                                            data-locked="{{ $customer->is_locked ? '1' : '0' }}"
                                            title="{{ $customer->is_locked ? 'Unlock customer' : 'Lock customer' }}">
                                        <i class="ti {{ $customer->is_locked ? 'ti-lock-open' : 'ti-lock' }}"></i>
                                        <span class="lock-text">{{ $customer->is_locked ? 'Unlock' : 'Lock' }}</span>
                                    </button>

                                    <!-- Delete Action with Icon -->
                                    <button type="button"
                                            class="btn btn-sm btn-soft-danger btn-delete-customer"
                                            data-id="{{ $customer->id }}"
                                            data-username="{{ $customer->username }}"
                                            title="Delete customer">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-users-minus fs-36 mb-2 d-block text-secondary"></i>
                                    <h5 class="fs-16">No customers found</h5>
                                    <p class="fs-13 mb-0">
                                        @if($search || $status)
                                            No customers matched your filter criteria.
                                            <a href="{{ route('admin.customers.index') }}" class="d-block mt-2">Clear filters</a>
                                        @else
                                            No customers registered yet.
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

@section('scripts')
    <!-- SweetAlert2 js -->
    <script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>
    <!-- DataTables js -->
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize DataTable
            if ($('#customers-datatable tbody tr td').length > 1) {
                $('#customers-datatable').DataTable({
                    paging: false, // Server handles pagination via Laravel links
                    info: false,
                    responsive: true,
                    searching: false, // Handled by server-side query form
                    ordering: true,
                    columnDefs: [
                        { orderable: false, targets: [0, 7] } // Disable ordering on # and Actions
                    ],
                    language: {
                        emptyTable: "No customers found"
                    }
                });
            }

            // Lock / Unlock Customer Handler
            $(document).on('click', '.btn-toggle-lock', function () {
                const btn = $(this);
                const customerId = btn.data('id');
                const username = btn.data('username');
                const isCurrentlyLocked = String(btn.data('locked')) === '1';
                const actionVerb = isCurrentlyLocked ? 'unlock' : 'lock';

                Swal.fire({
                    title: (isCurrentlyLocked ? 'Unlock Customer?' : 'Lock Customer?'),
                    text: `Are you sure you want to ${actionVerb} customer @${username}? ${isCurrentlyLocked ? 'They will be allowed to log in and transact.' : 'They will be prevented from logging into the platform.'}`,
                    icon: (isCurrentlyLocked ? 'question' : 'warning'),
                    showCancelButton: true,
                    confirmButtonColor: (isCurrentlyLocked ? '#28c76f' : '#ffab00'),
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: `Yes, ${actionVerb} user`,
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        btn.prop('disabled', true);

                        $.ajax({
                            url: `/admininistator/customers/${customerId}/toggle-lock`,
                            type: 'POST',
                            dataType: 'json',
                            success: function (res) {
                                const newLockedState = res.is_locked;
                                btn.data('locked', newLockedState ? '1' : '0');

                                const row = $(`#customer-row-${customerId}`);
                                const statusCell = row.find('.status-cell');

                                if (newLockedState) {
                                    btn.removeClass('btn-soft-warning').addClass('btn-soft-success');
                                    btn.find('i').removeClass('ti-lock').addClass('ti-lock-open');
                                    btn.find('.lock-text').text('Unlock');
                                    btn.attr('title', 'Unlock customer');

                                    statusCell.html(`
                                        <span class="badge bg-danger-subtle text-danger fs-11">
                                            <i class="ti ti-lock me-1"></i> Locked
                                        </span>
                                    `);
                                } else {
                                    btn.removeClass('btn-soft-success').addClass('btn-soft-warning');
                                    btn.find('i').removeClass('ti-lock-open').addClass('ti-lock');
                                    btn.find('.lock-text').text('Lock');
                                    btn.attr('title', 'Lock customer');

                                    statusCell.html(`
                                        <span class="badge bg-success-subtle text-success fs-11">
                                            <i class="ti ti-circle-check me-1"></i> Active
                                        </span>
                                    `);
                                }

                                toastr.success(res.message);
                            },
                            error: function (xhr) {
                                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to update lock state.';
                                toastr.error(msg);
                            },
                            complete: function () {
                                btn.prop('disabled', false);
                            }
                        });
                    }
                });
            });

            // Delete Customer Handler
            $(document).on('click', '.btn-delete-customer', function () {
                const btn = $(this);
                const customerId = btn.data('id');
                const username = btn.data('username');

                Swal.fire({
                    title: 'Delete Customer?',
                    text: `Are you sure you want to permanently delete customer @${username}? This will remove their wallet, virtual account, and all associated data. This action cannot be undone!`,
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#ea5455',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, permanently delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        btn.prop('disabled', true);

                        $.ajax({
                            url: `/admininistator/customers/${customerId}`,
                            type: 'DELETE',
                            dataType: 'json',
                            success: function (res) {
                                toastr.success(res.message);
                                const row = $(`#customer-row-${customerId}`);
                                row.fadeOut(300, function () {
                                    $(this).remove();
                                });
                            },
                            error: function (xhr) {
                                const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to delete customer.';
                                toastr.error(msg);
                                btn.prop('disabled', false);
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
