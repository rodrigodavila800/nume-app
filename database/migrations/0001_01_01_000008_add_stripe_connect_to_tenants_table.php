<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Conta Stripe Connect (Express) da produtora, usada para receber pagamentos de clientes
            $table->string('stripe_account_id')->nullable()->unique()->after('subscription_expires_at');
            $table->string('stripe_account_status')->default('not_connected')->after('stripe_account_id');
            $table->boolean('charges_enabled')->default(false)->after('stripe_account_status');
            $table->boolean('payouts_enabled')->default(false)->after('charges_enabled');
            $table->boolean('details_submitted')->default(false)->after('payouts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'stripe_account_status',
                'charges_enabled',
                'payouts_enabled',
                'details_submitted',
            ]);
        });
    }
};
