<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            'dashboard.view' => ['View Dashboard', 'dashboard'],
            'articles.manage' => ['Manage Articles', 'content'],
            'categories.manage' => ['Manage Categories', 'content'],
            'users.manage' => ['Manage Users', 'security'],
            'roles.manage' => ['Manage Roles & Permissions', 'security'],
            'settings.manage' => ['Manage Site Settings', 'settings'],
            'backups.manage' => ['Manage Backups', 'settings'],
        ];

        foreach ($items as $slug => [$name, $group]) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'group' => $group]
            );
        }

        $super = Role::updateOrCreate(
            ['slug' => 'super_admin'],
            ['name' => 'Super Admin', 'description' => 'Full system access', 'is_system' => true]
        );

        $admin = Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'CMS administration access', 'is_system' => true]
        );

        $editor = Role::updateOrCreate(
            ['slug' => 'editor'],
            ['name' => 'Editor', 'description' => 'Content publishing access', 'is_system' => true]
        );

        $author = Role::updateOrCreate(
            ['slug' => 'author'],
            ['name' => 'Author', 'description' => 'Content author access', 'is_system' => true]
        );

        $all = Permission::pluck('id');
        $super->permissions()->sync($all);

        $admin->permissions()->sync(Permission::whereIn('slug', [
            'dashboard.view', 'articles.manage', 'categories.manage',
            'users.manage', 'roles.manage', 'settings.manage', 'backups.manage',
        ])->pluck('id'));

        $editor->permissions()->sync(Permission::whereIn('slug', [
            'dashboard.view', 'articles.manage', 'categories.manage',
        ])->pluck('id'));

        $author->permissions()->sync(Permission::whereIn('slug', [
            'dashboard.view', 'articles.manage',
        ])->pluck('id'));

        $first = User::first();
        if ($first) {
            $first->roles()->syncWithoutDetaching([$super->id]);
            $first->role = 'super_admin';
            $first->status = 'active';
            $first->save();
        }
    }
}
