<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Limites de publicação definidos pelo admin da NUME:
     * - quantas redes o cliente pode conectar (o cliente escolhe quais);
     * - quantos envios por mês (várias redes no mesmo envio = 1).
     * Vêm do plano/pacote (packs), do orçamento (copiados para pack_purchases) e o ajuste
     * manual na ficha do cliente prevalece. Vazio = sem limite.
     */
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->unsignedTinyInteger('social_networks_limit')->nullable()->after('video_quantity');
            $table->unsignedSmallInteger('monthly_post_limit')->nullable()->after('social_networks_limit');
        });

        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->unsignedTinyInteger('social_networks_limit')->nullable()->after('video_quantity');
            $table->unsignedSmallInteger('monthly_post_limit')->nullable()->after('social_networks_limit');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedTinyInteger('social_networks_limit_override')->nullable()->after('billing_updated_at');
            $table->unsignedSmallInteger('monthly_post_limit_override')->nullable()->after('social_networks_limit_override');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['social_networks_limit_override', 'monthly_post_limit_override']);
        });

        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropColumn(['social_networks_limit', 'monthly_post_limit']);
        });

        Schema::table('packs', function (Blueprint $table) {
            $table->dropColumn(['social_networks_limit', 'monthly_post_limit']);
        });
    }
};
