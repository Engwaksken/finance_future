<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
        $term = trim($filters['q'] ?? '');
        $categorySlug = $filters['category'] ?? null;

        $articles = Article::published()
            ->with(['author', 'category'])
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('body', 'like', "%{$term}%")))
            ->when($categorySlug, fn ($query) => $query->whereHas('category', fn ($category) => $category
                ->where('slug', $categorySlug)->where('is_active', true)))
            ->latest('published_at')
            ->paginate(12)->withQueryString();

        $categoryLabel = $categorySlug
            ? (Category::where('slug', $categorySlug)->where('is_active', true)->value('name')
                ?? config("site.topics.{$categorySlug}"))
            : null;

        return view('frontend.articles.index', compact('articles', 'term', 'categoryLabel'));
    }

    public function show(string $slug)
    {
        $article = Article::published()
            ->with(['author', 'category', 'tags'])
            ->where('slug', $slug)
            ->firstOrFail();

        $article->increment('views_count');

        $related = Article::published()
            ->where('category_id', $article->category_id)
            ->whereKeyNot($article->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        return view('frontend.articles.show', compact('article', 'related'));
    }

    public function category(string $slug)
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $articles = $category->articles()
            ->published()
            ->with(['author', 'category'])
            ->latest('published_at')
            ->paginate(12);

        return view('frontend.articles.category', compact('category', 'articles'));
    }
}
