<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Tag;
use App\Services\AuditSnapshot;
use App\Services\HtmlSanitizer;
use App\Services\PageBuilder\PageBuilder;
use App\Support\AdminPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    /** Maximum size of the builder layout JSON accepted on save (bytes). */
    public const MAX_LAYOUT_BYTES = 2_000_000;

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(Article::STATUSES)],
            'category' => ['nullable', 'integer'],
            'view' => ['nullable', 'in:all,trash'],
            'sort' => ['nullable', 'in:latest,oldest,title,views,updated'],
        ]);
        $inTrash = ($filters['view'] ?? 'all') === 'trash';
        $period = AdminPeriod::fromRequest($request);

        $base = $period->apply(Article::query())
            ->when($request->filled('q'), function ($q) use ($filters) {
                $term = trim($filters['q']);
                $q->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%"));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $filters['category']));

        $query = (clone $base)
            ->when($inTrash, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $filters['status']));

        $stats = [
            'Total Articles' => (clone $query)->count(),
            'Published' => (clone $query)->where('status', 'published')->count(),
            'Drafts' => (clone $query)->where('status', 'draft')->count(),
            'Total Views' => (clone $query)->sum('views_count'),
        ];
        $trashCount = (clone $base)->onlyTrashed()->count();

        $query = match ($filters['sort'] ?? 'latest') {
            'oldest' => $query->oldest(),
            'title' => $query->orderBy('title'),
            'views' => $query->orderByDesc('views_count'),
            'updated' => $query->latest('updated_at'),
            default => $query->latest(),
        };

        $articles = $query->with(['author', 'category'])
            ->select(['id', 'author_id', 'category_id', 'title', 'slug', 'status', 'featured_image', 'is_featured', 'is_trending', 'views_count', 'published_at', 'scheduled_for', 'created_at', 'updated_at', 'deleted_at', DB::raw('layout IS NOT NULL as built_with_builder')])
            ->paginate(20)
            ->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('admin.articles.index', compact('articles', 'categories', 'stats', 'period', 'inTrash', 'trashCount'));
    }

    public function create()
    {
        $article = new Article(['status' => 'draft', 'page_template' => 'default']);
        $article->layout = PageBuilder::starter();

        return $this->builder($article);
    }

    public function edit(Article $article)
    {
        $article->load('tags');
        if (! $article->usesBuilder()) {
            // Classic articles open in the builder as a single text section.
            $article->layout = PageBuilder::fromHtml($article->body);
        }

        return $this->builder($article);
    }

    private function builder(Article $article)
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $tags = Tag::orderBy('name')->get(['id', 'name']);

        return view('admin.articles.builder', [
            'article' => $article,
            'categories' => $categories,
            'tags' => $tags,
            'schema' => PageBuilder::schemaForEditor(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null ?: $data['title']);
        $data = $this->applyBody($data, new Article(['slug' => $data['slug'], 'title' => $data['title']]));

        if ($request->hasFile('featured_image_file')) {
            $data['featured_image'] = $request->file('featured_image_file')->store('articles', 'public');
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $article = DB::transaction(function () use ($data, $request) {
            $article = Article::create($data);
            $article->tags()->sync($this->tagIds($request, $data));
            $this->recordRevision($article, $request);

            return $article;
        });
        $request->attributes->set('audit_subject', $article);

        return $this->saved($request, $article, 'Article created.');
    }

    public function update(Request $request, Article $article)
    {
        $data = $this->validated($request, $article);
        if (array_key_exists('slug', $data)) {
            $data['slug'] = $data['slug'] && Str::slug($data['slug']) !== $article->slug
                ? $this->uniqueSlug($data['slug'], $article->id)
                : $article->slug;
        }
        $data = $this->applyBody($data, new Article(['slug' => $data['slug'] ?? $article->slug, 'title' => $data['title']]));

        if ($request->boolean('remove_featured_image') && $article->featured_image) {
            Storage::disk('public')->delete($article->featured_image);
            $data['featured_image'] = null;
        }

        if ($request->hasFile('featured_image_file')) {
            if ($article->featured_image) {
                Storage::disk('public')->delete($article->featured_image);
            }

            $data['featured_image'] = $request->file('featured_image_file')->store('articles', 'public');
        }

        if ($data['status'] === 'published' && ! $article->published_at && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        DB::transaction(function () use ($article, $data, $request) {
            $article->update($data);
            $article->tags()->sync($this->tagIds($request, $data));
            $this->recordRevision($article, $request);
        });

        return $this->saved($request, $article, 'Article updated.');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return back()->with('success', 'Article moved to trash.');
    }

    public function duplicate(Request $request, Article $article): RedirectResponse
    {
        $copy = $article->replicate(['views_count', 'published_at', 'slug']);
        $copy->title = Str::limit($article->title.' (Copy)', 255, '');
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->status = 'draft';
        $copy->author_id = $request->user()->id;
        $copy->views_count = 0;
        $copy->is_featured = false;
        if ($article->featured_image && Storage::disk('public')->exists($article->featured_image)) {
            $path = 'articles/'.Str::random(40).'.'.pathinfo($article->featured_image, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($article->featured_image, $path);
            $copy->featured_image = $path;
        }
        if ($copy->usesBuilder()) {
            $built = PageBuilder::build($copy->layout, PageBuilder::context($copy));
            $copy->body = $built['html'];
            $copy->layout_css = $built['css'];
        }
        $copy->save();
        $copy->tags()->sync($article->tags()->pluck('tags.id'));
        $this->audit($request, $copy, 'created', 'admin.articles.duplicate');

        return redirect()->route('admin.articles.edit', $copy)->with('success', 'Article duplicated as a draft.');
    }

    public function restore(Request $request, int $article): RedirectResponse
    {
        $model = Article::onlyTrashed()->findOrFail($article);
        $model->restore();
        $this->audit($request, $model, 'restored', 'admin.articles.restore');

        return back()->with('success', 'Article restored.');
    }

    public function forceDestroy(Request $request, int $article): RedirectResponse
    {
        $model = Article::onlyTrashed()->findOrFail($article);
        $this->audit($request, $model, 'deleted permanently', 'admin.articles.force-destroy');
        if ($model->featured_image) {
            Storage::disk('public')->delete($model->featured_image);
        }
        $model->forceDelete();

        return back()->with('success', 'Article permanently deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:publish,draft,review,archive,feature,unfeature,trash,restore,force_delete'],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $articles = Article::withTrashed()->whereIn('id', $data['ids'])->get();
        $count = 0;

        foreach ($articles as $article) {
            $trashed = $article->trashed();
            $changed = match ($data['action']) {
                'publish' => ! $trashed && $article->update(['status' => 'published', 'published_at' => $article->published_at ?? now()]),
                'draft' => ! $trashed && $article->update(['status' => 'draft']),
                'review' => ! $trashed && $article->update(['status' => 'review']),
                'archive' => ! $trashed && $article->update(['status' => 'archived']),
                'feature' => ! $trashed && $article->update(['is_featured' => true]),
                'unfeature' => ! $trashed && $article->update(['is_featured' => false]),
                'trash' => ! $trashed && $article->delete(),
                'restore' => $trashed && $article->restore(),
                'force_delete' => $trashed && $this->purge($article),
            };

            if ($changed) {
                $count++;
                $this->audit($request, $article, 'bulk '.$data['action'], 'admin.articles.bulk');
            }
        }

        return back()->with('success', trans_choice('{0} No articles changed.|{1} 1 article updated.|[2,*] :count articles updated.', $count, ['count' => $count]));
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'article_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer'],
            'page_template' => ['nullable', Rule::in(array_keys(Article::PAGE_TEMPLATES))],
            'layout' => ['required', 'string', 'max:'.self::MAX_LAYOUT_BYTES],
        ]);

        $existing = isset($data['article_id']) ? Article::find($data['article_id']) : null;
        $article = new Article([
            'title' => $data['title'] ?: 'Untitled article',
            'slug' => $existing?->slug ?? '',
            'excerpt' => $data['excerpt'] ?? null,
            'page_template' => $data['page_template'] ?? 'default',
            'featured_image' => $existing?->featured_image,
            'reading_time' => 1,
        ]);
        $built = PageBuilder::build($data['layout'], PageBuilder::context($article));
        $article->body = $built['html'];
        $article->layout = $built['layout'];
        $article->layout_css = $built['css'];
        $article->reading_time = $this->readingTime($built['html']);
        $article->published_at = $existing?->published_at ?? now();
        $article->setRelation('author', $existing?->author ?? $request->user());
        $article->setRelation('category', isset($data['category_id']) ? Category::find($data['category_id']) : null);
        $article->setRelation('tags', collect());

        return response()
            ->view('frontend.articles.show', ['article' => $article, 'related' => collect(), 'isPreview' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function revisions(Article $article): JsonResponse
    {
        return response()->json($article->revisions()->with('user:id,name')->limit(ArticleRevision::KEEP)
            ->get(['id', 'article_id', 'user_id', 'title', 'status', 'created_at'])
            ->map(fn (ArticleRevision $revision) => [
                'id' => $revision->id,
                'title' => $revision->title,
                'status' => $revision->status,
                'user' => $revision->user?->name ?? 'Unknown',
                'created_at' => $revision->created_at?->toIso8601String(),
                'created_human' => $revision->created_at?->diffForHumans(),
            ]));
    }

    public function revision(Article $article, ArticleRevision $revision): JsonResponse
    {
        abort_unless($revision->article_id === $article->id, 404);

        return response()->json([
            'id' => $revision->id,
            'title' => $revision->title,
            'page_template' => $revision->page_template,
            'layout' => is_array($revision->layout) ? $revision->layout : PageBuilder::fromHtml($revision->body),
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?Article $article = null): array
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required_without:layout', 'nullable', 'string', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > HtmlSanitizer::MAX_INPUT_LENGTH) {
                    $fail('The body must not exceed 1,000,000 bytes.');
                }
            }],
            'layout' => ['nullable', 'string', 'max:'.self::MAX_LAYOUT_BYTES, function ($attribute, $value, $fail) {
                if (is_string($value) && ! is_array(json_decode($value, true, 64))) {
                    $fail('The page layout is not valid.');
                }
            }],
            'page_template' => ['nullable', Rule::in(array_keys(Article::PAGE_TEMPLATES))],
            'featured_image_file' => ['nullable', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(Article::STATUSES)],
            'is_featured' => ['nullable', 'boolean'],
            'is_trending' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date', Rule::requiredIf($request->input('status') === 'scheduled')],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ]) + [
            'is_featured' => $request->boolean('is_featured'),
            'is_trending' => $request->boolean('is_trending'),
        ];

        unset($data['featured_image_file'], $data['remove_featured_image'], $data['new_tags']);
        if (! $request->has('slug')) {
            unset($data['slug']);
        }
        $data['page_template'] = $data['page_template'] ?? $article?->page_template ?? 'default';

        return $data;
    }

    /** Fill body/layout/layout_css from either the builder layout or a classic HTML body. */
    private function applyBody(array $data, Article $context): array
    {
        if (! empty($data['layout'])) {
            $built = PageBuilder::build($data['layout'], PageBuilder::context($context));
            $data['layout'] = $built['layout'];
            $data['layout_css'] = $built['css'];
            $data['body'] = $built['html'];
        } else {
            $data['body'] = HtmlSanitizer::clean($data['body'] ?? '');
            $data['layout'] = null;
            $data['layout_css'] = null;
        }

        $data['reading_time'] = $this->readingTime($data['body']);

        return $data;
    }

    /** Selected tag ids plus any comma-separated new tags (created on the fly). */
    private function tagIds(Request $request, array $data): array
    {
        $ids = array_map('intval', $data['tags'] ?? []);

        foreach (array_filter(array_map('trim', explode(',', (string) $request->input('new_tags')))) as $name) {
            $name = Str::limit($name, 60, '');
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $ids[] = Tag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }

    private function recordRevision(Article $article, Request $request): void
    {
        $latest = $article->revisions()->first();
        if ($latest && $latest->body === $article->body && $latest->title === $article->title && $latest->status === $article->status) {
            return;
        }

        $article->revisions()->create([
            'user_id' => $request->user()?->id,
            'title' => $article->title,
            'body' => $article->body,
            'layout' => $article->layout,
            'page_template' => $article->page_template ?? 'default',
            'status' => $article->status,
        ]);

        $keep = $article->revisions()->limit(ArticleRevision::KEEP)->pluck('id');
        ArticleRevision::where('article_id', $article->id)->whereNotIn('id', $keep)->delete();
    }

    private function saved(Request $request, Article $article, string $message)
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        $article->refresh()->load('tags:id,name');

        return response()->json([
            'message' => $message,
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'status' => $article->status,
                'page_template' => $article->page_template,
                'published_at' => $article->published_at?->format('Y-m-d\TH:i'),
                'featured_image_url' => $article->featured_image ? asset('storage/'.$article->featured_image) : null,
                'tags' => $article->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                'public_url' => route('articles.show', $article->slug),
                'edit_url' => route('admin.articles.edit', $article),
                'update_url' => route('admin.articles.update', $article),
                'revisions_url' => route('admin.articles.revisions', $article),
                'updated_human' => $article->updated_at?->format('H:i'),
            ],
        ]);
    }

    private function purge(Article $article): bool
    {
        if ($article->featured_image) {
            Storage::disk('public')->delete($article->featured_image);
        }

        return (bool) $article->forceDelete();
    }

    /** Audit actions whose route names the audit middleware doesn't recognise. */
    private function audit(Request $request, Article $article, string $action, string $route): void
    {
        $user = $request->user();
        AuditLog::create([
            'actor_id' => $user?->id,
            'actor_name' => $user?->name ?? 'System',
            'action' => $action,
            'subject_type' => 'Article',
            'subject_id' => (string) $article->getKey(),
            'subject_label' => Str::limit($article->title, 255, ''),
            'route' => $route,
            'ip_address' => $request->ip(),
            'changes' => app(AuditSnapshot::class)->changes([], ['status' => $article->status, 'deleted' => $article->trashed()]),
        ]);
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'article';
        $base = Str::limit($base, 180, '');
        $slug = $base;
        $i = 2;

        while (Article::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function readingTime(string $body): int
    {
        return max(1, (int) ceil(str_word_count(PageBuilder::plainText($body)) / 220));
    }
}
