@extends('layouts.frontend')
@section('title',$category->name.' | Finance & Future Technology')
@section('content')
<section class="section">
<div class="container">
    <div class="section-head"><div><x-category-visual :category="$category" /><div class="eyebrow">Topic</div><h2>{{ $category->name }}</h2><p>{{ $category->description }}</p></div></div>
    <div class="grid">
        @forelse($articles as $article)
            <article class="card">
                @if($article->featured_image)<img class="thumb" src="{{ asset('storage/'.$article->featured_image) }}" alt="{{ $article->title }}">@else<div class="thumb"></div>@endif
                <div class="card-body">
                    <h3><a href="{{ route('articles.show',$article->slug) }}">{{ $article->title }}</a></h3>
                    <div class="meta">{{ optional($article->published_at)->format('d M Y') }} · {{ $article->reading_time }} min</div>
                </div>
            </article>
        @empty
            <p>No published articles in this topic yet.</p>
        @endforelse
    </div>
    <div style="margin-top:28px">{{ $articles->links('pagination::bootstrap-4') }}</div>
</div>
</section>
@endsection
