<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action', 20)->index();
            $table->string('subject_type', 60)->index();
            $table->string('subject_id', 60)->nullable();
            $table->string('subject_label');
            $table->string('route', 150);
            $table->string('ip_address', 45)->nullable();
            $table->json('changes');
            $table->timestamp('created_at')->index();
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->index('subscribed_at');
        });

        // Upgrade existing installations; fresh installations receive permissions from the seeder.
        $roleIds = DB::table('roles')->whereIn('slug', ['super_admin', 'admin'])->pluck('id');
        if ($roleIds->isEmpty()) {
            return;
        }

        foreach ([
            'audit.view' => ['View Audit Logs', 'security'],
            'subscribers.view' => ['View Subscribers', 'content'],
        ] as $slug => [$name, $group]) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug, 'name' => $name, 'group' => $group, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropIndex(['subscribed_at']);
        });
        DB::table('permissions')->whereIn('slug', ['audit.view', 'subscribers.view'])->delete();
    }
};
