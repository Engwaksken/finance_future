<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'author_id', 'category_id', 'title', 'slug', 'excerpt', 'body',
        'layout', 'layout_css', 'page_template',
        'featured_image', 'status', 'is_featured', 'is_trending', 'reading_time',
        'published_at', 'scheduled_for', 'seo_title', 'seo_description',
        'social_image', 'views_count',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'layout' => 'array',
    ];

    public const STATUSES = ['draft', 'review', 'scheduled', 'published', 'archived'];

    public const PAGE_TEMPLATES = [
        'default' => 'Article (title, meta and featured image)',
        'wide' => 'Wide article (1180px content)',
        'canvas' => 'Canvas (builder content only, full width)',
    ];

    /** Whether the body was built with the visual builder. */
    public function usesBuilder(): bool
    {
        return is_array($this->layout);
    }

    public function revisions()
    {
        return $this->hasMany(ArticleRevision::class)->latest('id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
