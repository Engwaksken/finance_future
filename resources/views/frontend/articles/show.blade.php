@extends('layouts.frontend')
@section('title',$article->seo_title ?: $article->title)
@section('meta_description',$article->seo_description ?: ($article->excerpt ?: 'Finance & Future Technology'))
@php($template = $article->page_template ?: 'default')
@push('styles')
@include('frontend.articles.partials.body-styles')
@if($isPreview ?? false)<meta name="robots" content="noindex,nofollow">@endif
{{-- Builder CSS is generated server-side from validated, typed values only. --}}
@if($article->usesBuilder() && $article->layout_css)<style>{!! str_ireplace('</style', '<\/style', $article->layout_css) !!}</style>@endif
@endpush
@section('content')
@if($isPreview ?? false)
    <div class="article-preview-bar" role="status"><i class="fa-solid fa-eye"></i> Preview: this version is not saved or published yet.</div>
@endif
<article class="article-wrap article-wrap--{{ $template }}">
    @if($template !== 'canvas')
        <header class="article-header">
            <div class="eyebrow">{{ $article->category?->name }}</div>
            <h1>{{ $article->title }}</h1>
            @if($article->excerpt)<p class="article-excerpt">{{ $article->excerpt }}</p>@endif
            <div class="article-meta">{{ $article->author?->name }} · {{ optional($article->published_at)->format('d M Y') }} · {{ $article->reading_time }} min read</div>
            @if($article->featured_image)<img class="article-featured" src="{{ asset('storage/'.$article->featured_image) }}" alt="{{ $article->title }}">@endif
        </header>
    @endif
    @if($article->usesBuilder())
        {{-- Rendered by App\Services\PageBuilder from a validated layout; text is escaped and rich text sanitized. --}}
        <div class="article-body article-body--builder">{!! $article->body !!}</div>
    @else
        {{-- Body is sanitized on save; MediaEmbed also upgrades media links in articles saved before embeds existed. --}}
        <div class="article-body">{!! \App\Services\MediaEmbed::convert($article->body) !!}</div>
    @endif
    @if($template !== 'canvas' && $article->tags->count())
        <div class="article-tags">@foreach($article->tags as $tag)<span>#{{ $tag->name }}</span>@endforeach</div>
    @endif
</article>
@if($related->count())
<section class="related">
    <div class="section-head"><h2>Related Reading</h2></div>
    <div class="grid">
        @foreach($related as $item)
        <article class="card"><div class="card-body"><div class="eyebrow">{{ $item->category?->name }}</div><h3><a href="{{ route('articles.show',$item->slug) }}">{{ $item->title }}</a></h3></div></article>
        @endforeach
    </div>
</section>
@endif
@endsection
