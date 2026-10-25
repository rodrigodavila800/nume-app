<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Publicação nativa: uma linha por rede, ligada à conta conectada, com tentativas e erro próprios.
     * As publicações via Upload-Post continuam como antes (driver = upload_post, várias redes na mesma linha).
     */
    public function up(): void
    {
        Schema::table('video_publications', function (Blueprint $table) {
            $table->string('driver', 30)->default('upload_post')->after('upload_post_request_id');
            $table->string('platform', 20)->nullable()->after('driver');
            $table->foreignId('social_connection_id')->nullable()->after('platform')
                ->constrained('social_connections')->nullOnDelete();
            $table->string('external_id', 100)->nullable()->after('social_connection_id');
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->string('last_error', 100)->nullable()->after('attempts');

            $table->index(['driver', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('video_publications', function (Blueprint $table) {
            $table->dropIndex(['driver', 'status']);
            $table->dropConstrainedForeignId('social_connection_id');
            $table->dropColumn(['driver', 'platform', 'external_id', 'attempts', 'last_error']);
        });
    }
};
