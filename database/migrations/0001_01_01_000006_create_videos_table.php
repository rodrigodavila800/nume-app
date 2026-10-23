<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url');
            $table->string('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->enum('status', ['processing', 'ready', 'published', 'archived'])->default('processing');
            $table->string('platform')->nullable(); // youtube, tiktok, instagram, etc
            $table->string('platform_video_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['platform', 'platform_video_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
