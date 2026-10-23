<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_publications', function (Blueprint $table) {
            $table->id();

            // Isolamento Multi-tenant Obrigatório
            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Cliente titular da publicação
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete();

            // Vídeo produzido na plataforma (opcional, caso venha de produção da Nume)
            $table->foreignId('video_id')
                ->nullable()
                ->constrained('videos')
                ->nullOnDelete();

            // Usuário do staff da produtora caso disparado por um operador interno
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // request_id devolvido pelo Upload-Post para consulta assíncrona
            $table->string('upload_post_request_id')->nullable()->index();

            $table->string('titulo', 255);
            $table->json('plataformas'); // ['instagram','youtube','tiktok',...]

            $table->enum('status', ['pendente', 'agendado', 'processando', 'publicado', 'falhou'])
                ->default('pendente');

            // Resposta crua da API para auditoria e conferência de logs/URLs de post
            $table->json('resultado')->nullable();

            $table->timestamp('publicado_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'cliente_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_publications');
    }
};
