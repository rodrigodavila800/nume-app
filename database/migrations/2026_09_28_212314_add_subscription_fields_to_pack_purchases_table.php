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
            $table->string('stripe_subscription_id')->nullable()->after('stripe_checkout_session_id');
            $table->string('subscription_status', 30)->nullable()->after('status'); // 'active', 'past_due', 'canceled', 'completed'
            $table->string('subscription_interval', 20)->default('month')->after('subscription_status'); // 'month', 'quarter'
            $table->unsignedSmallInteger('commitment_months')->default(1)->after('subscription_interval'); // 1, 3, 6, 12
            $table->unsignedSmallInteger('subscription_cycle_current')->default(1)->after('commitment_months');
            $table->unsignedSmallInteger('subscription_cycles_total')->default(1)->after('subscription_cycle_current');
            $table->timestamp('subscription_renews_at')->nullable()->after('subscription_cycles_total');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_renews_at');

            $table->index(['subscription_status', 'stripe_subscription_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['subscription_status', 'stripe_subscription_id']);
            $table->dropColumn([
                'stripe_subscription_id',
                'subscription_status',
                'subscription_interval',
                'commitment_months',
                'subscription_cycle_current',
                'subscription_cycles_total',
                'subscription_renews_at',
                'subscription_ends_at',
            ]);
        });
    }
};
