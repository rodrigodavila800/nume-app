<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roteiros', function (Blueprint $table) {
            // Roteiro pode ser aprovado/rejeitado pela produtora (approved_by) OU pelo cliente final
            $table->foreignId('approved_by_cliente_id')->nullable()->after('approved_by')
                ->constrained('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roteiros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_cliente_id');
        });
    }
};
