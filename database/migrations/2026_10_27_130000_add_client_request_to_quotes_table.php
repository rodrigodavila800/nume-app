<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orçamento pedido pelo cliente no portal (a partir do plano aprovado) e tipo de orçamento
     * (produção de vídeos ou serviço de anúncios).
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('kind', 20)->default('production')->after('status')->index();
            $table->foreignId('marketing_plan_id')->nullable()->after('production_id')->constrained('marketing_plans')->nullOnDelete();
            $table->json('request_details')->nullable()->after('request_text');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketing_plan_id');
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'request_details']);
        });
    }
};
