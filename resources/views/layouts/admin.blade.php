<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <meta name="csrf-token"
          content="{{ csrf_token() }}">

    <title>
        @yield('title', 'CMS') | Finance & Future Technology
    </title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    @vite([
        'resources/css/admin.css',
        'resources/js/app.js'
    ])

    @stack('styles')
</head>

<body>

<div class="app-shell">

    <aside class="sidebar"
           id="adminSidebar">

        <div class="sidebar-brand">

            <strong>
                Finance & Future Technology
            </strong>

            <span>
                Administration
            </span>

        </div>


        {{-- OVERVIEW --}}
        <div class="sidebar-section">
            Overview
        </div>

        <nav class="sidebar-menu">

            <a href="{{ route('admin.dashboard') }}"
               class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">

                <i class="fa-solid fa-chart-line"></i>

                <span>
                    Dashboard
                </span>

            </a>

        </nav>


        {{-- CONTENT MANAGEMENT --}}
        <div class="sidebar-section">
            Content Management
        </div>

        <nav class="sidebar-menu">

            <a href="{{ route('admin.articles.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}">

                <i class="fa-regular fa-newspaper"></i>

                <span>
                    Articles
                </span>

            </a>


            <a href="{{ route('admin.categories.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">

                <i class="fa-solid fa-layer-group"></i>

                <span>
                    Categories
                </span>

            </a>

        </nav>


        {{-- USER MANAGEMENT --}}
        <div class="sidebar-section">
            User Management
        </div>

        <nav class="sidebar-menu">

            <a href="{{ route('admin.users.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">

                <i class="fa-solid fa-users"></i>

                <span>
                    Users
                </span>

            </a>


            <a href="{{ route('admin.roles.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">

                <i class="fa-solid fa-user-shield"></i>

                <span>
                    Roles & Permissions
                </span>

            </a>

        </nav>


        {{-- SYSTEM --}}
        <div class="sidebar-section">
            System
        </div>

        <nav class="sidebar-menu">

            <a href="{{ route('admin.settings.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">

                <i class="fa-solid fa-gear"></i>

                <span>
                    Site Settings
                </span>

            </a>

        </nav>


        {{-- WEBSITE --}}
        <div class="sidebar-section">
            Website
        </div>

        <nav class="sidebar-menu">

            <a href="{{ route('home') }}"
               target="_blank"
               class="sidebar-link">

                <i class="fa-solid fa-arrow-up-right-from-square"></i>

                <span>
                    View Website
                </span>

            </a>

        </nav>

    </aside>


    <div class="main-area">

        <header class="admin-topbar">

            <div class="admin-topbar-left">

                <button type="button"
                        class="mobile-menu-btn"
                        id="sidebarToggle"
                        aria-label="Toggle sidebar">

                    <i class="fa-solid fa-bars"></i>

                </button>


                <h1 class="page-title">

                    @yield('heading', 'Dashboard')

                </h1>

            </div>


            <div class="admin-user">

                <div class="admin-avatar">

                    {{
                        strtoupper(
                            substr(
                                auth()->user()->name ?? 'A',
                                0,
                                1
                            )
                        )
                    }}

                </div>


                <span class="admin-user-name">

                    {{ auth()->user()->name ?? 'Administrator' }}

                </span>

            </div>

        </header>


        <main class="content">

            @if(session('success'))

                <div class="alert alert-success auto-dismiss">

                    <i class="fa-solid fa-circle-check"></i>

                    {{ session('success') }}

                </div>

            @endif


            @if(session('error'))

                <div class="alert alert-danger auto-dismiss">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    {{ session('error') }}

                </div>

            @endif


            @if($errors->any())

                <div class="alert alert-danger">

                    @foreach($errors->all() as $error)

                        <div>
                            {{ $error }}
                        </div>

                    @endforeach

                </div>

            @endif


            @yield('content')

        </main>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const sidebar =
        document.getElementById('adminSidebar');

    const toggle =
        document.getElementById('sidebarToggle');


    /*
    |--------------------------------------------------------------------------
    | Mobile Sidebar
    |--------------------------------------------------------------------------
    */

    if (toggle && sidebar) {

        toggle.addEventListener(
            'click',
            function () {

                sidebar.classList.toggle('open');

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Modal Handling
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        function (event) {

            const opener =
                event.target.closest(
                    '[data-modal-open]'
                );


            if (opener) {

                const modal =
                    document.getElementById(
                        opener.dataset.modalOpen
                    );


                if (modal) {

                    modal.classList.add(
                        'active'
                    );

                    document.body.style.overflow =
                        'hidden';

                }

            }


            const closer =
                event.target.closest(
                    '[data-modal-close]'
                );


            if (closer) {

                const modal =
                    closer.closest(
                        '.modal-backdrop-custom'
                    );


                if (modal) {

                    modal.classList.remove(
                        'active'
                    );

                    document.body.style.overflow =
                        '';

                }

            }


            if (
                event.target.classList.contains(
                    'modal-backdrop-custom'
                )
            ) {

                event.target.classList.remove(
                    'active'
                );

                document.body.style.overflow =
                    '';

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape Key Closes Modal
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {

                document
                    .querySelectorAll(
                        '.modal-backdrop-custom.active'
                    )
                    .forEach(function (modal) {

                        modal.classList.remove(
                            'active'
                        );

                    });


                document.body.style.overflow =
                    '';

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Auto-dismiss Flash Messages
    |--------------------------------------------------------------------------
    */

    setTimeout(
        function () {

            document
                .querySelectorAll(
                    '.auto-dismiss'
                )
                .forEach(function (element) {

                    element.style.opacity =
                        '0';

                    element.style.transform =
                        'translateY(-4px)';


                    setTimeout(
                        function () {

                            element.remove();

                        },
                        400
                    );

                });

        },
        5000
    );

});
</script>


@stack('scripts')

</body>
</html>