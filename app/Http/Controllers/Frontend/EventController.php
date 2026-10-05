<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::where('status', 'published')->orderBy('starts_at')->paginate(12);

        return view('frontend.events', compact('events'));
    }

    public function show(string $slug)
    {
        $event = Event::where('status', 'published')->where('slug', $slug)->firstOrFail();

        return view('frontend.event', compact('event'));
    }
}
