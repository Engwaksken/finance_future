<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;

class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::published()
            ->with(['author', 'category'])
            ->latest('published_at')
            ->paginate(12);

        return view('frontend.articles.index', compact('articles'));
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
