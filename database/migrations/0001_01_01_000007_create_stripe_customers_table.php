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
        Schema::create('stripe_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete()->unique();
            $table->string('stripe_customer_id')->unique();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->enum('subscription_status', ['active', 'past_due', 'canceled', 'unpaid'])->nullable();
            $table->decimal('monthly_amount', 10, 2)->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id']);
            $table->index(['subscription_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_customers');
    }
};
