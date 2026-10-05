<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'bio',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        if ($this->role === $slug) {
            return true;
        }

        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return Role::query()
            ->where(fn ($query) => $query->where('slug', $this->role)
                ->orWhereHas('users', fn ($users) => $users->whereKey($this->id)))
            ->whereHas('permissions', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }

    public function isAdmin(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->hasRole('super_admin') || Role::query()
            ->where(fn ($query) => $query->where('slug', $this->role)
                ->orWhereHas('users', fn ($users) => $users->whereKey($this->id)))
            ->whereHas('permissions')
            ->exists();
    }
}
