<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Montador de pacote: preço por vídeo de cada tipo (sem preço o tipo não aparece para o cliente) e
 * adicionais que o cliente pode acrescentar sozinho (os demais seguem só nos pacotes prontos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_types', function (Blueprint $table) {
            $table->decimal('builder_price', 10, 2)->nullable()->after('edit_hours_per_video');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->boolean('in_builder')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('video_types', function (Blueprint $table) {
            $table->dropColumn('builder_price');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('in_builder');
        });
    }
};
