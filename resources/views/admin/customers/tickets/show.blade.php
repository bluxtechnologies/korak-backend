@extends('layouts.admin')

@php
    $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $customer->username;
    $title = "Ticket {$ticket->reference} - @{$customer->username}";
    $statusValue = $ticket->status->value ?? (string) $ticket->status;
    $badgeClass = match($statusValue) {
        'open' => 'bg-danger-subtle text-danger',
        'in_progress' => 'bg-warning-subtle text-warning',
        'resolved' => 'bg-success-subtle text-success',
        'closed' => 'bg-secondary-subtle text-secondary',
        default => 'bg-light text-dark',
    };
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
    <style>
        .chat-bubble-customer {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px 12px 12px 2px;
        }
        .chat-bubble-admin {
            background-color: #f0edff;
            border: 1px solid #dcd7fc;
            border-radius: 12px 12px 2px 12px;
        }
    </style>
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => "Ticket: {$ticket->reference}",
    'subtitle' => "Conversation thread with @{$customer->username} regarding \"{$ticket->subject}\".",
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Tickets' => route('admin.customers.tickets', $customer),
        $ticket->reference => null,
    ],
    'actions' => [
        [
            'label' => 'Back to All Tickets',
            'icon' => 'ti ti-arrow-left',
            'class' => 'btn btn-outline-secondary',
            'url' => route('admin.customers.tickets', $customer),
        ],
    ],
])

{{-- Persistent Customer 360 Header --}}
@include('admin.customers.partials.header', ['activeTab' => 'tickets'])

<div class="row g-3">
    {{-- Left / Top: Ticket Details & Status Controls --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-info-circle me-1 text-primary"></i> Ticket Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Subject</span>
                    <h5 class="fs-15 text-dark fw-bold mb-0">{{ $ticket->subject }}</h5>
                </div>

                <div class="row g-2 mb-3 fs-13">
                    <div class="col-6">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Status</span>
                        <span class="badge {{ $badgeClass }} fs-12 px-2 py-1">
                            {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                        </span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Category</span>
                        <span class="badge bg-light text-muted fs-11 text-uppercase">
                            {{ $ticket->category->value ?? $ticket->category }}
                        </span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Reference</span>
                        <span class="font-monospace text-dark fs-12">{{ $ticket->reference }}</span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Opened On</span>
                        <span class="text-dark fs-12">{{ $ticket->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>

                {{-- Status Changer Form --}}
                <div class="pt-3 border-top">
                    <label class="form-label fs-12 fw-semibold text-dark mb-1">Update Ticket Status</label>
                    <form method="POST" action="{{ route('admin.customers.tickets.update-status', [$customer, $ticket]) }}">
                        @csrf
                        @method('PATCH')
                        <div class="input-group input-group-sm">
                            <select name="status" class="form-select">
                                <option value="open" {{ $statusValue === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $statusValue === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $statusValue === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $statusValue === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Thread & Reply Form --}}
    <div class="col-lg-8">
        {{-- Thread Container --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-messages me-1 text-primary"></i> Conversation History
                </h5>
                <span class="badge bg-light text-muted fs-12">{{ $ticket->messages->count() }} messages</span>
            </div>

            <div class="card-body p-3 p-md-4" style="max-height: 550px; overflow-y: auto;">
                @forelse($ticket->messages as $msg)
                    @php
                        $isAdmin = $msg->author_type === 'admin';
                    @endphp
                    <div class="d-flex {{ $isAdmin ? 'justify-content-end' : 'justify-content-start' }} mb-3">
                        <div class="d-flex {{ $isAdmin ? 'flex-row-reverse' : 'flex-row' }} align-items-start gap-2" style="max-width: 85%;">
                            <div class="avatar-sm flex-shrink-0">
                                @if($isAdmin)
                                    <span class="avatar-title bg-primary text-white rounded-circle fs-13 fw-bold">
                                        <i class="ti ti-shield"></i>
                                    </span>
                                @else
                                    <span class="avatar-title bg-light text-primary rounded-circle fs-13 fw-bold">
                                        {{ strtoupper(substr($customer->first_name ?? $customer->username, 0, 1)) }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <div class="p-3 {{ $isAdmin ? 'chat-bubble-admin' : 'chat-bubble-customer' }}">
                                    <div class="d-flex align-items-center justify-content-between gap-3 mb-1">
                                        <span class="fw-bold fs-12 {{ $isAdmin ? 'text-primary' : 'text-dark' }}">
                                            {{ $msg->author_name ?: ($isAdmin ? 'Support Agent' : $customer->username) }}
                                            @if($isAdmin)
                                                <span class="badge bg-primary-subtle text-primary fs-10 ms-1">Admin</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary fs-10 ms-1">Customer</span>
                                            @endif
                                        </span>
                                        <small class="text-muted fs-11">{{ $msg->created_at->format('M d, H:i') }}</small>
                                    </div>
                                    <div class="fs-13 text-dark text-break" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">No messages in this ticket yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Admin Reply Form Card --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-3">
                <h5 class="card-title fs-14 mb-0 fw-bold">
                    <i class="ti ti-message-share me-1 text-primary"></i> Send Official Reply
                </h5>
            </div>
            <div class="card-body p-3 p-md-4">
                <form method="POST" action="{{ route('admin.customers.tickets.reply', [$customer, $ticket]) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="reply-message" class="form-label fs-13 fw-semibold text-dark">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control fs-13"
                                  id="reply-message"
                                  name="message"
                                  rows="4"
                                  placeholder="Type your message to @{{ $customer->username }}..."
                                  required></textarea>
                    </div>

                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <label for="reply-status" class="form-label fs-12 text-muted mb-1">Update Status Upon Reply</label>
                            <select name="status" id="reply-status" class="form-select form-select-sm">
                                <option value="" selected>Keep current ({{ ucfirst($statusValue) }})</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-md-6 text-md-end pt-3 pt-md-0">
                            <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 px-3">
                                <i class="ti ti-send"></i> Send Reply to Customer
                            </button>
                        </div>
                    </div>
                </form>
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
