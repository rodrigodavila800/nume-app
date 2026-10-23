<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('packs')
            ->where('billing_type', 'recurring')
            ->update(['billing_type' => 'subscription']);

        DB::table('packs')
            ->where('billing_type', 'one_off')
            ->update(['billing_type' => 'one_time']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('packs')
            ->where('billing_type', 'subscription')
            ->update(['billing_type' => 'recurring']);

        DB::table('packs')
            ->where('billing_type', 'one_time')
            ->update(['billing_type' => 'one_off']);
    }
};
