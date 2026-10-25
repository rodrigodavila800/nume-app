<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DNA da Marca com as 8 redes do ecossistema (PlataformaAlvo) e detalhes públicos
     * das contas conectadas via Upload-Post (nunca tokens).
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('facebook_url')->nullable()->after('tiktok_handle');
            $table->string('x_handle', 100)->nullable()->after('facebook_url');
            $table->string('threads_handle', 100)->nullable()->after('x_handle');
            $table->string('pinterest_url')->nullable()->after('threads_handle');
        });

        Schema::table('social_profiles', function (Blueprint $table) {
            $table->json('account_details')->nullable()->after('platforms_connected');
        });
    }

    public function down(): void
    {
        Schema::table('social_profiles', function (Blueprint $table) {
            $table->dropColumn('account_details');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['facebook_url', 'x_handle', 'threads_handle', 'pinterest_url']);
        });
    }
};
