<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'role' => 'super_admin',
                    'status' => 'active',
                ]
            );
        }

        $this->call([
            FinanceFutureSeeder::class,
            RolesPermissionsSeeder::class,
        ]);
    }
}
