<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcase_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('category')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_videos');
    }
};
