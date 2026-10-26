<?php

namespace App\Services\Quotes;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Quote;
use App\Models\VideoType;

/**
 * Transforma a solicitação do cliente (feita no portal, a partir do plano aprovado) num orçamento
 * em análise: a calculadora recebe o pedido como entrada e a equipe só ajusta e envia.
 */
class QuoteRequestService
{
    public function __construct(
        protected QuoteCalculatorService $calculator,
        protected SofiQuoteEstimatorService $estimator,
    ) {}

    /**
     * Pedido em aberto do mesmo plano e tipo (evita duplicar ao clicar duas vezes).
     */
    public function openRequest(Cliente $cliente, MarketingPlan $plan, string $kind = Quote::KIND_PRODUCTION): ?Quote
    {
        return Quote::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('marketing_plan_id', $plan->id)
            ->where('kind', $kind)
            ->where('status', Quote::STATUS_REQUESTED)
            ->first();
    }

    /**
     * @param  array{videos: int, video_type_id: ?int, social_networks: int, wants_ads: bool, ads_budget: ?string, message: ?string}  $data
     */
    public function createFromPortal(Cliente $cliente, MarketingPlan $plan, array $data, ?VideoType $videoType, bool $isEs): Quote
    {
        $tenant = $cliente->tenant;
        $requestText = $this->requestText($cliente, $data, $videoType);

        // Leitura da Sofi (IA ou heurística) só para horas de pré, gravação e equipe; a quantidade
        // de vídeos é a que o cliente pediu.
        $reading = $this->estimator->estimate($requestText, $tenant);

        $defaults = $this->calculator->getDefaults($tenant);
        $inputs = array_merge($defaults, array_intersect_key($reading['pedido'] ?? [], array_flip(['pre', 'gravacao', 'motion', 'filmmakers', 'drone'])));
        $inputs['videos'] = $data['videos'];
        $inputs['edicao'] = round($data['videos'] * $this->editHoursPerVideo($videoType, $defaults), 2);

        $calc = $this->calculator->calculate($inputs);

        return Quote::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'marketing_plan_id' => $plan->id,
            'status' => Quote::STATUS_REQUESTED,
            'kind' => Quote::KIND_PRODUCTION,
            'client_name' => $cliente->company_name ?: $cliente->name,
            'client_email' => $cliente->email,
            'client_contact' => $cliente->name,
            'project_title' => ($isEs ? 'Plan de vídeos ' : 'Plano de vídeos ').$plan->month_year,
            'language' => $isEs ? 'es' : 'pt_BR',
            'request_text' => $requestText,
            'request_details' => [
                'videos' => $data['videos'],
                'video_type_id' => $videoType?->id,
                'video_type_name' => $videoType?->name,
                'social_networks' => $data['social_networks'],
                'wants_ads' => $data['wants_ads'],
                'ads_budget' => $data['wants_ads'] ? $data['ads_budget'] : null,
                'message' => $data['message'],
                'posting_frequency' => $cliente->anamnesis['posting_frequency'] ?? null,
            ],
            'inputs' => $calc['inputs_sanitized'],
            'calculation' => $calc,
            'ai_reading' => $reading,
            'subtotal' => $calc['preco'],
            'margin_amount' => $calc['margem'],
            'adjustment_amount' => $calc['adicional'],
            'tax_rate' => $calc['inputs_sanitized']['iva'],
            'tax_amount' => $calc['iva'],
            'total_amount' => $calc['total_com_iva'],
            'deposit_amount' => $calc['sinal'],
            'balance_amount' => $calc['na_entrega'],
        ]);
    }

    protected function editHoursPerVideo(?VideoType $videoType, array $defaults): float
    {
        return (float) $defaults['horas_edicao_por_video'];
    }

    /**
     * Texto do pedido para a leitura da Sofi e para a equipe. A mensagem livre é do cliente.
     */
    protected function requestText(Cliente $cliente, array $data, ?VideoType $videoType): string
    {
        return implode("\n", array_filter([
            'Empresa: '.($cliente->company_name ?: $cliente->name),
            'Vídeos por mês: '.$data['videos'],
            $videoType ? "Tipo de vídeo: {$videoType->name} ({$videoType->duration_seconds} s)" : null,
            'Redes para publicar: '.$data['social_networks'],
            'Anúncios: '.($data['wants_ads'] ? 'sim'.($data['ads_budget'] ? " (verba: {$data['ads_budget']})" : '') : 'não'),
            $data['message'] ? 'Mensagem do cliente: '.$data['message'] : null,
        ]));
    }
}
