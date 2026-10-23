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
        Schema::table('roteiros', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('submitted_at');
            $table->unsignedInteger('reminder_count')->default(0)->after('last_reminded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roteiros', function (Blueprint $table) {
            $table->dropColumn(['last_reminded_at', 'reminder_count']);
        });
    }
};
