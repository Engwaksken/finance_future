<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | Finance & Future Technology</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|manrope:500,600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/frontend.css', 'resources/css/auth.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    @include('partials.site-header')

    <div class="auth-shell">
        <aside class="auth-brand-panel" aria-label="Finance & Future Technology">
            <a class="auth-brand" href="{{ route('home') }}">
                <span class="auth-brand-mark" aria-hidden="true">F<span>+</span></span>
                <span>Finance &amp;<br>Future Technology</span>
            </a>

            <div class="auth-brand-content">
                <span class="auth-eyebrow">Finance, technology, and you</span>
                <h2>Good ideas.<br>Fresh perspectives.</h2>
                <p>A place to explore what’s happening in finance and technology, with Mark Mpirirwe.</p>
                <div class="auth-topics" aria-label="Our focus">
                    <span>Finance</span><span>Technology</span><span>Innovation</span>
                </div>
            </div>

            <a class="auth-home-link" href="{{ route('home') }}"><span aria-hidden="true">&larr;</span> Back to the website</a>
        </aside>

        <main class="auth-form-panel">
            <div class="auth-card">
                <header class="auth-heading">
                    <span class="auth-eyebrow">@yield('eyebrow')</span>
                    <h1>@yield('heading')</h1>
                    <p>@yield('description')</p>
                </header>

                @if(session('status'))
                    <div class="auth-notice" role="status">{{ session('status') }}</div>
                @endif

                @yield('content')
            </div>

            <footer class="auth-footer">&copy; {{ date('Y') }} Finance &amp; Future Technology</footer>
        </main>
    </div>
</body>
</html>
