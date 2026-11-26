{{-- Edit Profile Modal --}}
<div class="modal fade" id="modalEditProfile" tabindex="-1" aria-labelledby="modalEditProfileLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fs-15 fw-bold" id="modalEditProfileLabel">
                    <i class="ti ti-user-edit text-primary me-1"></i> Edit Customer Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-edit-profile" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fs-12 fw-semibold text-muted text-uppercase mb-1" for="edit_first_name">First Name</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" placeholder="First Name" maxlength="60">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fs-12 fw-semibold text-muted text-uppercase mb-1" for="edit_last_name">Last Name</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" placeholder="Last Name" maxlength="60">
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-12 fw-semibold text-muted text-uppercase mb-1" for="edit_phone_number">Phone Number</label>
                            <input type="text" class="form-control" id="edit_phone_number" name="phone_number" placeholder="e.g. +2348012345678" maxlength="20">
                            <small class="text-muted fs-11">Used for SMS alerts and account contact.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-12 fw-semibold text-muted text-uppercase mb-1" for="edit_email">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email" required placeholder="customer@example.com" maxlength="255">
                            <small class="text-muted fs-11">Primary account identifier for notifications and login.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="btn-submit-edit-profile">
                        <i class="ti ti-check fs-14"></i> Save Profile Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
