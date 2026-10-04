<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'created_by', 'title', 'slug', 'summary', 'description', 'venue',
        'registration_url', 'starts_at', 'ends_at', 'status', 'featured_image',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}
