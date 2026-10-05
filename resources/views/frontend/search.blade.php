@extends('layouts.frontend')
@section('title', 'Search | Finance & Future Technology')

@section('content')
<section class="section">
    <div class="container">
        <div class="eyebrow">Explore the platform</div>
        <h1>Search the site</h1>
        @if($term === '')
            <p>Use the search bar to find articles, events, topics, and pages.</p>
        @else
            <p>{{ $total }} {{ Str::plural('result', $total) }} for “{{ $term }}”</p>
            @if($total === 0)
                <div class="search-empty">Nothing matched your search. Try a different name, topic, or keyword.</div>
            @endif

            @if($articles->total())
                <h2>Articles</h2>
                <div class="search-results">
                    @foreach($articles as $article)
                        <article class="search-result">
                            <span class="eyebrow">{{ $article->category?->name ?? 'Article' }}</span>
                            <h3><a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a></h3>
                            <p>{{ Str::limit(strip_tags($article->excerpt ?: $article->body), 200) }}</p>
                        </article>
                    @endforeach
                </div>
                {{ $articles->links('pagination::bootstrap-4') }}
            @endif

            @if($events->total())
                <h2>Events</h2>
                <div class="search-results">
                    @foreach($events as $event)
                        <article class="search-result">
                            <span class="meta">{{ $event->starts_at->format('d M Y') }} · {{ $event->venue }}</span>
                            <h3><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></h3>
                            <p>{{ Str::limit(strip_tags($event->summary ?: $event->description ?? ''), 200) }}</p>
                        </article>
                    @endforeach
                </div>
                {{ $events->links('pagination::bootstrap-4') }}
            @endif

            @if($topics->total())
                <h2>Topics</h2>
                <div class="search-results">
                    @foreach($topics as $topic)
                        <article class="search-result">
                            <h3><a href="{{ route('categories.show', $topic->slug) }}">{{ $topic->name }}</a></h3>
                            <p>{{ $topic->description }}</p>
                        </article>
                    @endforeach
                </div>
                {{ $topics->links('pagination::bootstrap-4') }}
            @endif

            @if($pages->isNotEmpty())
                <h2>Pages</h2>
                <div class="search-results">
                    @foreach($pages as $page)
                        <article class="search-result">
                            <h3><a href="{{ route($page['route']).($page['fragment'] ?? '') }}">{{ $page['title'] }}</a></h3>
                            <p>{{ $page['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</section>
@endsection
