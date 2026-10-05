<div class="topbar">
    <div class="container inner">
        <div class="topbar-links">
            <a href="{{ route('articles.index') }}">Latest</a>
            <a href="{{ route('about') }}">About Mark</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>
        <a href="{{ route('home') }}#newsletter">Newsletter</a>
    </div>
</div>

<header class="brand-row">
    <div class="container inner">
        <a class="brand" href="{{ route('home') }}">
            <strong>Finance &amp; Future Technology</strong>
            <span>with Mark Mpirirwe</span>
        </a>

        <div class="header-actions">
            <form class="site-search" action="{{ route('search') }}" method="GET" role="search">
                <label class="visually-hidden" for="site-search">Search the site</label>
                <input id="site-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                       placeholder="Search the site…" maxlength="200" required>
                <button type="submit" aria-label="Search the site">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>
                    </svg>
                </button>
            </form>
            <a class="btn btn-primary" href="{{ route('home') }}#newsletter">Subscribe</a>
            @auth
                <a class="btn login" href="{{ route('dashboard') }}">Dashboard</a>
            @else
                <a class="btn login" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Sign in</a>
                @if(Route::has('register'))
                    <a class="btn login" href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>Register</a>
                @endif
            @endauth
        </div>
    </div>
</header>

<nav class="nav" aria-label="Main navigation">
    <div class="container inner">
        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
        <a href="{{ route('articles.index') }}" @if(request()->routeIs('articles.*')) aria-current="page" @endif>Articles</a>
        @foreach($siteTopics as $topic)
            <a href="{{ $topic['url'] }}"
               @if((request()->routeIs('categories.show') && request()->route('slug') === $topic['slug']) || (request()->routeIs('articles.index') && request('category') === $topic['slug'])) aria-current="page" @endif>
                {{ $topic['label'] }}
            </a>
        @endforeach
        <a href="{{ route('events.index') }}" @if(request()->routeIs('events.*')) aria-current="page" @endif>Events</a>
        <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
    </div>
</nav>
