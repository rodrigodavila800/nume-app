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
        Schema::create('marketing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pack_purchase_id')->nullable()->constrained('pack_purchases')->nullOnDelete();
            $table->string('month_year', 7); // Ex: '2026-10'
            $table->string('title')->default('Plano Estratégico de Conteúdo');
            $table->text('strategic_goal')->nullable();
            $table->string('status')->default('active'); // draft, active, completed, archived
            $table->json('metrics_summary')->nullable(); // Resumo de metas e retenção estimada
            $table->timestamps();

            $table->index(['tenant_id', 'cliente_id']);
        });

        Schema::create('marketing_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_plan_id')->constrained('marketing_plans')->cascadeOnDelete();
            $table->foreignId('production_id')->nullable()->constrained('productions')->nullOnDelete();
            $table->unsignedTinyInteger('week_number')->default(1);
            $table->enum('funnel_stage', ['top', 'middle', 'bottom'])->default('top');
            $table->string('scheduled_day_time')->nullable(); // Ex: 'Terça-feira, 18h30'
            $table->string('topic');
            $table->text('hook_text')->nullable(); // Gancho de scroll-stop (0-3s)
            $table->text('seo_caption')->nullable(); // Legenda persuasiva
            $table->json('hashtags')->nullable();
            $table->enum('format', ['vertical', 'horizontal'])->default('vertical');
            $table->string('status')->default('pending'); // pending, in_production, published
            $table->timestamps();

            $table->index(['marketing_plan_id', 'week_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_plan_items');
        Schema::dropIfExists('marketing_plans');
    }
};
