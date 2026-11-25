{{-- Manual Wallet Credit / Debit Modal --}}
<div class="modal fade" id="adjustWalletModal" tabindex="-1" aria-labelledby="adjustWalletModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="adjustWalletForm" method="POST" action="">
                @csrf
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fs-15 fw-bold d-flex align-items-center gap-2" id="adjustWalletModalLabel">
                        <i class="ti ti-wallet text-primary fs-18"></i>
                        <span>Adjust Wallet Balance</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    {{-- Target User Preview --}}
                    <div class="alert alert-light border d-flex align-items-center justify-content-between p-2 mb-3">
                        <div>
                            <span class="fs-12 text-muted d-block">Target Customer</span>
                            <span class="fw-bold text-dark fs-14" id="modal-customer-name">—</span>
                            <span class="text-muted fs-12 d-block" id="modal-customer-username">@—</span>
                        </div>
                        <div class="text-end">
                            <span class="fs-11 text-muted text-uppercase fw-semibold d-block">Current Balance</span>
                            <span class="fw-bold fs-16 text-primary" id="modal-customer-balance">₦0.00</span>
                        </div>
                    </div>

                    {{-- Action Type Selector (Credit vs Debit) --}}
                    <div class="mb-3">
                        <label class="form-label fs-13 fw-semibold text-dark">Adjustment Type <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="action" id="action-credit" value="credit" checked autocomplete="off">
                                <label class="btn btn-outline-success w-100 d-flex align-items-center justify-content-center gap-2 py-2" for="action-credit">
                                    <i class="ti ti-arrow-down-left fs-16"></i>
                                    <span class="fw-semibold">Credit (+)</span>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="action" id="action-debit" value="debit" autocomplete="off">
                                <label class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2 py-2" for="action-debit">
                                    <i class="ti ti-arrow-up-right fs-16"></i>
                                    <span class="fw-semibold">Debit (-)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Amount Input --}}
                    <div class="mb-3">
                        <label for="adjust-amount" class="form-label fs-13 fw-semibold text-dark">Amount (₦) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold text-dark">₦</span>
                            <input type="number"
                                   step="0.01"
                                   min="0.01"
                                   class="form-control form-control-lg fs-16 font-monospace"
                                   id="adjust-amount"
                                   name="amount"
                                   placeholder="0.00"
                                   required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted fs-11" id="adjust-balance-preview">New balance will be: <span class="fw-semibold text-dark">₦0.00</span></small>
                            <small class="text-danger fs-11 d-none" id="adjust-overdraw-warning">
                                <i class="ti ti-alert-triangle"></i> Amount exceeds balance!
                            </small>
                        </div>
                    </div>

                    {{-- Reason / Description --}}
                    <div class="mb-3">
                        <label for="adjust-reason" class="form-label fs-13 fw-semibold text-dark">Reason / Description <span class="text-danger">*</span></label>
                        <textarea class="form-control fs-13"
                                  id="adjust-reason"
                                  name="reason"
                                  rows="2"
                                  placeholder="e.g. Compensation for order refund #1092, promo credit, manual correction..."
                                  required></textarea>
                    </div>

                    {{-- Optional Reference --}}
                    <div class="mb-3">
                        <label for="adjust-reference" class="form-label fs-12 text-muted">Custom Reference <span class="fw-normal text-muted">(Optional)</span></label>
                        <input type="text"
                               class="form-control form-control-sm font-monospace fs-12"
                               id="adjust-reference"
                               name="reference"
                               placeholder="Auto-generated if left blank">
                    </div>

                    {{-- Notification Toggle --}}
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="adjust-notify" name="notify" value="1" checked>
                        <label class="form-check-label fs-13 text-dark" for="adjust-notify">
                            Send email notification to customer
                        </label>
                    </div>

                    {{-- Error Display Container --}}
                    <div class="alert alert-danger fs-12 mt-3 d-none" id="adjust-modal-error"></div>
                </div>

                <div class="modal-footer bg-light border-top py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="btn-submit-adjust">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="ti ti-check fs-14"></i>
                        <span id="btn-submit-text">Confirm Adjustment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
