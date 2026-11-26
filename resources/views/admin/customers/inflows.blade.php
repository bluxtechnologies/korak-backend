@extends('layouts.admin')

@php
    $title = 'Bank Inflows - @' . $customer->username;
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Bank Inflows: @' . $customer->username,
    'subtitle' => 'Virtual account bank transfer deposits, fee accounting, payer information, and manual reconciliation.',
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Bank Inflows' => null,
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
@include('admin.customers.partials.header', ['activeTab' => 'inflows'])

{{-- Financial Summary Strip --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Gross Bank Deposits</span>
            <h4 class="fs-18 fw-bold text-dark mb-0">₦{{ number_format(($summary['total_gross'] ?? 0) / 100, 2) }}</h4>
            <small class="text-muted fs-11">{{ number_format($summary['total_inflow_count'] ?? 0) }} transfers</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Fees Deducted</span>
            <h4 class="fs-18 fw-bold text-warning mb-0">₦{{ number_format(($summary['total_fee'] ?? 0) / 100, 2) }}</h4>
            <small class="text-muted fs-11">Tiered deposit service fee</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Net Wallet Credited</span>
            <h4 class="fs-18 fw-bold text-success mb-0">₦{{ number_format(($summary['total_net'] ?? 0) / 100, 2) }}</h4>
            <small class="text-muted fs-11">Available for user spending</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Pending Reconciliation</span>
            <h4 class="fs-18 fw-bold text-dark mb-0">
                {{ number_format($summary['pending_count'] ?? 0) }}
                @if(($summary['pending_count'] ?? 0) > 0)
                    <span class="badge bg-warning-subtle text-warning fs-10">Attention</span>
                @endif
            </h4>
            <small class="text-muted fs-11">Awaiting provider confirmation</small>
        </div>
    </div>
</div>

{{-- Filters & Search Card --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('admin.customers.inflows', $customer) }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="ti ti-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference, provider ref, sender name..." value="{{ $search }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="successful" {{ $status === 'successful' ? 'selected' : '' }}>Successful</option>
                    <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-md-4 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="ti ti-filter fs-14"></i> Filter
                </button>
                @if($search || $status)
                    <a href="{{ route('admin.customers.inflows', $customer) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                @endif
                <a href="{{ route('admin.customers.inflows.export', $customer) }}" class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1">
                    <i class="ti ti-file-spreadsheet fs-15"></i> Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Inflows Ledger Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header border-bottom d-flex align-items-center justify-content-between">
        <h5 class="card-title fs-14 fw-bold mb-0">Bank Transfer Funding Records</h5>
        <span class="text-muted fs-12">Showing {{ $inflows->firstItem() ?? 0 }} - {{ $inflows->lastItem() ?? 0 }} of {{ $inflows->total() }} records</span>
    </div>
    <div class="card-body p-0">
        @if($inflows->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-12">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Payer / Sender</th>
                            <th>Gross Deposit</th>
                            <th>Fee</th>
                            <th>Net Credited</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inflows as $inflow)
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark font-monospace d-block">{{ $inflow->reference }}</span>
                                    @if($inflow->provider_reference)
                                        <small class="text-muted font-monospace fs-10">Ext: {{ $inflow->provider_reference }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="d-block text-dark">{{ $inflow->created_at->format('M d, Y') }}</span>
                                    <small class="text-muted">{{ $inflow->created_at->format('H:i:s') }}</small>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark d-block">{{ $inflow->sender_name ?: 'Bank Transfer' }}</span>
                                    <small class="text-muted font-monospace fs-10">{{ ucfirst($inflow->provider ?? 'bank') }}</small>
                                </td>
                                <td class="fw-bold text-dark">
                                    ₦{{ number_format(($inflow->gross_amount ?? $inflow->amount) / 100, 2) }}
                                </td>
                                <td class="text-warning fw-semibold">
                                    -₦{{ number_format($inflow->fee / 100, 2) }}
                                </td>
                                <td class="text-success fw-bold">
                                    ₦{{ number_format($inflow->amount / 100, 2) }}
                                </td>
                                <td>
                                    @php
                                        $statusStr = $inflow->status->value ?? (string) $inflow->status;
                                        $badgeClass = match($statusStr) {
                                            'successful', 'completed' => 'bg-success-subtle text-success',
                                            'pending' => 'bg-warning-subtle text-warning',
                                            'failed' => 'bg-danger-subtle text-danger',
                                            default => 'bg-secondary-subtle text-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ ucfirst($statusStr) }}</span>
                                </td>
                                <td class="text-end">
                                    @if($statusStr === 'pending' || $statusStr === 'failed')
                                        <form method="POST" action="{{ route('admin.customers.inflows.reconcile', [$customer, $inflow]) }}" class="d-inline form-confirm-action" data-confirm-text="Re-query FinityPay and reconcile transfer #{{ $inflow->reference }}? If verified, the customer wallet will be credited.">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-primary btn-xs d-inline-flex align-items-center gap-1" title="Check with provider">
                                                <i class="ti ti-refresh fs-12"></i> Reconcile
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted fs-11"><i class="ti ti-check text-success"></i> Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($inflows->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $inflows->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-5 text-muted">
                <i class="ti ti-building-bank fs-36 text-secondary mb-2 d-block"></i>
                <p class="fs-13 mb-0">No bank transfer deposits found for this customer.</p>
            </div>
        @endif
    </div>
</div>

{{-- Modals & Scripts --}}
@include('admin.customers.partials.credit-debit-modal')
@include('admin.customers.partials.edit-profile-modal')
@endsection

@section('scripts')
<script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>
@include('admin.customers.partials.scripts')
@endsection
