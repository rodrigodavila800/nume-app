<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            // Fiscal & Tax Breakdown (IVA 21%, IRPF)
            $table->decimal('subtotal', 10, 2)->nullable()->after('amount');
            $table->decimal('tax_rate', 5, 2)->default(21.00)->after('subtotal');
            $table->decimal('tax_amount', 10, 2)->default(0.00)->after('tax_rate');
            $table->decimal('irpf_rate', 5, 2)->default(0.00)->after('tax_amount');
            $table->decimal('irpf_amount', 10, 2)->default(0.00)->after('irpf_rate');
            $table->decimal('total_amount', 10, 2)->nullable()->after('irpf_amount');

            // Billing / Legal Invoice Data
            $table->string('billing_type', 30)->default('particular')->after('total_amount');
            $table->string('billing_tax_id', 50)->nullable()->after('billing_type');
            $table->string('billing_legal_name', 255)->nullable()->after('billing_tax_id');
            $table->string('billing_address', 255)->nullable()->after('billing_legal_name');
            $table->string('billing_postal_code', 20)->nullable()->after('billing_address');
            $table->string('billing_city', 100)->nullable()->after('billing_postal_code');
            $table->string('billing_country', 2)->default('ES')->after('billing_city');

            // Multi-gateway & Redsys / Bizum fields
            $table->string('payment_gateway', 30)->default('stripe')->after('stripe_payment_intent_id');
            $table->string('gateway_order_reference', 50)->nullable()->after('payment_gateway');
            $table->json('gateway_metadata')->nullable()->after('gateway_order_reference');

            $table->index(['payment_gateway', 'gateway_order_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['payment_gateway', 'gateway_order_reference']);
            $table->dropColumn([
                'subtotal',
                'tax_rate',
                'tax_amount',
                'irpf_rate',
                'irpf_amount',
                'total_amount',
                'billing_type',
                'billing_tax_id',
                'billing_legal_name',
                'billing_address',
                'billing_postal_code',
                'billing_city',
                'billing_country',
                'payment_gateway',
                'gateway_order_reference',
                'gateway_metadata',
            ]);
        });
    }
};
