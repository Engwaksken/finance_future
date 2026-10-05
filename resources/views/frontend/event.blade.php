@extends('layouts.frontend')
@section('title', $event->title.' | Finance & Future Technology')
@section('content')
<section class="section">
    <article class="container page-copy">
        <div class="eyebrow">Event</div>
        <h1>{{ $event->title }}</h1>
        <p class="meta">{{ $event->starts_at->format('d M Y, H:i') }} · {{ $event->venue }}</p>
        <p>{{ $event->summary }}</p>
        <div class="event-description">{{ strip_tags($event->description ?? '') }}</div>
        @if($event->registration_url && preg_match('/^https?:\/\//i', $event->registration_url))
            <p><a class="btn btn-primary" href="{{ $event->registration_url }}" rel="noopener noreferrer">Register for this event</a></p>
        @endif
        <a class="btn" href="{{ route('events.index') }}">Back to events</a>
    </article>
</section>
@endsection
