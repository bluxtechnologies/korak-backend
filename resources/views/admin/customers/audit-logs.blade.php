@extends('layouts.admin')

@php
    $title = 'Audit Logs & Security - @' . $customer->username;
@endphp

@section('styles')
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.css') }}" />
@endsection

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Security Center & Audit Logs: @' . $customer->username,
    'subtitle' => 'Authentication history, IP address tracking, device challenges, and real-time security management.',
    'breadcrumbs' => [
        'Dashboard' => route('admin.dashboard'),
        'Customers' => route('admin.customers.index'),
        '@' . $customer->username => route('admin.customers.show', $customer),
        'Audit Logs' => null,
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
@include('admin.customers.partials.header', ['activeTab' => 'audit-logs'])

{{-- Security State Strip --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">2FA Device Verification</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                @if($securityState['device_verification_enabled'])
                    <span class="badge bg-success-subtle text-success fs-12"><i class="ti ti-check me-1"></i> Enabled</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary fs-12">Disabled</span>
                @endif
                <form method="POST" action="{{ route('admin.customers.toggle-device-verification', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="{{ $securityState['device_verification_enabled'] ? 'Disable 2FA device verification for @' . $customer->username . '?' : 'Enable 2FA device verification for @' . $customer->username . '?' }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-xs">
                        {{ $securityState['device_verification_enabled'] ? 'Disable' : 'Enable' }}
                    </button>
                </form>
            </div>
            <small class="text-muted fs-11 mt-1 d-block">Requires OTP on new device logins</small>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Login Lockout State</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                @if($securityState['is_lockout_active'] || $securityState['failed_attempts'] > 0)
                    <span class="badge bg-danger-subtle text-danger fs-12">
                        <i class="ti ti-alert-triangle me-1"></i> {{ $securityState['failed_attempts'] }} failed tries
                    </span>
                    <form method="POST" action="{{ route('admin.customers.clear-lockout', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="Clear login lockout and reset failed attempts counter for @{{ $customer->username }}?">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-xs">Clear</button>
                    </form>
                @else
                    <span class="badge bg-success-subtle text-success fs-12"><i class="ti ti-check me-1"></i> Normal</span>
                    <small class="text-muted fs-11">0 failed tries</small>
                @endif
            </div>
            <small class="text-muted fs-11 mt-1 d-block">Automatic 15-min lockout at 5 failures</small>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Account Administrative Lock</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                @if($securityState['is_locked'])
                    <span class="badge bg-danger-subtle text-danger fs-12"><i class="ti ti-lock me-1"></i> Locked</span>
                @else
                    <span class="badge bg-success-subtle text-success fs-12"><i class="ti ti-lock-open me-1"></i> Unlocked</span>
                @endif
                <form method="POST" action="{{ route('admin.customers.toggle-lock', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="{{ $securityState['is_locked'] ? 'Unlock customer @' . $customer->username . '?' : 'Lock customer @' . $customer->username . '? User will not be able to log in.' }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-xs">
                        {{ $securityState['is_locked'] ? 'Unlock' : 'Lock' }}
                    </button>
                </form>
            </div>
            <small class="text-muted fs-11 mt-1 d-block">Manual lock blocks authentication</small>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Active Trusted Devices</span>
            <div class="d-flex align-items-center justify-content-between mt-1">
                <span class="fs-16 fw-bold text-dark">{{ $securityState['trusted_devices_count'] }} Active</span>
                @if($securityState['trusted_devices_count'] > 0)
                    <form method="POST" action="{{ route('admin.customers.terminate-sessions', $customer) }}" class="d-inline form-confirm-action" data-confirm-text="Terminate all active trusted devices and sessions for @{{ $customer->username }}?">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-xs">Revoke All</button>
                    </form>
                @endif
            </div>
            <small class="text-muted fs-11 mt-1 d-block">Devices with saved trust tokens</small>
        </div>
    </div>
</div>

{{-- Filters & Search --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('admin.customers.audit-logs', $customer) }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="ti ti-network text-muted"></i></span>
                    <input type="text" name="ip" class="form-control border-start-0" placeholder="Filter by IP address..." value="{{ $ip }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Event Categories</option>
                    <option value="auth" {{ $category === 'auth' ? 'selected' : '' }}>Authentication (Login, Logout, Lockout)</option>
                    <option value="device" {{ $category === 'device' ? 'selected' : '' }}>Device Verification & Trust</option>
                    <option value="profile" {{ $category === 'profile' ? 'selected' : '' }}>Profile & Email Updates</option>
                    <option value="session" {{ $category === 'session' ? 'selected' : '' }}>Session Revocations</option>
                    <option value="wallet" {{ $category === 'wallet' ? 'selected' : '' }}>Wallet & Money Events</option>
                </select>
            </div>
            <div class="col-md-3 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="ti ti-filter fs-14"></i> Filter
                </button>
                @if($ip || $category)
                    <a href="{{ route('admin.customers.audit-logs', $customer) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Audit Logs Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header border-bottom d-flex align-items-center justify-content-between">
        <h5 class="card-title fs-14 fw-bold mb-0">Append-Only Audit Log</h5>
        <span class="text-muted fs-12">Showing {{ $auditLogs->firstItem() ?? 0 }} - {{ $auditLogs->lastItem() ?? 0 }} of {{ $auditLogs->total() }} events</span>
    </div>
    <div class="card-body p-0">
        @if($auditLogs->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-12">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Event</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                            <th>Metadata / Context</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $log)
                            @php
                                $eventName = is_object($log->event) ? $log->event->value : (string) $log->event;
                                $isAuth = str_starts_with($eventName, 'auth.');
                                $isDevice = str_starts_with($eventName, 'device.');
                                $isFailed = str_contains($eventName, 'failed') || str_contains($eventName, 'lockout');

                                $badgeClass = match(true) {
                                    $isFailed => 'bg-danger-subtle text-danger',
                                    $isAuth => 'bg-success-subtle text-success',
                                    $isDevice => 'bg-info-subtle text-info',
                                    default => 'bg-secondary-subtle text-secondary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="d-block text-dark fw-medium">{{ $log->occurred_at ? $log->occurred_at->format('M d, Y H:i:s') : '—' }}</span>
                                    <small class="text-muted">{{ $log->occurred_at ? $log->occurred_at->diffForHumans() : '' }}</small>
                                </td>
                                <td>
                                    <span class="badge {{ $badgeClass }} font-monospace fs-11">
                                        {{ $eventName }}
                                    </span>
                                </td>
                                <td>
                                    <span class="font-monospace text-dark">{{ $log->ip_address ?: '—' }}</span>
                                </td>
                                <td style="max-width: 250px;">
                                    <span class="text-truncate d-block text-muted" title="{{ $log->user_agent ?? 'Unknown' }}">
                                        {{ $log->user_agent ? Str::limit($log->user_agent, 40) : '—' }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($log->metadata))
                                        <pre class="bg-light p-1 rounded font-monospace fs-10 mb-0 text-muted" style="max-width: 320px; max-height: 80px; overflow: auto;">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($auditLogs->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $auditLogs->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-5 text-muted">
                <i class="ti ti-shield-search fs-36 text-secondary mb-2 d-block"></i>
                <p class="fs-13 mb-0">No audit events found matching the specified criteria.</p>
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
