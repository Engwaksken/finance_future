<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Finance & Future Technology')</title>
    <meta name="description" content="@yield('meta_description', 'Finance, future technology, innovation and policy with Mark Mpirirwe.')">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230b3b60'/%3E%3Ctext x='16' y='22' font-family='Arial,sans-serif' font-size='16' font-weight='700' fill='%23b99146' text-anchor='middle'%3EF%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/frontend.css'])
    @stack('styles')
</head>
<body>
<div class="topbar">
    <div class="container inner">
        <div><a href="{{ route('articles.index') }}">Latest</a><a href="{{ route('about') }}">About Mark</a><a href="#">Contact</a></div>
        <div><a href="#"><i class="fa-brands fa-linkedin-in"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div>
    </div>
</div>

<header class="brand-row">
    <div class="container inner">
        <a class="brand" href="{{ route('home') }}">
            <strong>Finance & Future Technology</strong>
            <span>with Mark Mpirirwe</span>
        </a>
        <div class="header-actions">
            <a class="btn" href="{{ route('articles.index') }}"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
            <a class="btn btn-primary" href="#newsletter"><i class="fa-regular fa-envelope"></i> Subscribe</a>
            @auth
                <a class="btn login" href="{{ route('admin.dashboard') }}">Dashboard</a>
            @else
                <a class="btn login" href="{{ route('login') }}">Sign In</a>
            @endauth
        </div>
    </div>
</header>

@php
    $navCategories = \App\Models\Category::where('is_active', true)->where('slug', '!=', 'events')->orderBy('sort_order')->get();
@endphp
<nav class="nav">
    <div class="container inner">
        <a href="{{ route('home') }}">Home</a>
        @foreach($navCategories as $navCategory)
            <a href="{{ route('categories.show', $navCategory->slug) }}">{{ $navCategory->name }}</a>
        @endforeach
        <a href="{{ route('events.index') }}">Events</a>
        <a href="{{ route('about') }}">About</a>
    </div>
</nav>

@if(isset($trending) && $trending->count())
<div class="trending">
    <div class="container inner">
        <strong>Trending:</strong>
        @foreach($trending as $item)
            <a href="{{ route('articles.show',$item->slug) }}">{{ $item->title }}</a>
        @endforeach
    </div>
</div>
@endif

@if(session('success'))
<div class="container flash">{{ session('success') }}</div>
@endif

<main>@yield('content')</main>

<footer>
    <div class="container footer-grid">
        <div>
            <h4>Finance & Future Technology</h4>
            <p>Exploring where money meets innovation with Mark Mpirirwe.</p>
        </div>
        <div><h4>Topics</h4><p>Finance<br>FinTech<br>Future Tech<br>Policy</p></div>
        <div><h4>Media</h4><p>Interviews<br>Podcasts<br>Videos<br>Events</p></div>
        <div><h4>Platform</h4><p>About<br>Newsletter<br>Contact<br>Privacy</p></div>
    </div>
</footer>
</body>
</html>
