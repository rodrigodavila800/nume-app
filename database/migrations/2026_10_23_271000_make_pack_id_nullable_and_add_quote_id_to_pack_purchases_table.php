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
            $table->foreignId('pack_id')->nullable()->change();
            $table->foreignId('quote_id')
                ->nullable()
                ->after('pack_id')
                ->constrained('quotes')
                ->nullOnDelete();

            $table->index(['tenant_id', 'quote_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'quote_id']);
            $table->dropConstrainedForeignId('quote_id');
            $table->foreignId('pack_id')->nullable(false)->change();
        });
    }
};
