<!-- Sidenav Menu Start -->
<div class="sidenav-menu">

    <!-- Brand Logo -->
    <a href="{{ route('admin.dashboard') }}" class="logo">
        <span class="logo-light">
            <span class="logo-lg"><img src="{{ asset('admin/assets/images/logo.png') }}" alt="logo"></span>
            <span class="logo-sm"><img src="{{ asset('admin/assets/images/logo-sm.png') }}" alt="small logo"></span>
        </span>

        <span class="logo-dark">
            <span class="logo-lg"><img src="{{ asset('admin/assets/images/logo-dark.png') }}" alt="dark logo"></span>
            <span class="logo-sm"><img src="{{ asset('admin/assets/images/logo-sm.png') }}" alt="small logo"></span>
        </span>
    </a>

    <!-- Sidebar Hover Menu Toggle Button -->
    <button class="button-sm-hover">
        <i class="ti ti-circle align-middle"></i>
    </button>

    <!-- Full Sidebar Menu Close Button -->
    <button class="button-close-fullsidebar">
        <i class="ti ti-x align-middle"></i>
    </button>

    <div data-simplebar>

        <!--- Sidenav Menu -->
        <ul class="side-nav">
            <li class="side-nav-title">Navigation</li>

            <li class="side-nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <a href="{{ route('admin.dashboard') }}" class="side-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-dashboard"></i></span>
                    <span class="menu-text"> Dashboard </span>
                </a>
            </li>

            <li class="side-nav-title mt-2">Management</li>

            <li class="side-nav-item {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.customers.index') ? route('admin.customers.index') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-users"></i></span>
                    <span class="menu-text"> Customers </span>
                </a>
            </li>

            <li class="side-nav-item {{ request()->routeIs('admin.funding-agents*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.funding-agents.index') ? route('admin.funding-agents.index') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.funding-agents*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-wallet"></i></span>
                    <span class="menu-text"> Funding Agents </span>
                </a>
            </li>

            <li class="side-nav-item {{ request()->routeIs('admin.providers*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.providers.index') ? route('admin.providers.index') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.providers*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-api"></i></span>
                    <span class="menu-text"> Providers (3rd Party API) </span>
                </a>
            </li>

            <li class="side-nav-item {{ request()->routeIs('admin.services*') ? 'active' : '' }}">
                <a href="{{ route('admin.services.index') }}" class="side-nav-link {{ request()->routeIs('admin.services*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-apps"></i></span>
                    <span class="menu-text"> Korak Services </span>
                </a>
            </li>

            <li class="side-nav-title mt-2">System & Maintenance</li>

            <li class="side-nav-item {{ request()->routeIs('admin.failed-jobs*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.failed-jobs.index') ? route('admin.failed-jobs.index') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.failed-jobs*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-alert-triangle"></i></span>
                    <span class="menu-text"> Failed Jobs </span>
                </a>
            </li>

            <li class="side-nav-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.settings.index') ? route('admin.settings.index') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-settings"></i></span>
                    <span class="menu-text"> Site Settings </span>
                </a>
            </li>

            <li class="side-nav-item {{ request()->routeIs('admin.admins*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.admins.index') ? route('admin.admins.index') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.admins*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-shield-lock"></i></span>
                    <span class="menu-text"> Admins </span>
                </a>
            </li>

            <li class="side-nav-title mt-2">Account</li>

            <li class="side-nav-item {{ request()->routeIs('admin.profile*') ? 'active' : '' }}">
                <a href="{{ Route::has('admin.profile') ? route('admin.profile') : 'javascript:void(0);' }}" class="side-nav-link {{ request()->routeIs('admin.profile*') ? 'active' : '' }}">
                    <span class="menu-icon"><i class="ti ti-user-circle"></i></span>
                    <span class="menu-text"> My Profile </span>
                </a>
            </li>
        </ul>

        <div class="clearfix"></div>
    </div>
</div>
<!-- Sidenav Menu End -->
