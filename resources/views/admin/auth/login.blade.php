@extends('layouts.auth')

@php 
    $title = 'Administrator Login';
@endphp

@section('content')
<h3 class="fw-semibold mb-2">Login your account</h3>
<p class="text-muted mb-4">Enter your email address and password to access the admin panel.</p>

<form id="login-form" class="text-start mb-3" novalidate>
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
        <div class="invalid-feedback" id="email-error"></div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
        <div class="invalid-feedback" id="password-error"></div>
    </div>

    <div class="d-flex justify-content-between mb-3">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <a href="{{ route('admin.forgot-password') }}" class="text-muted border-bottom border-dashed">Forgot Password?</a>
    </div>

    <div class="d-grid">
        <button id="login-btn" class="btn btn-primary" type="submit">
            <span class="btn-text">Login</span>
            <span class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
        </button>
    </div>
</form>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        const $form = $('#login-form');
        const $btn = $('#login-btn');
        const $btnText = $btn.find('.btn-text');
        const $spinner = $btn.find('.spinner-border');

        function setLoading(loading) {
            if (loading) {
                $btn.prop('disabled', true);
                $spinner.removeClass('d-none');
                $btnText.text('Logging in...');
            } else {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $btnText.text('Login');
            }
        }

        function clearErrors() {
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
        }

        $form.on('submit', function(e) {
            e.preventDefault();
            clearErrors();
            setLoading(true);

            $.ajax({
                url: '{{ route('admin.login.submit') }}',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        toastr.success(response.message || 'Login successful! Redirecting...');
                        setTimeout(function() {
                            window.location.href = response.redirect_url;
                        }, 800);
                    } else {
                        setLoading(false);
                        toastr.error(response.message || 'Authentication failed.');
                    }
                },
                error: function(xhr) {
                    setLoading(false);
                    const res = xhr.responseJSON;

                    if (xhr.status === 422 && res && res.errors) {
                        $.each(res.errors, function(field, messages) {
                            const $input = $('#' + field);
                            const $errorDiv = $('#' + field + '-error');
                            $input.addClass('is-invalid');
                            if ($errorDiv.length) {
                                $errorDiv.text(messages[0]);
                            }
                        });
                        toastr.error(res.message || 'Please correct the highlighted errors.');
                    } else if (res && res.message) {
                        toastr.error(res.message);
                    } else {
                        toastr.error('An unexpected error occurred. Please try again.');
                    }
                }
            });
        });

        $('#email, #password').on('input', function() {
            $(this).removeClass('is-invalid');
            $('#' + $(this).attr('id') + '-error').text('');
        });
    });
</script>
@endsection
