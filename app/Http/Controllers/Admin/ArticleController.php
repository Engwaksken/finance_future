<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Services\HtmlSanitizer;
use App\Support\AdminPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:draft,review,scheduled,published,archived'],
        ]);
        $period = AdminPeriod::fromRequest($request);
        $query = $period->apply(Article::query())
            ->when($request->filled('q'), function ($q) use ($filters) {
                $term = trim($filters['q']);
                $q->where('title', 'like', "%{$term}%");
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $filters['status']));
        $stats = [
            'Total Articles' => (clone $query)->count(),
            'Published' => (clone $query)->where('status', 'published')->count(),
            'Drafts' => (clone $query)->where('status', 'draft')->count(),
            'Total Views' => (clone $query)->sum('views_count'),
        ];
        $articles = $query->with(['author', 'category', 'tags'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('admin.articles.index', compact('articles', 'categories', 'tags', 'stats', 'period'));
    }

    public function edit(Article $article)
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $tags = Tag::orderBy('name')->get();

        return view('admin.articles.form', compact('article', 'categories', 'tags'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['reading_time'] = $this->readingTime($data['body']);

        if ($request->hasFile('featured_image_file')) {
            $data['featured_image'] = $request->file('featured_image_file')->store('articles', 'public');
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $article = Article::create($data);
        $article->tags()->sync($data['tags'] ?? []);
        $request->attributes->set('audit_subject', $article);

        return back()->with('success', 'Article created.');
    }

    public function update(Request $request, Article $article)
    {
        $data = $this->validated($request);
        $data['reading_time'] = $this->readingTime($data['body']);

        if ($request->hasFile('featured_image_file')) {
            if ($article->featured_image) {
                Storage::disk('public')->delete($article->featured_image);
            }

            $data['featured_image'] = $request->file('featured_image_file')->store('articles', 'public');
        }

        if ($data['status'] === 'published' && ! $article->published_at) {
            $data['published_at'] = now();
        }

        $article->update($data);
        $article->tags()->sync($data['tags'] ?? []);

        return back()->with('success', 'Article updated.');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return back()->with('success', 'Article moved to trash.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > HtmlSanitizer::MAX_INPUT_LENGTH) {
                    $fail('The body must not exceed 1,000,000 bytes.');
                }
            }],
            'featured_image_file' => ['nullable', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'status' => ['required', 'in:draft,review,scheduled,published,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'is_trending' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]) + [
            'is_featured' => $request->boolean('is_featured'),
            'is_trending' => $request->boolean('is_trending'),
        ];

        $data['body'] = $this->sanitizeBody($data['body']);

        return $data;
    }

    private function sanitizeBody(string $body): string
    {
        return HtmlSanitizer::clean($body);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (Article::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function readingTime(string $body): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($body)) / 220));
    }
}
