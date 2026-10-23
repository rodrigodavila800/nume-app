<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // Sofi SEO Pack — sugestões geradas automaticamente ao publicar o vídeo
            $table->string('sofi_seo_title')->nullable()->after('description');
            $table->text('sofi_seo_description')->nullable()->after('sofi_seo_title');
            $table->json('sofi_hashtags')->nullable()->after('sofi_seo_description');
            $table->text('sofi_seo_keywords')->nullable()->after('sofi_hashtags');
            $table->timestamp('sofi_seo_generated_at')->nullable()->after('sofi_seo_keywords');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'sofi_seo_title',
                'sofi_seo_description',
                'sofi_hashtags',
                'sofi_seo_keywords',
                'sofi_seo_generated_at',
            ]);
        });
    }
};
