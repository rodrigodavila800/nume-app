<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->string('invoice_number', 50)->nullable()->unique()->after('billing_country');
            $table->string('invoice_series', 30)->default('NUM-2026-')->after('invoice_number');
            $table->timestamp('invoice_generated_at')->nullable()->after('invoice_series');
            $table->string('invoice_path', 255)->nullable()->after('invoice_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number',
                'invoice_series',
                'invoice_generated_at',
                'invoice_path',
            ]);
        });
    }
};
