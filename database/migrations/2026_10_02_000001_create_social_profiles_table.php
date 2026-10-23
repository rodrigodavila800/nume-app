<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_profiles', function (Blueprint $table) {
            $table->id();

            // Isolamento Multi-tenant Obrigatório
            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Vinculado ao Cliente final (guard cliente)
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete();

            // Identificador estável enviado ao Upload-Post como "username".
            // UUID interno para não vazar e-mail/documento do cliente a terceiros.
            $table->uuid('upload_post_username')->unique();

            // Status da conexão após validação server-to-server
            $table->boolean('connected')->default(false);

            // Snapshot das plataformas com conta conectada (instagram, youtube, tiktok, etc)
            $table->json('platforms_connected')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Um perfil social por cliente dentro de cada tenant
            $table->unique(['tenant_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_profiles');
    }
};
