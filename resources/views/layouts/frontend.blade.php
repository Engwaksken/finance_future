<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Finance & Future Technology')</title>
    <meta name="description" content="@yield('meta_description', 'Finance, future technology, innovation and policy with Mark Mpirirwe.')">
    @if($favicon = \App\Models\SiteSetting::valueOf('site_favicon'))
        <link rel="icon" href="{{ asset('storage/'.$favicon) }}">
    @else
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230b3b60'/%3E%3Ctext x='16' y='22' font-family='Arial,sans-serif' font-size='16' font-weight='700' fill='%23b99146' text-anchor='middle'%3EF%3C/text%3E%3C/svg%3E">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/frontend.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
@include('partials.site-header')

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

<main>@include('partials.page-slides')@yield('content')</main>

@include('partials.site-footer')
</body>
</html>
