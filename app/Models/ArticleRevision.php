<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleRevision extends Model
{
    public const KEEP = 30;

    protected $fillable = ['article_id', 'user_id', 'title', 'body', 'layout', 'page_template', 'status'];

    protected $casts = ['layout' => 'array'];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
