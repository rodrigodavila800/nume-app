<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vídeo hospedado na NUME: o arquivo enviado fica em disco privado e pode coexistir
     * com (ou substituir) o link externo. Vídeos só com link continuam como antes.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('video_url')->nullable()->change();
            $table->string('source_disk', 30)->nullable()->after('thumbnail_url');
            $table->string('source_path')->nullable()->after('source_disk');
            $table->string('source_mime', 50)->nullable()->after('source_path');
            $table->unsignedBigInteger('source_size')->nullable()->after('source_mime');
            $table->unsignedSmallInteger('source_width')->nullable()->after('source_size');
            $table->unsignedSmallInteger('source_height')->nullable()->after('source_width');
            $table->char('source_sha256', 64)->nullable()->after('source_height');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'source_disk', 'source_path', 'source_mime', 'source_size',
                'source_width', 'source_height', 'source_sha256',
            ]);
        });

        // video_url volta a ser NOT NULL: vídeos só com arquivo ficam com string vazia.
        DB::table('videos')->whereNull('video_url')->update(['video_url' => '']);

        Schema::table('videos', function (Blueprint $table) {
            $table->string('video_url')->nullable(false)->change();
        });
    }
};
