<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $term = trim($data['q'] ?? '');
        $articles = $events = $topics = null;
        $pages = collect();

        if ($term !== '') {
            $pattern = "%{$term}%";
            $articles = Article::published()->with('category')
                ->where(fn ($query) => $query->where('title', 'like', $pattern)
                    ->orWhere('excerpt', 'like', $pattern)
                    ->orWhere('body', 'like', $pattern)
                    ->orWhereHas('category', fn ($category) => $category->where('is_active', true)
                        ->where(fn ($query) => $query->where('name', 'like', $pattern)
                            ->orWhere('description', 'like', $pattern)))
                    ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', $pattern)))
                ->latest('published_at')->paginate(10, ['*'], 'articles_page')->withQueryString();

            $events = Event::where('status', 'published')
                ->where(fn ($query) => $query->where('title', 'like', $pattern)
                    ->orWhere('summary', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern)
                    ->orWhere('venue', 'like', $pattern))
                ->orderBy('starts_at')->paginate(6, ['*'], 'events_page')->withQueryString();

            $topics = Category::where('is_active', true)
                ->where(fn ($query) => $query->where('name', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern))
                ->orderBy('sort_order')->paginate(6, ['*'], 'topics_page')->withQueryString();

            $pages = collect(config('site.pages'))->filter(fn ($page) => Str::contains(
                Str::lower($page['title'].' '.$page['description']), Str::lower($term)
            ));
        }

        $total = ($articles?->total() ?? 0) + ($events?->total() ?? 0)
            + ($topics?->total() ?? 0) + $pages->count();

        return view('frontend.search', compact('term', 'articles', 'events', 'topics', 'pages', 'total'));
    }
}
