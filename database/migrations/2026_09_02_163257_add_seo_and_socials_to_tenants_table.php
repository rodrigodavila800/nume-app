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
        Schema::table('tenants', function (Blueprint $table) {
            // Redes Sociais da Produtora
            $table->string('social_instagram')->nullable()->after('description');
            $table->string('social_youtube')->nullable()->after('social_instagram');
            $table->string('social_linkedin')->nullable()->after('social_youtube');
            $table->string('social_tiktok')->nullable()->after('social_linkedin');
            $table->string('social_whatsapp')->nullable()->after('social_tiktok');
            $table->string('social_facebook')->nullable()->after('social_whatsapp');

            // SEO da Plataforma (gerado pela Sofi ou editado manualmente)
            $table->string('seo_meta_title', 70)->nullable()->after('social_facebook');
            $table->string('seo_meta_description', 160)->nullable()->after('seo_meta_title');
            $table->string('seo_og_image')->nullable()->after('seo_meta_description');
            $table->string('seo_og_description', 200)->nullable()->after('seo_og_image');
            $table->text('seo_schema_keywords')->nullable()->after('seo_og_description');
            $table->string('seo_canonical_url')->nullable()->after('seo_schema_keywords');
            $table->timestamp('seo_last_generated_at')->nullable()->after('seo_canonical_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'social_instagram', 'social_youtube', 'social_linkedin',
                'social_tiktok', 'social_whatsapp', 'social_facebook',
                'seo_meta_title', 'seo_meta_description', 'seo_og_image',
                'seo_og_description', 'seo_schema_keywords', 'seo_canonical_url',
                'seo_last_generated_at',
            ]);
        });
    }
};
