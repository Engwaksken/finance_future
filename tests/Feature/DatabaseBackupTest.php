<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_round_trip_preserves_data_and_excludes_runtime_tables(): void
    {
        $user = User::factory()->create(['name' => "O'Reilly\nFinance", 'bio' => null]);
        DB::table('cache_locks')->insert(['key' => 'backup-lock', 'owner' => 'test', 'expiration' => 123]);
        $paths = [];

        try {
            $backup = new DatabaseBackupService;
            $this->freezeTime();
            $paths[] = $backup->create();
            $paths[] = $backup->create();
            $this->assertNotSame($paths[0], $paths[1]);
            $sql = File::get($paths[0]);
            foreach (['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
                $this->assertStringNotContainsString('-- Table: '.$table."\n", $sql);
            }

            DB::table('users')->delete();
            DB::unprepared($sql);
            $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => $user->name, 'bio' => null]);
            $this->assertDatabaseCount('cache_locks', 1);
        } finally {
            File::delete($paths);
        }
    }
}
