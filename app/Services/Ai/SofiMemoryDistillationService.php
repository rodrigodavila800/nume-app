<?php

namespace App\Services\Ai;

use App\Models\ClientAiMemory;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Roteiro;
use App\Models\SofiConversation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SofiMemoryDistillationService
{
    /**
     * Localiza ou instancia a memória de IA para o cliente e tenant.
     */
    public function getOrCreateMemory(int $tenantId, ?int $clienteId = null): ClientAiMemory
    {
        $memory = ClientAiMemory::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('cliente_id', $clienteId)
            ->first();

        if (! $memory) {
            $memory = new ClientAiMemory([
                'tenant_id' => $tenantId,
                'cliente_id' => $clienteId,
                'positive_patterns' => [],
                'anti_patterns' => [],
                'crm_learnings' => [],
                'chat_learnings' => [],
                'few_shot_exemplars' => [],
            ]);
            $memory->forceFill(['tenant_id' => $tenantId])->save();
        }

        return $memory;
    }

    /**
     * Aprende a partir da aprovação ou rejeição de um roteiro.
     */
    public function distillFromScriptFeedback(Roteiro $roteiro, ?string $feedback, bool $approved): void
    {
        $production = $roteiro->production;
        if (! $production || ! $production->cliente_id) {
            return;
        }

        $memory = $this->getOrCreateMemory($production->tenant_id, $production->cliente_id);

        if ($approved) {
            $hook = is_array($roteiro->structure) ? ($roteiro->structure['hook'] ?? null) : null;
            if (! $hook && is_string($roteiro->content) && filled($roteiro->content)) {
                $hook = Str::limit(trim($roteiro->content), 120);
            }
            $pattern = $hook ? "Gancho aprovado: \"{$hook}\"" : "Estrutura do roteiro \"{$roteiro->id}\" aprovada.";
            $memory->addPositivePattern($pattern);

            // Armazena como exemplar de sucesso
            $memory->addFewShotExemplar([
                'id' => $roteiro->id,
                'hook' => $hook,
                'approved_at' => now()->toIso8601String(),
            ]);

            Log::info("[SofiCognitiveMemory] Aprendizado positivo registrado para Cliente #{$production->cliente_id} (Roteiro #{$roteiro->id}).");
        } else {
            $reason = trim($feedback ?: 'Conteúdo não alinhado com a identidade da marca');
            $antiPattern = "Roteiro rejeitado: {$reason}";
            $memory->addAntiPattern($antiPattern);

            Log::info("[SofiCognitiveMemory] Anti-pattern registrado para Cliente #{$production->cliente_id}: {$antiPattern}");
        }

        $memory->last_distilled_at = now();
        $memory->save();
    }

    /**
     * Aprende a partir da perda de um lead / orçamento no CRM.
     */
    public function distillFromLeadLost(Lead $lead, ?string $reason): void
    {
        if (empty($reason)) {
            return;
        }

        $memory = $this->getOrCreateMemory($lead->tenant_id, $lead->cliente_id);
        $cleanReason = trim($reason);

        $memory->addCrmLearning("Negócio perdido ({$lead->name}): {$cleanReason}");
        $memory->last_distilled_at = now();
        $memory->save();

        Log::info("[SofiCognitiveMemory] Aprendizado comercial de CRM registrado para Lead #{$lead->id}: {$cleanReason}");
    }

    /**
     * Aprende a partir de sessões de conversa no Chat (Portal, WhatsApp ou Storefront).
     */
    public function distillFromChat(SofiConversation $conversation): void
    {
        $messages = $conversation->messages()->latest('id')->take(6)->get()->reverse();

        if ($messages->isEmpty()) {
            return;
        }

        $userQuestions = [];
        foreach ($messages as $msg) {
            if ($msg->sender === 'user' && mb_strlen($msg->content) > 10) {
                // Captura a essência da pergunta ou objeção do usuário
                $userQuestions[] = mb_substr(trim($msg->content), 0, 150);
            }
        }

        if (empty($userQuestions)) {
            return;
        }

        $memory = $this->getOrCreateMemory($conversation->tenant_id, $conversation->cliente_id);

        foreach ($userQuestions as $question) {
            $memory->addChatLearning("[{$conversation->channel}] Dúvida/Tópico: \"{$question}\"");
        }

        $memory->last_distilled_at = now();
        $memory->save();

        Log::info("[SofiCognitiveMemory] Aprendizado de conversa ({$conversation->channel}) registrado para conversa #{$conversation->id}.");
    }

    /**
     * Retorna o bloco de prompt com a memória cognitiva formatada.
     */
    public function getMemoryPromptForCliente(?Cliente $cliente, int $tenantId): string
    {
        if (! $cliente) {
            $generalMemory = ClientAiMemory::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->whereNull('cliente_id')
                ->first();

            return $generalMemory ? $generalMemory->formatForPrompt() : '';
        }

        $memory = $this->getOrCreateMemory($tenantId, $cliente->id);

        return $memory->formatForPrompt();
    }
}
