<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pack_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('application_fee_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->unsignedInteger('video_quantity');
            $table->unsignedInteger('videos_used')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_purchases');
    }
};
