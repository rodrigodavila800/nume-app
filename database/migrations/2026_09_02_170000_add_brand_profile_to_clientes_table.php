<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('website')->nullable()->after('company_name');
            $table->string('instagram_handle')->nullable()->after('website');
            $table->string('linkedin_url')->nullable()->after('instagram_handle');
            $table->string('youtube_channel')->nullable()->after('linkedin_url');
            $table->string('tiktok_handle')->nullable()->after('youtube_channel');
            $table->string('brand_niche')->nullable()->after('tiktok_handle');
            $table->text('target_audience')->nullable()->after('brand_niche');
            $table->text('key_differentials')->nullable()->after('target_audience');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'website',
                'instagram_handle',
                'linkedin_url',
                'youtube_channel',
                'tiktok_handle',
                'brand_niche',
                'target_audience',
                'key_differentials',
            ]);
        });
    }
};
