<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de vídeo cadastrados pelo admin da produtora (vendas, posicionamento, institucional...).
     * Cada tipo define a estrutura do roteiro em blocos (nome, início, fim, função) e a duração.
     */
    public function up(): void
    {
        Schema::create('video_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->default(30);
            $table->json('blocks');
            $table->text('sofi_guidance')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_types');
    }
};
