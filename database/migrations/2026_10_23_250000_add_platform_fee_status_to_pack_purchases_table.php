<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->string('platform_fee_status', 30)
                ->default('not_applicable')
                ->after('application_fee_amount');
            $table->index('platform_fee_status');
        });

        // Backfill 1: compras já pagas via Stripe Connect viram 'collected_at_gateway'
        DB::table('pack_purchases')
            ->where('status', 'paid')
            ->where('payment_gateway', 'stripe')
            ->update(['platform_fee_status' => 'collected_at_gateway']);

        // Backfill 2: compras já pagas via Redsys/Bizum de outras produtoras viram 'pending_billing' se houver fee
        $numeTenantId = DB::table('tenants')->where('slug', 'nume')->value('id');
        $redsysPurchases = DB::table('pack_purchases')
            ->where('status', 'paid')
            ->whereIn('payment_gateway', ['redsys', 'redsys_bizum', 'redsys_card', 'bizum'])
            ->where('application_fee_amount', '>', 0);

        if ($numeTenantId) {
            $redsysPurchases->where('tenant_id', '!=', $numeTenantId);
        }

        $redsysPurchases->update(['platform_fee_status' => 'pending_billing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['platform_fee_status']);
            $table->dropColumn('platform_fee_status');
        });
    }
};
