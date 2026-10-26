<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A produção e o roteiro guardam o tipo de vídeo. O roteiro guarda também uma cópia da estrutura
     * usada na geração: editar o tipo depois não muda roteiros já escritos.
     */
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->foreignId('video_type_id')->nullable()->after('description')
                ->constrained('video_types')->nullOnDelete();
        });

        Schema::table('roteiros', function (Blueprint $table) {
            $table->foreignId('video_type_id')->nullable()->after('production_id')
                ->constrained('video_types')->nullOnDelete();
            $table->json('structure')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('roteiros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_type_id');
            $table->dropColumn('structure');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_type_id');
        });
    }
};
