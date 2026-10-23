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
        Schema::table('video_publications', function (Blueprint $table) {
            $table->string('status', 32)->default('pendente')->change();
            $table->timestamp('scheduled_at')->nullable()->after('plataformas');
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_publications', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
    }
};
