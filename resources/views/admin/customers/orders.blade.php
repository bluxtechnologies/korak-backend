@extends('layouts.admin')

@php
    $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username;
    $title = "Orders - @{$customer->username}";
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => "Orders: {$fullName}",
    'subtitle' => "All bill payments, airtime topups, data bundles, utilities, and VTU transactions for @{$customer->username}.",
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Orders' => null,
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
@include('admin.customers.partials.header', ['activeTab' => 'orders'])

{{-- Order Stats Row --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Total Orders</span>
                        <h4 class="my-1 fs-20 text-dark fw-bold">{{ number_format($orderStats['total_count'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">All-time count</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-18 p-2">
                            <i class="ti ti-shopping-cart"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Total Spent</span>
                        <h4 class="my-1 fs-20 text-success fw-bold">₦{{ number_format(($orderStats['total_amount'] ?? 0) / 100, 2) }}</h4>
                        <span class="text-muted fs-11">Completed volume</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-18 p-2">
                            <i class="ti ti-cash"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Completed</span>
                        <h4 class="my-1 fs-20 text-info fw-bold">{{ number_format($orderStats['completed'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">Delivered successfully</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-info-subtle text-info rounded fs-18 p-2">
                            <i class="ti ti-circle-check"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Failed / Refunded</span>
                        <h4 class="my-1 fs-20 text-warning fw-bold">{{ number_format($orderStats['failed'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">Unsuccessful orders</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-18 p-2">
                            <i class="ti ti-rotate-clockwise"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Orders Table Card --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3">
        <form method="GET" action="{{ route('admin.customers.orders', $customer) }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ti ti-search"></i></span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           class="form-control"
                           placeholder="Search ref, provider ref, identifier, or token...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    <option value="refunded" {{ $status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <option value="airtime" {{ $category === 'airtime' ? 'selected' : '' }}>Airtime</option>
                    <option value="data" {{ $category === 'data' ? 'selected' : '' }}>Mobile Data</option>
                    <option value="electricity" {{ $category === 'electricity' ? 'selected' : '' }}>Electricity</option>
                    <option value="cable_tv" {{ $category === 'cable_tv' ? 'selected' : '' }}>Cable TV</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Filter Orders">
                    <i class="ti ti-filter"></i>
                </button>
                @if($search || $status || $category)
                    <a href="{{ route('admin.customers.orders', $customer) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
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
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Service / Package</th>
                        <th>Target Identifier</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Token / Key</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <span class="fw-medium text-dark d-block">{{ $order->created_at->format('M d, Y') }}</span>
                                <small class="text-muted fs-11">{{ $order->created_at->format('H:i') }}</small>
                            </td>
                            <td>
                                <span class="font-monospace text-dark fs-12 d-block">{{ $order->reference }}</span>
                                @if($order->provider_reference)
                                    <small class="text-muted font-monospace fs-10 d-block">{{ $order->provider_reference }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold text-dark d-block">{{ $order->service_name }}</span>
                                <span class="badge bg-light text-muted fs-10 text-uppercase">{{ $order->category }}</span>
                                @if($order->package_code)
                                    <small class="text-muted font-monospace fs-10">({{ $order->package_code }})</small>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace text-dark fw-medium">{{ $order->customer_identifier }}</span>
                                @if($order->customer_name)
                                    <small class="text-muted fs-11 d-block">{{ $order->customer_name }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-13">₦{{ number_format($order->amount / 100, 2) }}</span>
                                @if($order->discount_amount > 0)
                                    <small class="text-success fs-10 d-block">-₦{{ number_format($order->discount_amount / 100, 2) }} discount</small>
                                @endif
                            </td>
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
                                <span class="badge {{ $badgeClass }} fs-11">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td>
                                @if($order->token)
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <span class="font-monospace text-dark fw-bold fs-12 bg-light px-2 py-1 rounded">{{ $order->token }}</span>
                                    </div>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-shopping-cart-off fs-36 mb-2 d-block text-secondary"></i>
                                    <h6 class="fs-15">No orders found</h6>
                                    <p class="fs-12 mb-0">
                                        @if($search || $status || $category)
                                            No orders match your filter criteria.
                                            <a href="{{ route('admin.customers.orders', $customer) }}" class="d-block mt-2">Clear filters</a>
                                        @else
                                            This customer has not placed any bill or VTU orders yet.
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

    @if($orders->hasPages())
        <div class="card-footer bg-transparent border-top py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="fs-12 text-muted">
                    Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
                </span>
                {{ $orders->links() }}
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
