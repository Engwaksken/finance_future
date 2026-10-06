<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('display_type', 20)->default('number');
            $table->string('featured_image')->nullable();
            $table->unsignedInteger('display_number')->nullable();
        });

        Schema::create('page_slides', function (Blueprint $table) {
            $table->id();
            $table->string('page', 60)->index();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('background_type', 20)->default('color');
            $table->string('media_path')->nullable();
            $table->string('background_color', 7)->default('#0b3b60');
            $table->string('text_color', 7)->default('#ffffff');
            $table->string('button_label', 80)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_slides');
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['display_type', 'featured_image', 'display_number']);
        });
    }
};
