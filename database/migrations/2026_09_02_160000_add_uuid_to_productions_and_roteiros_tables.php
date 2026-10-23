<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->index();
        });

        Schema::table('roteiros', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });

        Schema::table('roteiros', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
