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
        Schema::table('pack_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('pack_purchases', 'bank_transfer_reference')) {
                $table->string('bank_transfer_reference', 64)
                    ->nullable()
                    ->after('gateway_order_reference');
                $table->index(['tenant_id', 'bank_transfer_reference']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('pack_purchases', 'bank_transfer_reference')) {
                $table->dropIndex(['tenant_id', 'bank_transfer_reference']);
                $table->dropColumn('bank_transfer_reference');
            }
        });
    }
};
