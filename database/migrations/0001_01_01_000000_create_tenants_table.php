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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique()->index();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->string('primary_contact_email')->nullable();
            $table->string('company_cnpj')->nullable()->unique();
            $table->string('subscription_plan')->default('starter');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
