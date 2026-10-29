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
        Schema::create('client_ai_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->cascadeOnDelete();
            $table->string('tone_preference')->nullable();
            $table->json('positive_patterns')->nullable();
            $table->json('anti_patterns')->nullable();
            $table->json('crm_learnings')->nullable();
            $table->json('chat_learnings')->nullable();
            $table->json('few_shot_exemplars')->nullable();
            $table->timestamp('last_distilled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'cliente_id']);
        });

        Schema::table('sofi_conversations', function (Blueprint $table) {
            $table->string('channel')->default('portal')->after('title'); // portal, whatsapp, storefront
            $table->string('contact_phone', 30)->nullable()->after('channel');

            $table->index(['tenant_id', 'contact_phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sofi_conversations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'contact_phone']);
            $table->dropColumn(['channel', 'contact_phone']);
        });

        Schema::dropIfExists('client_ai_memories');
    }
};

