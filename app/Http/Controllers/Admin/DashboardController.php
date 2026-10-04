<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\NewsletterSubscriber;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'published' => Article::published()->count(),
            'drafts' => Article::where('status', 'draft')->count(),
            'scheduled' => Article::where('status', 'scheduled')->count(),
            'categories' => Category::count(),
            'subscribers' => NewsletterSubscriber::where('status', 'active')->count(),
            'views' => Article::sum('views_count'),
        ];

        $recent = Article::with(['author', 'category'])->latest()->take(8)->get();

        return view('admin.dashboard', compact('stats', 'recent'));
    }
}
