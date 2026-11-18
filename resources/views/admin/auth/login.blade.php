@extends('layouts.auth')

@php 
    $title = 'Administrator Login';
@endphp

@section('content')
<h3 class="fw-semibold mb-2">Login your account</h3>

<p class="text-muted mb-4">Enter your email address and password to access admin panel.</p>


<form action="https://coderthemes.com/osen/layouts/index.html" class="text-start mb-3">
    <div class="mb-3">
        <label class="form-label" for="example-email">Email</label>
        <input type="email" id="example-email" name="example-email" class="form-control" placeholder="Enter your email">
    </div>

    <div class="mb-3">
        <label class="form-label" for="example-password">Password</label>
        <input type="password" id="example-password" class="form-control" placeholder="Enter your password">
    </div>

    <div class="d-flex justify-content-between mb-3">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="checkbox-signin">
            <label class="form-check-label" for="checkbox-signin">Remember me</label>
        </div>

        <a href="auth-recoverpw.html" class="text-muted border-bottom border-dashed">Forget Password</a>
    </div>

    <div class="d-grid">
        <button class="btn btn-primary" type="submit">Login</button>
    </div>
</form>
@endsection