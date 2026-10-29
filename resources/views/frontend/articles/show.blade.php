@extends('layouts.frontend')
@section('title',$article->seo_title ?: $article->title)
@section('meta_description',$article->seo_description ?: ($article->excerpt ?: 'Finance & Future Technology'))
@push('styles')
<style>
.article-wrap{width:min(820px,92%);margin:48px auto}.article-wrap h1{font:800 48px/1.08 Manrope;margin:12px 0 18px}
.article-body{font-size:18px;line-height:1.8}.article-body a{color:#0b5d91;text-decoration:underline;text-underline-offset:3px}.article-body a:hover{color:#083b5d}.article-body img{max-width:100%;height:auto}.article-meta{color:#667085;margin-bottom:28px}
.related{width:min(1180px,92%);margin:50px auto}
@media(max-width:600px){.article-wrap h1{font-size:36px}}
</style>
@endpush
@section('content')
<article class="article-wrap">
    <div class="eyebrow">{{ $article->category?->name }}</div>
    <h1>{{ $article->title }}</h1>
    <p style="font-size:20px;color:#667085">{{ $article->excerpt }}</p>
    <div class="article-meta">{{ $article->author?->name }} · {{ optional($article->published_at)->format('d M Y') }} · {{ $article->reading_time }} min read</div>
    @if($article->featured_image)<img style="width:100%;border-radius:16px;margin-bottom:28px" src="{{ asset('storage/'.$article->featured_image) }}" alt="{{ $article->title }}">@endif
    <div class="article-body">{!! $article->body !!}</div>
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
