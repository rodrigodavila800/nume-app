<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fluxo pelo Drive: link de revisão (o cliente assiste antes de aprovar) e link de entrega
     * (cópia final que fica com o cliente). source_purged_at marca o arquivo apagado da
     * hospedagem depois de postado.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('review_url', 2048)->nullable()->after('video_url');
            $table->string('delivery_url', 2048)->nullable()->after('review_url');
            $table->timestamp('source_purged_at')->nullable()->after('source_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['review_url', 'delivery_url', 'source_purged_at']);
        });
    }
};
