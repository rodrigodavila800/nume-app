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
        Schema::table('pack_purchases', function (Blueprint $table) {
            // Dropa o índice não-único anterior caso exista
            try {
                $table->dropIndex('pack_purchases_tenant_id_bank_transfer_reference_index');
            } catch (Throwable $e) {
                // Em SQLite em memória ou se o índice não existir, segue em frente
            }

            // Adiciona índice UNIQUE composto por tenant_id e bank_transfer_reference
            $table->unique(
                ['tenant_id', 'bank_transfer_reference'],
                'pack_purchases_tenant_bank_transfer_ref_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            try {
                $table->dropUnique('pack_purchases_tenant_bank_transfer_ref_unique');
            } catch (Throwable $e) {
                // Ignora se não existir
            }

            $table->index(
                ['tenant_id', 'bank_transfer_reference'],
                'pack_purchases_tenant_id_bank_transfer_reference_index'
            );
        });
    }
};
