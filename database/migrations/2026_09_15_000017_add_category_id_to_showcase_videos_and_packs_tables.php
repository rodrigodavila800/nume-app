<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcase_videos', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('thumbnail_url')->constrained('categories')->nullOnDelete();
        });

        Schema::table('packs', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('tenant_id')->constrained('categories')->nullOnDelete();
            $table->json('features')->nullable()->after('description');
            $table->json('addons')->nullable()->after('features');
            $table->decimal('vat_rate', 5, 2)->default(21.00)->after('price'); // IVA padrão na Espanha (+ IVA 21%)
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'features', 'addons', 'vat_rate']);
        });

        Schema::table('showcase_videos', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id']);
        });
    }
};
