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
        Schema::table('packs', function (Blueprint $table) {
            $table->foreignId('showcase_video_id')
                ->nullable()
                ->after('category_id')
                ->constrained('showcase_videos')
                ->nullOnDelete();

            $table->string('billing_type')->default('one_time')->after('status'); // 'one_time', 'subscription'
            $table->string('billing_interval')->default('month')->after('billing_type'); // 'month', 'quarter'
            $table->unsignedSmallInteger('commitment_months')->default(1)->after('billing_interval'); // 1, 3, 6, 12
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->dropForeign(['showcase_video_id']);
            $table->dropColumn(['showcase_video_id', 'billing_type', 'billing_interval', 'commitment_months']);
        });
    }
};
