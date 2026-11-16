<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderTemplate extends Model
{
    public const TYPES = ['page', 'section'];

    protected $fillable = ['user_id', 'name', 'type', 'layout'];

    protected $casts = ['layout' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
