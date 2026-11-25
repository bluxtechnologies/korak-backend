@extends('layouts.admin')

@php
    $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username;
    $title = "Support Tickets - @{$customer->username}";
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => "Support Tickets: {$fullName}",
    'subtitle' => "Customer inquiry tickets, help desk requests, and direct communication threads for @{$customer->username}.",
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Tickets' => null,
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
@include('admin.customers.partials.header', ['activeTab' => 'tickets'])

{{-- Ticket Stats Row --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Total Tickets</span>
                        <h4 class="my-1 fs-20 text-dark fw-bold">{{ number_format($ticketStats['total'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">All-time inquiries</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-primary-subtle text-primary rounded fs-18 p-2">
                            <i class="ti ti-ticket"></i>
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
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Open Tickets</span>
                        <h4 class="my-1 fs-20 text-danger fw-bold">{{ number_format($ticketStats['open'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">Awaiting attention</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-danger-subtle text-danger rounded fs-18 p-2">
                            <i class="ti ti-alert-circle"></i>
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
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">In Progress</span>
                        <h4 class="my-1 fs-20 text-warning fw-bold">{{ number_format($ticketStats['in_progress'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">Being resolved</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-warning-subtle text-warning rounded fs-18 p-2">
                            <i class="ti ti-clock"></i>
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
                        <span class="text-muted fs-12 text-uppercase fw-semibold d-block">Resolved / Closed</span>
                        <h4 class="my-1 fs-20 text-success fw-bold">{{ number_format($ticketStats['resolved'] ?? 0) }}</h4>
                        <span class="text-muted fs-11">Successfully completed</span>
                    </div>
                    <div class="avatar-sm">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-18 p-2">
                            <i class="ti ti-circle-check"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Tickets Table Card --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3">
        <form method="GET" action="{{ route('admin.customers.tickets', $customer) }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="ti ti-search"></i></span>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           class="form-control"
                           placeholder="Search ticket ref or subject...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="resolved" {{ $status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <option value="billing" {{ $category === 'billing' ? 'selected' : '' }}>Billing & Wallet</option>
                    <option value="technical" {{ $category === 'technical' ? 'selected' : '' }}>Technical</option>
                    <option value="account" {{ $category === 'account' ? 'selected' : '' }}>Account & Security</option>
                    <option value="vtu" {{ $category === 'vtu' ? 'selected' : '' }}>VTU / Order Delivery</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Filter Tickets">
                    <i class="ti ti-filter"></i>
                </button>
                @if($search || $status || $category)
                    <a href="{{ route('admin.customers.tickets', $customer) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
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
                        <th>Reference</th>
                        <th>Subject & Category</th>
                        <th>Messages</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Activity</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $t)
                        <tr>
                            <td>
                                <span class="font-monospace text-dark fw-bold fs-12">{{ $t->reference }}</span>
                                @if($t->has_unread_reply)
                                    <span class="badge bg-danger rounded-pill fs-10 ms-1">Customer Unread</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.customers.tickets.show', [$customer, $t]) }}" class="text-dark fw-semibold text-decoration-none d-block">
                                    {{ $t->subject }}
                                </a>
                                <span class="badge bg-light text-muted fs-10 text-uppercase">{{ $t->category->value ?? $t->category }}</span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary fs-11">
                                    <i class="ti ti-messages me-1"></i> {{ $t->messages_count }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $statusValue = $t->status->value ?? (string) $t->status;
                                    $badgeClass = match($statusValue) {
                                        'open' => 'bg-danger-subtle text-danger',
                                        'in_progress' => 'bg-warning-subtle text-warning',
                                        'resolved' => 'bg-success-subtle text-success',
                                        'closed' => 'bg-secondary-subtle text-secondary',
                                        default => 'bg-light text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} fs-11">
                                    {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                                </span>
                            </td>
                            <td>
                                <span class="text-muted fs-12">{{ $t->created_at->format('M d, Y') }}</span>
                            </td>
                            <td>
                                <span class="text-muted fs-12">{{ $t->updated_at->diffForHumans() }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.customers.tickets.show', [$customer, $t]) }}" class="btn btn-sm btn-soft-primary">
                                    <i class="ti ti-message-reply"></i> Open Thread
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-headset-off fs-36 mb-2 d-block text-secondary"></i>
                                    <h6 class="fs-15">No support tickets found</h6>
                                    <p class="fs-12 mb-0">
                                        @if($search || $status || $category)
                                            No tickets match your filter criteria.
                                            <a href="{{ route('admin.customers.tickets', $customer) }}" class="d-block mt-2">Clear filters</a>
                                        @else
                                            This customer has not submitted any support tickets.
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

    @if($tickets->hasPages())
        <div class="card-footer bg-transparent border-top py-2 px-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="fs-12 text-muted">
                    Showing {{ $tickets->firstItem() ?? 0 }} to {{ $tickets->lastItem() ?? 0 }} of {{ $tickets->total() }} tickets
                </span>
                {{ $tickets->links() }}
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
