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
        Schema::table('showcase_videos', function (Blueprint $table) {
            $table->boolean('is_vertical')->default(true)->after('thumbnail_url');
            $table->index(['tenant_id', 'is_vertical', 'sort_order']);
        });

        // Atualizar vídeos existentes: se contiver /shorts/ ou categoria vertical é vertical (true), se for watch?v= sem shorts é horizontal (false)
        foreach (\Illuminate\Support\Facades\DB::table('showcase_videos')->get() as $video) {
            $isVertical = true;
            $url = strtolower($video->video_url ?? '');
            if (!empty($url)) {
                if (str_contains($url, '/shorts/') || str_contains($url, '/reel/') || str_contains($url, 'tiktok.com')) {
                    $isVertical = true;
                } elseif (str_contains($url, 'watch?v=') || str_contains($url, 'vimeo.com')) {
                    $isVertical = false;
                }
            }
            \Illuminate\Support\Facades\DB::table('showcase_videos')->where('id', $video->id)->update([
                'is_vertical' => $isVertical,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('showcase_videos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_vertical', 'sort_order']);
            $table->dropColumn('is_vertical');
        });
    }
};
