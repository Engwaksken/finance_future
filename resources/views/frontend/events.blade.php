@extends('layouts.frontend')
@section('title', 'Events | Finance & Future Technology')
@section('content')
<section class="section">
    <div class="container">
        <div class="eyebrow">Conversations &amp; gatherings</div>
        <h1>Events</h1>
        <div class="search-results">
            @forelse($events as $event)
                <article class="search-result">
                    <span class="meta">{{ $event->starts_at->format('d M Y') }} · {{ $event->venue }}</span>
                    <h2><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></h2>
                    <p>{{ $event->summary }}</p>
                </article>
            @empty
                <p>Conferences, webinars, discussions and appearances will be published here.</p>
            @endforelse
        </div>
        {{ $events->links('pagination::bootstrap-4') }}
    </div>
</section>
@endsection
