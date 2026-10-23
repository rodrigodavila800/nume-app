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
            // Permite status 'expired'
            $table->string('status', 30)->default('pending')->change();

            // Prazos e conciliação de transferência bancária
            $table->timestamp('expires_at')->nullable()->after('paid_at');
            $table->foreignId('confirmed_by_user_id')
                ->nullable()
                ->after('expires_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_user_id');

            $table->index(['status', 'expires_at']);
            $table->index(['tenant_id', 'status', 'payment_gateway']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropIndex(['tenant_id', 'status', 'payment_gateway']);
            $table->dropConstrainedForeignId('confirmed_by_user_id');
            $table->dropColumn(['expires_at', 'confirmed_at']);
        });
    }
};
