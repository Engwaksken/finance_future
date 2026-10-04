@extends('layouts.frontend')

@section('title','Finance & Future Technology with Mark Mpirirwe')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        @if($featured)
            <article class="featured">
                <div class="eyebrow">{{ $featured->category?->name ?? 'Featured' }}</div>
                <h1><a href="{{ route('articles.show',$featured->slug) }}">{{ $featured->title }}</a></h1>
                <p>{{ $featured->excerpt }}</p>
                <div class="meta" style="color:#c8d6df">
                    {{ $featured->author?->name }} · {{ optional($featured->published_at)->format('d M Y') }} · {{ $featured->reading_time }} min read
                </div>
            </article>
        @else
            <article class="featured">
                <div class="eyebrow">Finance meets innovation</div>
                <h1>Where Finance Meets the Future</h1>
                <p>Navigate the intersection of finance and future technology with Mark Mpirirwe, exploring the trends, breakthroughs and policy shifts shaping the future of finance.</p>
            </article>
        @endif

        <aside class="side-intro">
            <div class="eyebrow">The platform</div>
            <h2>Understand the forces reshaping money, markets and financial systems.</h2>
            <p>Analysis and conversations on banking, FinTech, artificial intelligence, digital payments, regulation, investment and innovation across Uganda, Africa and the wider world.</p>
            <a class="btn btn-primary" href="{{ route('articles.index') }}">Explore latest insights</a>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <div><div class="eyebrow">New thinking</div><h2>Latest Insights</h2></div>
            <a href="{{ route('articles.index') }}">View all <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="grid">
            @forelse($latest as $article)
                <article class="card">
                    @if($article->featured_image)
                        <img class="thumb" src="{{ asset('storage/'.$article->featured_image) }}" alt="{{ $article->title }}">
                    @else
                        <div class="thumb"></div>
                    @endif
                    <div class="card-body">
                        <div class="eyebrow">{{ $article->category?->name }}</div>
                        <h3><a href="{{ route('articles.show',$article->slug) }}">{{ $article->title }}</a></h3>
                        <div class="meta">{{ optional($article->published_at)->format('d M Y') }} · {{ $article->reading_time }} min</div>
                    </div>
                </article>
            @empty
                <p>No articles have been published yet.</p>
            @endforelse
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><div><div class="eyebrow">Explore</div><h2>Core Topics</h2></div></div>
        <div class="grid">
            @foreach($categories->take(8) as $category)
                <a class="card" href="{{ route('categories.show',$category->slug) }}">
                    <div class="card-body">
                        <div class="eyebrow">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</div>
                        <h3>{{ $category->name }}</h3>
                        <p>{{ $category->description }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="section" id="newsletter">
    <div class="container newsletter">
        <div class="eyebrow">Stay ahead</div>
        <h2>Finance & Future Technology Newsletter</h2>
        <p>Receive selected insights on finance, technology, innovation and policy.</p>
        <form method="POST" action="{{ route('newsletter.subscribe') }}">
            @csrf
            <input name="name" type="text" placeholder="Your name">
            <input name="email" type="email" placeholder="Email address" required>
            <button class="btn btn-primary" style="background:#b99146;border-color:#b99146" type="submit">Subscribe</button>
        </form>
    </div>
</section>
@endsection
