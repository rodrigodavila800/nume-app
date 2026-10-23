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
        Schema::create('sofi_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('production_id')->nullable()->constrained('productions')->nullOnDelete();
            $table->string('title')->default('Atendimento Sofi Copilot');
            $table->string('status')->default('active'); // active, resolved, archived
            $table->timestamps();

            $table->index(['tenant_id', 'cliente_id']);
        });

        Schema::create('sofi_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sofi_conversation_id')->constrained('sofi_conversations')->cascadeOnDelete();
            $table->enum('sender', ['user', 'sofi'])->default('user');
            $table->text('content');
            $table->json('metadata')->nullable(); // Sugestões de roteiro, CTAs, ações rápidas
            $table->timestamps();

            $table->index(['sofi_conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sofi_messages');
        Schema::dropIfExists('sofi_conversations');
    }
};
