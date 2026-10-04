<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Event;

class HomeController extends Controller
{
    public function index()
    {
        $featured = Article::published()->with(['author', 'category'])
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        $latest = Article::published()->with(['author', 'category'])
            ->when($featured, fn ($q) => $q->whereKeyNot($featured->id))
            ->latest('published_at')
            ->take(8)
            ->get();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $trending = Article::published()
            ->where('is_trending', true)
            ->latest('published_at')
            ->take(5)
            ->get();

        $events = Event::query()
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->take(3)
            ->get();

        return view('frontend.home', compact(
            'featured', 'latest', 'categories', 'trending', 'events'
        ));
    }
}
