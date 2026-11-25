{{-- Shared Customer Actions & Modal JavaScript --}}
<script>
    $(function () {
        let currentRawBalance = 0;

        // Open Manual Adjustment Modal
        $(document).on('click', '.btn-open-adjust-modal', function (e) {
            e.preventDefault();
            const customerId = $(this).data('customer-id');
            const customerName = $(this).data('customer-name');
            const customerUsername = $(this).data('customer-username');
            const customerBalance = $(this).data('customer-balance');

            currentRawBalance = parseFloat(String(customerBalance).replace(/,/g, '')) || 0;

            $('#adjustWalletForm').attr('action', `/admininistator/customers/${customerId}/wallet/adjust`);
            $('#modal-customer-name').text(customerName);
            $('#modal-customer-username').text(`@${customerUsername}`);
            $('#modal-customer-balance').text(`₦${currentRawBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);

            // Reset fields
            $('#adjust-amount').val('');
            $('#adjust-reason').val('');
            $('#adjust-reference').val('');
            $('#action-credit').prop('checked', true);
            $('#adjust-notify').prop('checked', true);
            $('#adjust-modal-error').addClass('d-none').text('');
            $('#adjust-overdraw-warning').addClass('d-none');
            updateBalancePreview();

            const modal = new bootstrap.Modal(document.getElementById('adjustWalletModal'));
            modal.show();
        });

        // Dynamic Balance Preview Calculation
        $('#adjust-amount, input[name="action"]').on('input change', function () {
            updateBalancePreview();
        });

        function updateBalancePreview() {
            const entered = parseFloat($('#adjust-amount').val()) || 0;
            const action = $('input[name="action"]:checked').val();
            let newBalance = currentRawBalance;

            if (action === 'credit') {
                newBalance = currentRawBalance + entered;
                $('#adjust-overdraw-warning').addClass('d-none');
                $('#btn-submit-adjust').prop('disabled', false);
            } else {
                newBalance = currentRawBalance - entered;
                if (entered > currentRawBalance) {
                    $('#adjust-overdraw-warning').removeClass('d-none');
                    $('#btn-submit-adjust').prop('disabled', true);
                } else {
                    $('#adjust-overdraw-warning').addClass('d-none');
                    $('#btn-submit-adjust').prop('disabled', false);
                }
            }

            const formattedNew = newBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            $('#adjust-balance-preview').html(`New balance will be: <span class="fw-bold ${newBalance < 0 ? 'text-danger' : 'text-dark'}">₦${formattedNew}</span>`);
        }

        // AJAX Submission for Adjustment Form
        $('#adjustWalletForm').on('submit', function (e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btn-submit-adjust');
            const spinner = btn.find('.spinner-border');
            const btnText = $('#btn-submit-text');
            const errorBox = $('#adjust-modal-error');

            errorBox.addClass('d-none').text('');
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            btnText.text('Processing...');

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                success: function (res) {
                    const modalEl = document.getElementById('adjustWalletModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    Swal.fire({
                        title: 'Balance Adjusted!',
                        text: res.message || 'Customer wallet balance updated successfully.',
                        icon: 'success',
                        confirmButtonColor: '#7266ed',
                    }).then(() => {
                        window.location.reload();
                    });
                },
                error: function (xhr) {
                    btn.prop('disabled', false);
                    spinner.addClass('d-none');
                    btnText.text('Confirm Adjustment');

                    let message = 'An unexpected error occurred.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                    }
                    errorBox.removeClass('d-none').html(message);
                }
            });
        });

        // Generic Confirmation for Quick Actions
        $(document).on('submit', '.form-confirm-action', function (e) {
            e.preventDefault();
            const form = this;
            const confirmText = $(form).data('confirm-text') || 'Are you sure you want to proceed with this action?';

            Swal.fire({
                title: 'Confirm Action',
                text: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7266ed',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Customer Delete SweetAlert Confirmation
        $(document).on('click', '.btn-delete-profile, .btn-delete-customer', function (e) {
            e.preventDefault();
            const customerId = $(this).data('id');
            const username = $(this).data('username');

            Swal.fire({
                title: 'Delete Customer?',
                text: `Are you sure you want to permanently delete customer @${username}? This action is irreversible.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#ea5455',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, permanently delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = $('<form>', {
                        action: `/admininistator/customers/${customerId}`,
                        method: 'POST'
                    }).append(
                        $('<input>', { type: 'hidden', name: '_token', value: $('meta[name="csrf-token"]').attr('content') }),
                        $('<input>', { type: 'hidden', name: '_method', value: 'DELETE' })
                    );
                    $('body').append(form);
                    form.submit();
                }
            });
        });
    });
</script>
