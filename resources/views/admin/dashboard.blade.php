@extends('layouts.admin')

@php
    $title = 'Dashboard';
@endphp

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Dashboard',
    'subtitle' => 'System metrics, operational activity, and administrative overview.',
    'breadcrumbs' => [
        'Home' => route('admin.dashboard'),
        'Dashboard' => null,
    ],
    'actions' => [
        [
            'label' => 'View Customers',
            'icon' => 'ti ti-users',
            'class' => 'btn btn-primary',
            'url' => route('admin.customers.index'),
        ],
        [
            'label' => 'Refresh',
            'icon' => 'ti ti-refresh',
            'class' => 'btn btn-outline-secondary',
            'attributes' => 'onclick="window.location.reload();"',
        ],
    ],
])

<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="header-title">Welcome back, {{ $admin->username ?? 'Administrator' }}!</h4>
                <p class="text-muted fs-14 mb-0">
                    You are logged into the Korak Digitals Administrator Panel. Role: <span class="badge bg-primary">{{ ucfirst($admin->role->value ?? 'admin') }}</span>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Total Customers</span>
                        <h3 class="my-1 fs-24 text-dark">{{ number_format($totalCustomers ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">
                            <a href="{{ route('admin.customers.index') }}" class="text-primary text-decoration-none">
                                View all customers <i class="ti ti-arrow-right"></i>
                            </a>
                        </p>
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

    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Dedicated Accounts</span>
                        <h3 class="my-1 fs-24 text-dark">{{ number_format($totalVirtualAccounts ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">
                            <span class="text-success"><i class="ti ti-check"></i> FinityPay Connected</span>
                        </p>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle text-success rounded fs-20 p-2">
                            <i class="ti ti-building-bank"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Active Wallets</span>
                        <h3 class="my-1 fs-24 text-dark">{{ number_format($totalWallets ?? 0) }}</h3>
                        <p class="mb-0 text-muted fs-12">
                            <span class="text-info"><i class="ti ti-wallet"></i> Multi-currency Ledger</span>
                        </p>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle text-info rounded fs-20 p-2">
                            <i class="ti ti-wallet"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
