<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_preserves_existing_user_privileges_and_status(): void
    {
        $user = User::factory()->create(['role' => 'author', 'status' => 'inactive']);

        $this->seed(RolesPermissionsSeeder::class);

        $this->assertSame('author', $user->fresh()->role);
        $this->assertSame('inactive', $user->fresh()->status);
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_editor_can_manage_content_but_cannot_manage_system_resources(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $user = User::factory()->create(['role' => 'editor', 'status' => 'active']);
        $this->actingAs($user)->get(route('admin.articles.index'))->assertOk()
            ->assertDontSee(route('admin.users.index'));

        foreach (['users.index', 'roles.index', 'settings.index', 'settings.backup'] as $route) {
            $this->get(route('admin.'.$route))->assertForbidden();
        }

        $this->post(route('admin.users.store'), [])->assertForbidden();
        $this->post(route('admin.roles.store'), [])->assertForbidden();
        $this->put(route('admin.settings.update'), [])->assertForbidden();
    }

    public function test_custom_role_permissions_grant_cms_access(): void
    {
        $permission = Permission::create(['name' => 'Manage Articles', 'slug' => 'articles.manage', 'group' => 'content']);
        $role = Role::create(['name' => 'Content Specialist', 'slug' => 'content_specialist']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role' => 'reader', 'status' => 'active']);
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('admin.articles.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_inactive_super_admin_cannot_access_cms_or_bypass_maintenance(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'inactive']);
        SiteSetting::put('maintenance_enabled', true);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->get('/')->assertStatus(503);
        $this->get('/login')->assertRedirect();
    }

    public function test_maintenance_blocks_public_requests_but_allows_admin_recovery(): void
    {
        SiteSetting::put('maintenance_enabled', true);
        SiteSetting::put('maintenance_message', 'Scheduled CMS update');
        $this->get('/')->assertStatus(503)->assertSee('Scheduled CMS update');
        $this->get('/login')->assertOk();

        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($user)->get(route('admin.settings.index'))->assertOk();
    }
}
