<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horas de edição por vídeo do tipo: entrada da calculadora quando o cliente pede orçamento.
     * Vazio = padrão da produtora (quote_edit_hours_per_video).
     */
    public function up(): void
    {
        Schema::table('video_types', function (Blueprint $table) {
            $table->decimal('edit_hours_per_video', 5, 2)->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('video_types', function (Blueprint $table) {
            $table->dropColumn('edit_hours_per_video');
        });
    }
};
