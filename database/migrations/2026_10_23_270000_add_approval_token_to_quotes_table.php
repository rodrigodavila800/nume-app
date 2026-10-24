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
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('approval_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('approval_token_expires_at')->nullable()->after('approval_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique(['approval_token']);
            $table->dropColumn(['approval_token', 'approval_token_expires_at']);
        });
    }
};
