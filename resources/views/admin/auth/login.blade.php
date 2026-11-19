@extends('layouts.auth')

@php 
    $title = 'Administrator Login';
@endphp

@section('content')
<h3 class="fw-semibold mb-2">Login your account</h3>

<p class="text-muted mb-4">Enter your email address and password to access admin panel.</p>


<form id="login-form" class="text-start mb-3">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email">
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password">
    </div>

    <div class="d-flex justify-content-between mb-3">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember">
            <label class="form-check-label" for="checkbox-signin">Remember me</label>
        </div>

        <a href="{{ route('admin.forgot-password') }}" class="text-muted border-bottom border-dashed">Forget Password</a>
    </div>

    <div class="d-grid">
        <button class="btn btn-primary" type="submit">Login</button>
    </div>
</form>


@endsection

@section('scripts')

<script> 
    $(document).ready(function() {
        $('#login-form').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            $.ajax({
                url: '{{ route('admin.login') }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log(response);
                    if (response.status === 'success') {
                        window.location.href = '{{ route('admin.dashboard') }}';
                    } else {
                        toastr.error(response.message);
                        $('#email').addClass('is-invalid');
                        $('#password').addClass('is-invalid');
                        $('#email').val('');
                        $('#password').val('');
                    }
                },
                error: function(error) {
                    console.log(error);
                    toastr.error(error.responseJSON.message);
                    $('#email').addClass('is-invalid');
                    $('#password').addClass('is-invalid');
                },
            });
        });

        $('#email').on('input', function() {
            $('#email').removeClass('is-invalid');
        });

        $('#password').on('input', function() {
            $('#password').removeClass('is-invalid');
        });
    });
</script>
@endsection