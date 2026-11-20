@extends('layouts.auth')

@php 
    $title = 'Reset Password';
@endphp

@section('content')
<h3 class="fw-semibold mb-2">Set New Password</h3>
<p class="text-muted mb-4">Choose a strong new password for your administrator account.</p>

<form id="reset-form" class="text-start mb-3" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ $email ?? old('email') }}" class="form-control" placeholder="Enter your email" required autofocus>
        <div class="invalid-feedback" id="email-error"></div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">New Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password (min. 8 characters)" required>
        <div class="invalid-feedback" id="password-error"></div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="password_confirmation">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Confirm new password" required>
        <div class="invalid-feedback" id="password_confirmation-error"></div>
    </div>

    <div class="d-grid mb-3">
        <button id="reset-btn" class="btn btn-primary" type="submit">
            <span class="btn-text">Reset Password</span>
            <span class="spinner-border spinner-border-sm d-none ms-1" role="status" aria-hidden="true"></span>
        </button>
    </div>

    <div class="text-center">
        <a href="{{ route('admin.login') }}" class="text-muted border-bottom border-dashed">Back to Login</a>
    </div>
</form>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        const $form = $('#reset-form');
        const $btn = $('#reset-btn');
        const $btnText = $btn.find('.btn-text');
        const $spinner = $btn.find('.spinner-border');

        function setLoading(loading) {
            if (loading) {
                $btn.prop('disabled', true);
                $spinner.removeClass('d-none');
                $btnText.text('Resetting...');
            } else {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $btnText.text('Reset Password');
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
                url: '{{ route('admin.reset-password.submit') }}',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        toastr.success(response.message || 'Password reset successfully! Redirecting...');
                        setTimeout(function() {
                            window.location.href = response.redirect_url;
                        }, 800);
                    } else {
                        setLoading(false);
                        toastr.error(response.message || 'Failed to reset password.');
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

        $('#email, #password, #password_confirmation').on('input', function() {
            $(this).removeClass('is-invalid');
            $('#' + $(this).attr('id') + '-error').text('');
        });
    });
</script>
@endsection
