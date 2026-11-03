<?php

namespace App\Ai\Tools;

use App\Models\Lead;
use App\Models\SofiConversation;
use App\Services\WhatsApp\HandoffService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * A Sofi passa a conversa para a equipe: marca a conversa como "Precisa de você", registra no CRM e
 * avisa os administradores por e-mail (HandoffService). Só muda estado interno, não fala com o cliente.
 * Conversa e lead vêm do construtor, nunca do modelo.
 */
class EscalateToTeamTool implements Tool
{
    public function __construct(
        protected int $tenantId,
        protected ?int $conversationId = null,
        protected ?int $leadId = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Chama a equipe da produtora para assumir a conversa. Use para desconto ou condição especial, projeto fora do catálogo, reclamação, questão fiscal ou contratual, ou quando o cliente pedir uma pessoa. Depois diga ao cliente que a equipe foi avisada e retorna por aqui, sem prometer prazo.';
    }

    public function handle(Request $request): Stringable|string
    {
        $conversation = $this->conversationId
            ? SofiConversation::forTenant($this->tenantId)->where('channel', 'whatsapp')->find($this->conversationId)
            : null;

        if (! $conversation) {
            return 'Não consegui acionar a equipe automaticamente. Diga ao cliente que deixou registrado e que a equipe retorna.';
        }

        $lead = $this->leadId ? Lead::forTenant($this->tenantId)->whereNull('anonymized_at')->find($this->leadId) : null;

        $notified = app(HandoffService::class)->request(
            $conversation,
            $lead,
            (string) ($request['reason'] ?? 'other'),
            (string) ($request['summary'] ?? ''),
        );

        return $notified
            ? 'Equipe avisada. Diga ao cliente que uma pessoa da equipe retorna por este mesmo WhatsApp, sem prometer prazo.'
            : 'A equipe já estava avisada sobre esta conversa. Não chame esta ferramenta de novo; diga ao cliente que a equipe retorna por aqui.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()
                ->description('Motivo: '.implode(', ', array_keys(HandoffService::REASONS)).'.')
                ->required(),
            'summary' => $schema->string()
                ->description('Resumo curto (até 300 caracteres) do que o cliente quer e do que falta a equipe decidir.')
                ->required(),
        ];
    }
}
