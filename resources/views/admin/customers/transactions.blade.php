@extends('layouts.admin')

@php
    $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username;
    $title = "Transactions - @{$customer->username}";
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => "Transactions: {$fullName}",
    'subtitle' => "Full wallet ledger entries, funding deposits, order debits, refunds, and manual adjustments for @{$customer->username}.",
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Transactions' => null,
    ],
    'actions' => [
        [
            'label' => 'Back to Profile',
            'icon' => 'ti ti-arrow-left',
            'class' => 'btn btn-outline-secondary',
            'url' => route('admin.customers.show', $customer),
        ],
    ],
])

{{-- Persistent Customer 360 Header --}}
@include('admin.customers.partials.header', ['activeTab' => 'transactions'])

{{-- Transaction Stats Row --}}
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Total Credited</span>
                        <h4 class="my-1 fs-20 text-success fw-bold">
                            ₦{{ number_format(($transStats['total_credits'] ?? 0) / 100, 2) }}
                        </h4>
                        <span class="text-muted fs-11">Deposits & refunds</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-18 p-2">
                            <i class="ti ti-arrow-down-left"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Total Debited</span>
                        <h4 class="my-1 fs-20 text-danger fw-bold">
                            ₦{{ number_format(($transStats['total_debits'] ?? 0) / 100, 2) }}
                        </h4>
                        <span class="text-muted fs-11">Purchases & charges</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-18 p-2">
                            <i class="ti ti-arrow-up-right"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Manual Adjustments</span>
                        <h4 class="my-1 fs-20 text-primary fw-bold">
                            {{ number_format($transStats['manual_adjustments'] ?? 0) }}
                        </h4>
                        <span class="text-muted fs-11">Admin credit / debit entries</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-18 p-2">
                            <i class="ti ti-plus-minus"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Transactions Table Card --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3">
        <form method="GET" action="{{ route('admin.customers.transactions', $customer) }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ti ti-search"></i></span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           class="form-control"
                           placeholder="Search reference or description...">
                </div>
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select form-select-sm">
                    <option value="">All Directions</option>
                    <option value="credit" {{ $direction === 'credit' ? 'selected' : '' }}>Credits (+)</option>
                    <option value="debit" {{ $direction === 'debit' ? 'selected' : '' }}>Debits (-)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="funding" {{ $type === 'funding' ? 'selected' : '' }}>Funding</option>
                    <option value="order_debit" {{ $type === 'order_debit' ? 'selected' : '' }}>Order Debit</option>
                    <option value="refund" {{ $type === 'refund' ? 'selected' : '' }}>Refund</option>
                    <option value="manual_adjustment" {{ $type === 'manual_adjustment' ? 'selected' : '' }}>Manual Adjustment</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm" title="From Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm" title="To Date">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Apply Filters">
                    <i class="ti ti-filter"></i>
                </button>
                @if($search || $direction || $type || $dateFrom || $dateTo)
                    <a href="{{ route('admin.customers.transactions', $customer) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                        <i class="ti ti-x"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th>Description / Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgerEntries as $entry)
                        <tr>
                            <td>
                                <span class="fw-medium text-dark d-block">{{ $entry->created_at->format('M d, Y') }}</span>
                                <small class="text-muted fs-11">{{ $entry->created_at->format('H:i:s') }}</small>
                            </td>
                            <td>
                                <span class="font-monospace text-dark fs-12">{{ $entry->reference ?: '—' }}</span>
                            </td>
                            <td>
                                @php
                                    $isCredit = ($entry->direction->value ?? '') === 'credit';
                                    $typeBadge = match($entry->type->value ?? '') {
                                        'funding' => 'bg-info-subtle text-info',
                                        'order_debit' => 'bg-secondary-subtle text-secondary',
                                        'refund' => 'bg-warning-subtle text-warning',
                                        'manual_adjustment' => 'bg-primary-subtle text-primary',
                                        default => 'bg-light text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $isCredit ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} fs-11 me-1">
                                    {{ $isCredit ? 'Credit' : 'Debit' }}
                                </span>
                                <span class="badge {{ $typeBadge }} fs-11">
                                    {{ ucfirst(str_replace('_', ' ', $entry->type->value ?? 'entry')) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold fs-13 {{ $isCredit ? 'text-success' : 'text-danger' }}">
                                    {{ $isCredit ? '+' : '-' }}₦{{ number_format($entry->amount / 100, 2) }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark fs-12">
                                    ₦{{ number_format(($entry->balance_after ?? 0) / 100, 2) }}
                                </span>
                            </td>
                            <td>
                                <span class="text-dark d-block">{{ $entry->description ?: '—' }}</span>
                                @if(!empty($entry->metadata['reason']))
                                    <small class="text-muted fs-11 d-block"><span class="fw-semibold">Reason:</span> {{ $entry->metadata['reason'] }}</small>
                                @endif
                                @if(!empty($entry->metadata['admin_username']))
                                    <small class="text-muted fs-10 d-block"><i class="ti ti-user me-1"></i>Admin: @<span>{{ $entry->metadata['admin_username'] }}</span></small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-receipt-off fs-36 mb-2 d-block text-secondary"></i>
                                    <h6 class="fs-15">No transactions found</h6>
                                    <p class="fs-12 mb-0">
                                        @if($search || $direction || $type || $dateFrom || $dateTo)
                                            No transactions match your active filters.
                                            <a href="{{ route('admin.customers.transactions', $customer) }}" class="d-block mt-2">Clear filters</a>
                                        @else
                                            This customer has no wallet transactions recorded yet.
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

    @if($ledgerEntries->hasPages())
        <div class="card-footer bg-transparent border-top py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="fs-12 text-muted">
                    Showing {{ $ledgerEntries->firstItem() ?? 0 }} to {{ $ledgerEntries->lastItem() ?? 0 }} of {{ $ledgerEntries->total() }} entries
                </span>
                {{ $ledgerEntries->links() }}
            </div>
        </div>
    @endif
</div>

{{-- Adjustment Modal & Script Partials --}}
@include('admin.customers.partials.credit-debit-modal')
@endsection

@section('scripts')
    <script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>
    @include('admin.customers.partials.scripts')
@endsection
