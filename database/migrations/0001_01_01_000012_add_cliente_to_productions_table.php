<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('tenant_id')->constrained('clientes')->nullOnDelete();
            $table->foreignId('pack_purchase_id')->nullable()->after('cliente_id')->constrained('pack_purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropConstrainedForeignId('pack_purchase_id');
        });
    }
};
