@extends('layouts.frontend')
@section('title','Latest Articles | Finance & Future Technology')
@section('content')
<section class="section">
<div class="container">
    <div class="section-head"><div><div class="eyebrow">All coverage</div><h2>{{ $categoryLabel ?: 'Latest Articles' }}</h2></div></div>
    @if($term !== '')<p>Articles matching “{{ $term }}”</p>@endif
    <div class="grid">
        @forelse($articles as $article)
            <article class="card">
                @if($article->featured_image)<img class="thumb" src="{{ asset('storage/'.$article->featured_image) }}" alt="{{ $article->title }}">@else<div class="thumb"></div>@endif
                <div class="card-body">
                    <div class="eyebrow">{{ $article->category?->name }}</div>
                    <h3><a href="{{ route('articles.show',$article->slug) }}">{{ $article->title }}</a></h3>
                    <div class="meta">{{ optional($article->published_at)->format('d M Y') }} · {{ $article->reading_time }} min</div>
                </div>
            </article>
        @empty
            <p>No published articles found. Check back soon for more coverage.</p>
        @endforelse
    </div>
    <div style="margin-top:28px">{{ $articles->links('pagination::bootstrap-4') }}</div>
</div>
</section>
@endsection
