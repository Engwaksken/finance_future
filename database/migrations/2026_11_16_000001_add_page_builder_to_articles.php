<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->longText('layout')->nullable()->after('body');
            $table->longText('layout_css')->nullable()->after('layout');
            $table->string('page_template', 20)->default('default')->after('layout_css');
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->longText('layout')->nullable();
            $table->string('page_template', 20)->default('default');
            $table->string('status', 30)->nullable();
            $table->timestamps();
            $table->index(['article_id', 'created_at']);
        });

        Schema::create('builder_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('page');
            $table->longText('layout');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_templates');
        Schema::dropIfExists('article_revisions');
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['layout', 'layout_css', 'page_template']);
        });
    }
};
