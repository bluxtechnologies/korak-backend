@extends('layouts.auth')

@php 
    $title = 'Forgot Password';
@endphp

@section('content')
<h3 class="fw-semibold mb-2">Forgot Password</h3>
<p class="text-muted mb-4">Enter your registered email address and we'll send you a password reset link.</p>

<form id="forgot-form" class="text-start mb-3" novalidate>
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
        <div class="invalid-feedback" id="email-error"></div>
    </div>

    <div class="d-grid mb-3">
        <button id="forgot-btn" class="btn btn-primary" type="submit">
            <span class="btn-text">Send Reset Link</span>
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
        const $form = $('#forgot-form');
        const $btn = $('#forgot-btn');
        const $btnText = $btn.find('.btn-text');
        const $spinner = $btn.find('.spinner-border');

        function setLoading(loading) {
            if (loading) {
                $btn.prop('disabled', true);
                $spinner.removeClass('d-none');
                $btnText.text('Sending...');
            } else {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $btnText.text('Send Reset Link');
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
                url: '{{ route('admin.forgot-password.submit') }}',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    setLoading(false);
                    if (response.status === 'success') {
                        toastr.success(response.message || 'Password reset link sent!');
                        $form[0].reset();
                    } else {
                        toastr.error(response.message || 'Failed to send reset link.');
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

        $('#email').on('input', function() {
            $(this).removeClass('is-invalid');
            $('#email-error').text('');
        });
    });
</script>
@endsection
