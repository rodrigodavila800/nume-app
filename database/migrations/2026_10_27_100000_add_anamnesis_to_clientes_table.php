<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anamnese do cliente: respostas usadas pela Sofi para montar o plano de marketing.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->json('anamnesis')->nullable()->after('brand_study_generated_at');
            $table->timestamp('anamnesis_completed_at')->nullable()->after('anamnesis');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['anamnesis', 'anamnesis_completed_at']);
        });
    }
};
