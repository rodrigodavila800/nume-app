<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uso de IA por cliente do portal (limite mensal por cliente, além do da produtora).
     */
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('tenant_id')->constrained('clientes')->nullOnDelete();
            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'created_at']);
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
