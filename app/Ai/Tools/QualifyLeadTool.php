<?php

namespace App\Ai\Tools;

use App\Models\Lead;
use App\Services\Crm\LeadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QualifyLeadTool implements Tool
{
    public function __construct(
        protected int $tenantId,
        protected ?int $leadId = null,
        protected ?string $phone = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Qualifica o lead no CRM quando ele informa a empresa, nicho de atuação, objetivos com vídeos ou interesse em um pacote específico. Move o lead para a etapa "qualificado" e registra os dados comerciais.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Lead::forTenant($this->tenantId)
            ->whereNull('anonymized_at');

        if ($this->leadId) {
            $query->where('id', $this->leadId);
        } elseif ($this->phone) {
            $clean = preg_replace('/\D/', '', $this->phone);
            $suffix = strlen($clean) >= 8 ? substr($clean, -8) : $clean;
            $query->where(function ($q) use ($clean, $suffix) {
                $q->where('phone', 'like', "%{$clean}%")
                    ->orWhere('phone', 'like', "%{$suffix}%");
            });
        }

        $lead = $query->latest('id')->first();

        if (! $lead) {
            return 'Lead não encontrado para qualificação.';
        }

        $company = trim((string) ($request['company'] ?? ''));
        $niche = trim((string) ($request['niche'] ?? ''));
        $goal = trim((string) ($request['video_goal'] ?? ''));
        $pack = trim((string) ($request['pack_name'] ?? ''));
        $budget = isset($request['estimated_budget']) ? (float) $request['estimated_budget'] : null;
        $notes = trim((string) ($request['notes'] ?? ''));

        $updates = [];
        if ($company !== '' && empty($lead->company)) {
            $updates['company'] = $company;
        }
        if ($budget !== null && $budget > 0 && empty($lead->estimated_value)) {
            $updates['estimated_value'] = $budget;
        }
        if ($notes !== '') {
            $updates['notes'] = trim(($lead->notes ? $lead->notes."\n" : '').$notes);
        }

        if (! empty($updates)) {
            $lead->forceFill($updates)->save();
        }

        // Avança para a etapa 'qualificado' se estiver em etapas anteriores
        $leadService = app(LeadService::class);
        if (in_array($lead->stage, ['novo', 'contato'], true)) {
            $leadService->moveTo(
                $lead,
                'qualificado',
                null,
                'Qualificado pela Sofi no WhatsApp'.($niche !== '' ? " (Nicho: {$niche})" : ''),
                automatic: true
            );
        }

        // Registra a atividade no histórico do CRM
        $leadService->log(
            $lead,
            'whatsapp',
            "Qualificação automática via Sofi (WhatsApp):\n"
            .($company !== '' ? "• Empresa: {$company}\n" : '')
            .($niche !== '' ? "• Nicho: {$niche}\n" : '')
            .($goal !== '' ? "• Objetivo: {$goal}\n" : '')
            .($pack !== '' ? "• Pacote de interesse: {$pack}\n" : '')
            .($budget ? "• Orçamento estimado: € {$budget}\n" : ''),
            null,
            ['niche' => $niche, 'goal' => $goal, 'pack' => $pack]
        );

        return "Lead '{$lead->name}' (".($lead->company ?: 'Empresa a confirmar').") qualificado com sucesso para a etapa 'Qualificado' no CRM. Recomende o pacote mais adequado e pergunte se ele gostaria de receber a proposta formal de orçamento.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'company' => $schema->string()->description('Nome da empresa, loja, clínica ou marca do cliente.'),
            'niche' => $schema->string()->description('Nicho ou segmento de atuação (ex: Odontologia, Restaurante, E-commerce, Startup B2B).'),
            'video_goal' => $schema->string()->description('Objetivo principal dos vídeos (ex: captação de clientes locais via Reels, autoridade no LinkedIn, tráfego pago).'),
            'pack_name' => $schema->string()->description('Nome do pacote de vídeos que mais se alinha ao interesse do cliente.'),
            'estimated_budget' => $schema->number()->description('Valor estimado do orçamento ou preço base do pacote em Euros.'),
            'notes' => $schema->string()->description('Observações relevantes ditas pelo cliente durante a conversa.'),
        ];
    }
}

