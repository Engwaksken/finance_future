<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSlide extends Model
{
    public const PAGES = [
        'home' => 'Home',
        'articles.index' => 'Articles',
        'articles.show' => 'Article detail pages',
        'categories.show' => 'Category pages',
        'events.index' => 'Events',
        'events.show' => 'Event detail pages',
        'about' => 'About',
        'contact' => 'Contact',
        'privacy' => 'Privacy',
        'search' => 'Search',
    ];

    protected $fillable = [
        'page', 'category_id', 'title', 'description', 'background_type', 'media_path',
        'background_color', 'text_color', 'button_label', 'button_url', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
