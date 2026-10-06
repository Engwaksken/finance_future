<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\PageSlide;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditSnapshot
{
    public function model(Model $model): array
    {
        $fields = match (true) {
            $model instanceof Article => ['title', 'slug', 'author_id', 'category_id', 'status', 'is_featured', 'is_trending', 'published_at', 'scheduled_for', 'featured_image', 'body', 'excerpt', 'seo_title', 'seo_description'],
            $model instanceof User => ['name', 'email', 'role', 'status', 'password'],
            $model instanceof Category => ['name', 'slug', 'description', 'display_type', 'icon', 'featured_image', 'display_number', 'sort_order', 'is_active', 'is_featured'],
            $model instanceof Role => ['name', 'slug', 'description'],
            $model instanceof PageSlide => ['page', 'category_id', 'title', 'description', 'background_type', 'media_path', 'background_color', 'text_color', 'button_label', 'button_url', 'sort_order', 'is_active'],
            default => [],
        };
        $snapshot = array_intersect_key($model->getAttributes(), array_flip($fields));
        if ($model instanceof User) {
            $snapshot['role_ids'] = $model->roles()->orderBy('roles.id')->pluck('roles.id')->all();
        } elseif ($model instanceof Role) {
            $snapshot['permission_ids'] = $model->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        } elseif ($model instanceof Article) {
            $snapshot['tag_ids'] = $model->tags()->orderBy('tags.id')->pluck('tags.id')->all();
        }

        return $snapshot;
    }

    public function settings(string $section): array
    {
        $keys = match ($section) {
            'general' => ['site_name', 'site_tagline', 'site_email'],
            'branding' => ['site_logo', 'site_favicon'],
            'backup' => ['backup_enabled', 'backup_retention_days'],
            'maintenance' => ['maintenance_enabled', 'maintenance_message'],
            default => [],
        };

        return SiteSetting::whereIn('key', $keys)->pluck('value', 'key')->all();
    }

    public function changes(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($old === $new) {
                continue;
            }
            // Record that credentials/content changed without retaining their values.
            if (in_array($field, ['password', 'body'], true)) {
                $changes[$field] = ['redacted' => true];
            } else {
                $changes[$field] = [
                    'before' => is_string($old) ? Str::limit($old, 500) : $old,
                    'after' => is_string($new) ? Str::limit($new, 500) : $new,
                ];
            }
        }

        return $changes;
    }
}
