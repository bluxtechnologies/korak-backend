@extends('layouts.admin')

@php
    $title = 'Dashboard';
@endphp

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box">
            <h4 class="page-title">Dashboard</h4>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h4 class="header-title">Welcome back, {{ $admin->username ?? 'Administrator' }}!</h4>
                <p class="text-muted fs-14">
                    You are logged into the Korak Digitals Administrator Panel. Role: <span class="badge bg-primary">{{ ucfirst($admin->role->value ?? 'admin') }}</span>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
