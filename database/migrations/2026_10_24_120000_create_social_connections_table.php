<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contas de redes sociais conectadas pelo próprio app da NUME (OAuth oficial de cada rede).
     * Tokens ficam criptografados (cast "encrypted" do model, com a APP_KEY) e nunca saem em JSON ou log.
     */
    public function up(): void
    {
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('provider', 20);
            $table->string('external_account_id', 100);
            $table->string('handle', 100)->nullable();
            $table->string('display_name', 100)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_refreshed_at')->nullable();
            $table->string('last_error', 100)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cliente_id', 'platform', 'external_account_id'], 'social_connections_account_unique');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_connections');
    }
};
