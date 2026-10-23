<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pack_addon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('packs')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->decimal('custom_price', 10, 2)->nullable();
            $table->boolean('is_default_selected')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['pack_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_addon');
    }
};
