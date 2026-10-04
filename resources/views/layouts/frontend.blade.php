<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Finance & Future Technology')</title>
    <meta name="description" content="@yield('meta_description', 'Finance, future technology, innovation and policy with Mark Mpirirwe.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        :root{--ink:#101820;--muted:#667085;--line:#e7e9ec;--soft:#f7f8fa;--brand:#0b3b60;--accent:#b99146;--white:#fff}
        *{box-sizing:border-box} body{margin:0;color:var(--ink);font-family:"DM Sans",sans-serif;background:#fff}
        a{color:inherit;text-decoration:none}.container{width:min(1180px,92%);margin:auto}
        .topbar{background:#0a2236;color:#d8e1e8;font-size:13px}.topbar .inner{height:38px;display:flex;align-items:center;justify-content:space-between}
        .topbar a{margin-right:18px}.brand-row{padding:22px 0;border-bottom:1px solid var(--line)}
        .brand-row .inner{display:flex;align-items:center;justify-content:space-between;gap:24px}
        .brand{font-family:Manrope,sans-serif}.brand strong{display:block;font-size:25px;letter-spacing:-.7px;color:var(--brand)}
        .brand span{font-size:13px;color:var(--muted)}.header-actions{display:flex;gap:10px;align-items:center}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 15px;border-radius:7px;border:1px solid var(--line);font-weight:700}
        .btn-primary{background:var(--brand);color:#fff;border-color:var(--brand)}
        .nav{position:sticky;top:0;z-index:30;background:#fff;border-bottom:1px solid var(--line)}
        .nav .inner{height:54px;display:flex;align-items:center;gap:25px;overflow:auto;white-space:nowrap}
        .nav a{font-weight:700;font-size:14px}.nav a:hover{color:var(--brand)}
        .trending{background:var(--soft);border-bottom:1px solid var(--line);font-size:13px}
        .trending .inner{padding:9px 0;display:flex;gap:14px;overflow:auto;white-space:nowrap}
        .trending strong{color:var(--brand)} main{min-height:60vh}
        .hero{padding:48px 0 30px}.hero-grid{display:grid;grid-template-columns:1.55fr .85fr;gap:32px}
        .featured{background:linear-gradient(135deg,#0b3b60,#071f33);min-height:400px;border-radius:18px;padding:42px;color:#fff;display:flex;flex-direction:column;justify-content:flex-end}
        .eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:1.2px;font-weight:800;color:var(--accent)}
        .featured h1{font:800 44px/1.05 Manrope,sans-serif;margin:12px 0}.featured p{color:#d9e5ed;max-width:780px}
        .side-intro{border:1px solid var(--line);border-radius:18px;padding:28px}.side-intro h2{font-family:Manrope;font-size:26px;margin-top:8px}
        .section{padding:34px 0}.section-head{display:flex;justify-content:space-between;align-items:end;margin-bottom:20px}
        .section-head h2{font:800 28px Manrope;margin:0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
        .card{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#fff}.card-body{padding:18px}
        .thumb{height:155px;background:#dfe6ec;display:block;object-fit:cover;width:100%}.card h3{font:700 18px/1.35 Manrope;margin:9px 0}
        .meta{font-size:12px;color:var(--muted)}.newsletter{background:#0a2236;color:#fff;border-radius:18px;padding:34px}
        .newsletter form{display:flex;gap:10px;flex-wrap:wrap}.newsletter input{padding:13px 14px;border-radius:7px;border:0;min-width:230px}
        footer{margin-top:45px;background:#081a29;color:#cfd8df;padding:42px 0}.footer-grid{display:grid;grid-template-columns:2fr repeat(3,1fr);gap:28px}
        footer h4{color:#fff}.flash{margin:18px auto;padding:12px 15px;border-radius:8px;background:#ecfdf3;color:#05603a}
        @media(max-width:900px){.hero-grid{grid-template-columns:1fr}.grid{grid-template-columns:repeat(2,1fr)}.topbar{display:none}.header-actions .login{display:none}.featured h1{font-size:35px}}
        @media(max-width:560px){.grid{grid-template-columns:1fr}.featured{min-height:350px;padding:26px}.featured h1{font-size:31px}.brand strong{font-size:20px}.newsletter form{display:block}.newsletter input,.newsletter button{width:100%;margin-top:8px}.footer-grid{grid-template-columns:1fr}}
    </style>
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

<nav class="nav">
    <div class="container inner">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('categories.show','finance') }}">Finance</a>
        <a href="{{ route('categories.show','fintech') }}">FinTech</a>
        <a href="{{ route('categories.show','future-tech') }}">Future Tech</a>
        <a href="{{ route('categories.show','policy-regulation') }}">Policy</a>
        <a href="{{ route('categories.show','insights') }}">Insights</a>
        <a href="{{ route('categories.show','interviews') }}">Interviews</a>
        <a href="{{ route('categories.show','media') }}">Media</a>
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
