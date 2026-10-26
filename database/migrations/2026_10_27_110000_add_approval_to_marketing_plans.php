<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plano de marketing passa a nascer como rascunho e só vale depois de aprovado pelo cliente.
     * Cada item do plano pode indicar o tipo de vídeo.
     */
    public function up(): void
    {
        Schema::table('marketing_plans', function (Blueprint $table) {
            $table->json('anamnesis_snapshot')->nullable()->after('metrics_summary');
            $table->text('adjustment_request')->nullable()->after('anamnesis_snapshot');
            $table->timestamp('approved_at')->nullable()->after('adjustment_request');
            $table->foreignId('approved_by_cliente_id')->nullable()->after('approved_at')->constrained('clientes')->nullOnDelete();
        });

        Schema::table('marketing_plan_items', function (Blueprint $table) {
            $table->foreignId('video_type_id')->nullable()->after('production_id')->constrained('video_types')->nullOnDelete();
        });

        // Planos antigos já estavam em uso (itens viraram produção): ficam como aprovados.
        DB::table('marketing_plans')->where('status', 'active')->update([
            'status' => 'approved',
            'approved_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        DB::table('marketing_plans')->whereIn('status', ['approved', 'draft', 'adjustment_requested'])->update(['status' => 'active']);

        Schema::table('marketing_plan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_type_id');
        });

        Schema::table('marketing_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_cliente_id');
            $table->dropColumn(['anamnesis_snapshot', 'adjustment_request', 'approved_at']);
        });
    }
};
