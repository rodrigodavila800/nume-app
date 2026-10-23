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
            $table->string('billing_mode')->default('full_upfront')->after('billing_type');
            $table->unsignedSmallInteger('deposit_percentage')->default(50)->after('billing_mode');
            $table->decimal('pilot_discount_on_subscription', 10, 2)->default(0.00)->after('deposit_percentage');
        });

        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->string('billing_mode')->default('full_upfront')->after('billing_type');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('billing_mode');
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_amount');
            $table->decimal('balance_amount', 10, 2)->nullable()->after('deposit_paid_at');
            $table->timestamp('balance_paid_at')->nullable()->after('balance_amount');
            $table->string('balance_status')->default('not_applicable')->after('balance_paid_at');
            $table->string('stripe_payment_method_id')->nullable()->after('stripe_subscription_id');
            $table->string('stripe_balance_payment_intent_id')->nullable()->after('stripe_payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'billing_mode',
                'deposit_amount',
                'deposit_paid_at',
                'balance_amount',
                'balance_paid_at',
                'balance_status',
                'stripe_payment_method_id',
                'stripe_balance_payment_intent_id',
            ]);
        });

        Schema::table('packs', function (Blueprint $table) {
            $table->dropColumn([
                'billing_mode',
                'deposit_percentage',
                'pilot_discount_on_subscription',
            ]);
        });
    }
};
