<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_bootstrap_is_repeatable_and_preserves_existing_credentials_and_settings(): void
    {
        $this->seed();

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasPermission('settings.manage'));
        $this->assertTrue(Hash::check('password', $user->password));

        $user->update(['password' => 'changed-password']);
        SiteSetting::put('site_name', 'Custom Site');

        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('tags', 9);
        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 9);
        $this->assertDatabaseCount('role_user', 1);
        $this->assertDatabaseCount('site_settings', 7);
        $this->assertTrue(Hash::check('changed-password', $user->fresh()->password));
        $this->assertDatabaseHas('site_settings', ['key' => 'site_name', 'value' => 'Custom Site']);
    }
}
