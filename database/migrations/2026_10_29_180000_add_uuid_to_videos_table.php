<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->char('uuid', 36)->nullable()->after('id');
        });

        // Preenche UUID para todos os vídeos já existentes
        $videos = DB::table('videos')->whereNull('uuid')->pluck('id');
        foreach ($videos as $id) {
            DB::table('videos')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};

