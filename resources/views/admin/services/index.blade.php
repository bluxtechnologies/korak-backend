@extends('layouts.admin')

@section('title', 'Korak Services')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.page-header', [
        'title' => 'Korak Services',
        'subtitle' => 'Activate or deactivate website services and customize frontend maintenance notices.',
        'breadcrumbs' => [
            'Dashboard' => route('admin.dashboard'),
            'Korak Services' => null,
        ]
    ])

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-circle-check fs-18 me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Stats KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Total Services</span>
                        <h3 class="fs-22 fw-bold text-dark mb-0 mt-1">{{ $totalServices }}</h3>
                        <span class="text-muted fs-11">Configured on platform</span>
                    </div>
                    <div class="avatar-md bg-light-primary rounded-circle d-flex align-items-center justify-content-center">
                        <i class="ti ti-apps fs-24 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Active & Operational</span>
                        <h3 class="fs-22 fw-bold text-success mb-0 mt-1" id="stat-active-count">{{ $activeServices }}</h3>
                        <span class="text-muted fs-11">Serving live customers</span>
                    </div>
                    <div class="avatar-md bg-light-success rounded-circle d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle-check fs-24 text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <span class="text-muted fs-12 text-uppercase fw-semibold">Offline / Maintenance</span>
                        <h3 class="fs-22 fw-bold text-danger mb-0 mt-1" id="stat-inactive-count">{{ $inactiveServices }}</h3>
                        <span class="text-muted fs-11">Displaying unavailable page</span>
                    </div>
                    <div class="avatar-md bg-light-danger rounded-circle d-flex align-items-center justify-content-center">
                        <i class="ti ti-alert-triangle fs-24 text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Services Table Card --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom d-flex align-items-center justify-content-between py-3 flex-wrap gap-2">
            <div>
                <h5 class="card-title mb-0 fs-16 fw-bold">Platform Services & Maintenance Control</h5>
                <p class="text-muted fs-12 mb-0 mt-1">
                    When deactivated, any frontend visit or API request displays "Service Unavailable" and shows nothing else on that page.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalAddService">
                    <i class="ti ti-plus fs-16"></i> Add Service
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="min-width: 200px;">Service Name</th>
                            <th>Code / Category</th>
                            <th style="width: 140px;">Active State</th>
                            <th style="min-width: 250px;">Maintenance Notice</th>
                            <th class="text-end" style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $index => $service)
                            <tr id="service-row-{{ $service->id }}">
                                <td class="text-muted fs-12">{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm rounded-circle d-flex align-items-center justify-content-center {{ $service->is_active ? 'bg-light-primary text-primary' : 'bg-light-secondary text-secondary' }}" id="service-avatar-{{ $service->id }}">
                                            <i class="{{ $service->icon ?: 'ti ti-apps' }} fs-18"></i>
                                        </div>
                                        <div>
                                            <h6 class="fs-14 fw-bold text-dark mb-0">{{ $service->name }}</h6>
                                            <span class="text-muted fs-11 d-block text-truncate" style="max-width: 260px;" title="{{ $service->description }}">
                                                {{ $service->description ?? 'No description' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <code class="text-primary bg-light px-2 py-1 rounded fs-12">{{ $service->code }}</code>
                                    <span class="badge bg-secondary-subtle text-secondary fs-10 ms-1 text-uppercase">{{ $service->category }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input switch-toggle-service"
                                                   type="checkbox"
                                                   role="switch"
                                                   id="switch-service-{{ $service->id }}"
                                                   data-service-id="{{ $service->id }}"
                                                   data-service-name="{{ $service->name }}"
                                                   data-action="{{ route('admin.services.toggle', $service) }}"
                                                   {{ $service->is_active ? 'checked' : '' }}>
                                        </div>
                                        <span class="badge {{ $service->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} fs-11" id="service-badge-{{ $service->id }}">
                                            {{ $service->is_active ? 'Active' : 'Offline' }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="fw-semibold text-dark fs-12 d-block">{{ $service->unavailable_headline }}</span>
                                        <span class="text-muted fs-11 d-block text-truncate" style="max-width: 320px;" title="{{ $service->maintenance_message }}">
                                            {{ $service->maintenance_message ?: 'Default maintenance notice applies.' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        {{-- Preview unavailable page in new tab --}}
                                        <a href="{{ route('admin.services.preview', $service) }}"
                                           target="_blank"
                                           class="btn btn-sm btn-soft-info"
                                           title="Preview how unavailable page looks">
                                            <i class="ti ti-eye fs-14"></i> Preview
                                        </a>

                                        {{-- Edit Service Modal Trigger --}}
                                        <button type="button"
                                                class="btn btn-sm btn-soft-primary btn-open-edit-service"
                                                data-service='@json($service)'
                                                data-action="{{ route('admin.services.update', $service) }}"
                                                title="Edit service details & notice">
                                            <i class="ti ti-edit fs-14"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="ti ti-apps-off fs-36 d-block mb-2 text-secondary"></i>
                                    <h5>No services registered</h5>
                                    <p class="fs-12">Click "Add Service" to register website services.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add Service Modal --}}
<div class="modal fade" id="modalAddService" tabindex="-1" aria-labelledby="modalAddServiceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.services.store') }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalAddServiceLabel">
                        <i class="ti ti-plus text-primary me-1"></i> Add New Website Service
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Airtime VTU" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Unique Code / Slug <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. airtime" required>
                            <span class="text-muted fs-10">Alpha-dash identifier used in API / URL.</span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="telecom">Telecom</option>
                                <option value="utilities">Utilities</option>
                                <option value="entertainment">Entertainment</option>
                                <option value="education">Education</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Icon Class (Tabler Icons)</label>
                        <input type="text" name="icon" class="form-control" placeholder="e.g. ti ti-device-mobile" value="ti ti-apps">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of the service"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Unavailable Headline</label>
                        <input type="text" name="unavailable_headline" class="form-control" placeholder="e.g. Service Unavailable" value="Service Unavailable">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Maintenance Message</label>
                        <textarea name="maintenance_message" class="form-control" rows="3" placeholder="Message shown on the unavailable page and in API responses when this service is deactivated"></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="addServiceIsActive" value="1" checked>
                        <label class="form-check-label fs-13 fw-semibold" for="addServiceIsActive">Activate Immediately</label>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Service Modal --}}
<div class="modal fade" id="modalEditService" tabindex="-1" aria-labelledby="modalEditServiceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formEditService" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalEditServiceLabel">
                        <i class="ti ti-edit text-primary me-1"></i> Edit Service & Notice
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_service_name" class="form-control" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Service Code</label>
                            <input type="text" id="edit_service_code" class="form-control bg-light" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-12 fw-semibold">Category <span class="text-danger">*</span></label>
                            <input type="text" name="category" id="edit_service_category" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Icon Class</label>
                        <input type="text" name="icon" id="edit_service_icon" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Description</label>
                        <textarea name="description" id="edit_service_description" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Frontend Unavailable Headline <span class="text-danger">*</span></label>
                        <input type="text" name="unavailable_headline" id="edit_service_headline" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-12 fw-semibold">Maintenance Notice / Message</label>
                        <textarea name="maintenance_message" id="edit_service_message" class="form-control" rows="3" placeholder="Explain reason for downtime to customers"></textarea>
                        <span class="text-muted fs-11">This message is rendered on the public unavailable page and returned in API responses.</span>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="edit_service_is_active" value="1">
                        <label class="form-check-label fs-13 fw-semibold" for="edit_service_is_active">Service Active & Operational</label>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Update Service</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- SweetAlert2 js -->
<script src="{{ asset('admin/assets/vendor/sweetalert2/sweetalert2.min.js') }}"></script>

<script>
    $(document).ready(function () {
        // Toggle Active State Handler
        $(document).on('change', '.switch-toggle-service', function () {
            const toggle = $(this);
            const serviceId = toggle.data('service-id');
            const serviceName = toggle.data('service-name');
            const actionUrl = toggle.data('action');
            const isChecked = toggle.is(':checked');

            toggle.prop('disabled', true);

            $.ajax({
                url: actionUrl,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                dataType: 'json',
                success: function (res) {
                    toggle.prop('disabled', false);
                    const isActive = res.is_active;

                    // Update UI badge
                    const badge = $(`#service-badge-${serviceId}`);
                    const avatar = $(`#service-avatar-${serviceId}`);

                    if (isActive) {
                        badge.removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success').text('Active');
                        avatar.removeClass('bg-light-secondary text-secondary').addClass('bg-light-primary text-primary');
                    } else {
                        badge.removeClass('bg-success-subtle text-success').addClass('bg-danger-subtle text-danger').text('Offline');
                        avatar.removeClass('bg-light-primary text-primary').addClass('bg-light-secondary text-secondary');
                    }

                    // Update stats counters
                    let activeCount = parseInt($('#stat-active-count').text()) || 0;
                    let inactiveCount = parseInt($('#stat-inactive-count').text()) || 0;

                    if (isActive) {
                        activeCount++;
                        inactiveCount = Math.max(0, inactiveCount - 1);
                    } else {
                        activeCount = Math.max(0, activeCount - 1);
                        inactiveCount++;
                    }

                    $('#stat-active-count').text(activeCount);
                    $('#stat-inactive-count').text(inactiveCount);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2500,
                        icon: 'success',
                        title: res.message || `${serviceName} status updated!`
                    });
                },
                error: function (xhr) {
                    toggle.prop('disabled', false);
                    toggle.prop('checked', !isChecked); // Revert

                    let errorMsg = 'Failed to update service status.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMsg,
                        confirmButtonColor: '#7266ed'
                    });
                }
            });
        });

        // Open Edit Service Modal
        $(document).on('click', '.btn-open-edit-service', function () {
            const btn = $(this);
            const service = btn.data('service');
            const actionUrl = btn.data('action');

            $('#formEditService').attr('action', actionUrl);
            $('#edit_service_name').val(service.name);
            $('#edit_service_code').val(service.code);
            $('#edit_service_category').val(service.category);
            $('#edit_service_icon').val(service.icon);
            $('#edit_service_description').val(service.description);
            $('#edit_service_headline').val(service.unavailable_headline);
            $('#edit_service_message').val(service.maintenance_message);
            $('#edit_service_is_active').prop('checked', !!service.is_active);

            $('#modalEditService').modal('show');
        });
    });
</script>
@endsection
