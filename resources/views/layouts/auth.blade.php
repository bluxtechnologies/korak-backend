<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ $title ?? 'Administrator' }} - Korak Digitals</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="KorakDigital Solutions" name="description" />
    <meta content="KorakDigitals" name="author" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('admin/assets/images/favicon.ico') }}">

    <!-- Theme Config Js -->
    <script src="{{ asset('admin/assets/js/config.js') }}"></script>

    <!-- Vendor css -->
    <link href="{{ asset('admin/assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- App css -->
    <link href="{{ asset('admin/assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />

    <!-- Icons css -->
    <link href="{{ asset('admin/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Toastr css (Local with CDN fallback) -->
    <link rel="stylesheet" href="{{ asset('admin/assets/vendor/toastr/toastr.min.css') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" />

    <style>
        /* Ensure Toastr displays above Bootstrap 5 modals/cards and overrides Bootstrap .toast hiding */
        #toast-container {
            position: fixed !important;
            z-index: 9999999 !important;
            pointer-events: auto !important;
        }
        #toast-container > .toast {
            display: block !important;
            opacity: 0.95 !important;
        }
        #toast-container > .toast:hover {
            opacity: 1 !important;
        }
    </style>
</head>

<body>

    <div class="auth-bg d-flex min-vh-100 justify-content-center align-items-center">
        <div class="row g-0 justify-content-center w-100 m-xxl-5 px-xxl-4 m-3">
            <div class="col-xl-4 col-lg-5 col-md-6">
                <div class="card overflow-hidden text-center h-100 p-xxl-4 p-3 mb-0">
                    <a href="{{ route('admin.login') }}" class="auth-brand mb-3">
                        <img src="{{ asset('admin/assets/images/logo-dark.png') }}" alt="dark logo" height="24" class="logo-dark">
                        <img src="{{ asset('admin/assets/images/logo.png') }}" alt="logo light" height="24" class="logo-light">
                    </a>

                    @yield('content')

                </div>
            </div>
        </div>
    </div>

    <!-- Vendor js (includes jQuery 3.7.1) -->
    <script src="{{ asset('admin/assets/js/vendor.min.js') }}"></script>

    <!-- Lucide fallback guard -->
    <script>
        window.lucide = window.lucide || { createIcons: function() {} };
    </script>

    <!-- App js -->
    <script src="{{ asset('admin/assets/js/app.js') }}"></script>

    <!-- Toastr js (Local asset with CDN fallback) -->
    <script src="{{ asset('admin/assets/vendor/toastr/toastr.min.js') }}"></script>
    <script>
        if (typeof window.toastr === 'undefined') {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"><\/script>');
        }
    </script>

    <script>
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "4000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };
        }

        if (typeof $ !== 'undefined') {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                }
            });
        }
    </script>

    @yield('scripts')

</body>

</html>
