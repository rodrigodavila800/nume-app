<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dados de faturação do cliente, informados ao aprovar um orçamento (ou no portal).
     * Nome fiscal, NIF e endereço ficam criptografados (cast "encrypted": colunas text).
     * A compra recebe uma cópia em claro, porque a fatura emitida é documento fiscal.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('billing_type', 30)->nullable()->after('anamnesis_completed_at');
            $table->text('billing_legal_name')->nullable()->after('billing_type');
            $table->text('billing_tax_id')->nullable()->after('billing_legal_name');
            $table->text('billing_address')->nullable()->after('billing_tax_id');
            $table->text('billing_postal_code')->nullable()->after('billing_address');
            $table->text('billing_city')->nullable()->after('billing_postal_code');
            $table->string('billing_country', 2)->nullable()->after('billing_city');
            $table->timestamp('billing_updated_at')->nullable()->after('billing_country');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'billing_type', 'billing_legal_name', 'billing_tax_id', 'billing_address',
                'billing_postal_code', 'billing_city', 'billing_country', 'billing_updated_at',
            ]);
        });
    }
};
