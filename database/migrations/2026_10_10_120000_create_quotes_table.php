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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('production_id')->nullable()->constrained('productions')->nullOnDelete();

            $table->string('number', 60)->index();
            $table->string('status', 20)->default('draft')->index(); // draft, sent, accepted, rejected, expired

            $table->string('client_name')->default('Cliente');
            $table->string('client_contact')->nullable();
            $table->string('client_email')->nullable()->index();
            $table->string('project_title')->nullable();
            $table->string('language', 10)->default('es');

            $table->text('request_text')->nullable();
            $table->json('inputs');
            $table->json('calculation');
            $table->json('ai_reading')->nullable();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('margin_amount', 10, 2)->default(0);
            $table->decimal('adjustment_amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(21.00);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2)->default(0);

            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
