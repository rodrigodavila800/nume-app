<?php

namespace App\Services\Quotes;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Quote;
use App\Models\VideoType;
use App\Services\MarketingPlanGeneratorService;
use App\Support\Anamnesis;

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
     * Garante que exista uma solicitação de orçamento vinculada a um plano aprovado.
     * Se já existir qualquer Quote para este plano (requested, draft, sent, accepted), retorna ele.
     * Caso contrário, cria o Quote production em requested (e o de ads se o cliente desejar).
     */
    public function ensureRequestForPlan(Cliente $cliente, MarketingPlan $plan): ?Quote
    {
        $existing = Quote::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('marketing_plan_id', $plan->id)
            ->where('kind', Quote::KIND_PRODUCTION)
            ->first();

        if ($existing) {
            return $existing;
        }

        $itemsCount = $plan->items()->count();
        $monthlyVideos = Anamnesis::monthlyVideos($cliente->anamnesis) ?? 4;
        $videoQty = $itemsCount > 0 ? $itemsCount : $monthlyVideos;

        $mainType = $plan->items()->pluck('video_type_id')->filter()->countBy()->sortDesc()->keys()->first();
        $videoType = $mainType ? VideoType::forTenant($cliente->tenant_id)->active()->find($mainType) : null;

        $socialNetworks = max(1, count($cliente->anamnesis['priority_networks'] ?? []));
        $wantsAds = (bool) ($cliente->anamnesis['wants_ads'] ?? false);
        $adsBudget = $cliente->anamnesis['ads_monthly_budget'] ?? null;

        $data = [
            'videos' => (int) $videoQty,
            'video_type_id' => $videoType?->id,
            'social_networks' => $socialNetworks,
            'wants_ads' => $wantsAds,
            'ads_budget' => $adsBudget,
            'message' => null,
        ];

        $isEs = MarketingPlanGeneratorService::isSpanishLocale($cliente);

        $quote = $this->createFromPortal($cliente, $plan, $data, $videoType, $isEs);

        if ($wantsAds && ! $this->openRequest($cliente, $plan, Quote::KIND_ADS)) {
            $this->createAdsFromPortal($cliente, $plan, $data, $isEs);
        }

        return $quote;
    }

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
        $inputs['redes'] = $data['social_networks'];
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

    /**
     * Pedido de anúncios (serviço da NUME: criativos + gestão). A verba que o cliente informou
     * entra só como informação; ele paga direto à plataforma.
     */
    public function createAdsFromPortal(Cliente $cliente, MarketingPlan $plan, array $data, bool $isEs): Quote
    {
        $inputs = $this->calculator->getAdsDefaults($cliente->tenant);
        $inputs['verba_mensal'] = $this->parseAmount($data['ads_budget'] ?? null);
        $inputs['plataformas'] = $this->adPlatforms($cliente->anamnesis['priority_networks'] ?? []) ?: $inputs['plataformas'];

        $calc = $this->calculator->calculateAds($inputs);

        return Quote::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'marketing_plan_id' => $plan->id,
            'status' => Quote::STATUS_REQUESTED,
            'kind' => Quote::KIND_ADS,
            'client_name' => $cliente->company_name ?: $cliente->name,
            'client_email' => $cliente->email,
            'client_contact' => $cliente->name,
            'project_title' => ($isEs ? 'Campaña de anuncios ' : 'Campanha de anúncios ').$plan->month_year,
            'language' => $isEs ? 'es' : 'pt_BR',
            'request_text' => 'Anúncios: verba pensada '.($data['ads_budget'] ?: 'não informada').($data['message'] ? "\nMensagem do cliente: ".$data['message'] : ''),
            'request_details' => ['ads_budget' => $data['ads_budget'] ?? null, 'message' => $data['message'] ?? null],
            'inputs' => $calc['inputs_sanitized'],
            'calculation' => $calc,
            'subtotal' => $calc['preco'],
            'margin_amount' => 0,
            'adjustment_amount' => $calc['adicional'],
            'tax_rate' => $calc['inputs_sanitized']['iva'],
            'tax_amount' => $calc['iva'],
            'total_amount' => $calc['total_com_iva'],
            'deposit_amount' => $calc['sinal'],
            'balance_amount' => $calc['na_entrega'],
        ]);
    }

    /**
     * "300 €", "1.500,50" → 300.0 / 1500.5. Texto do cliente: sem número vira 0.
     */
    protected function parseAmount(?string $text): float
    {
        $clean = preg_replace('/[^\d,.]/', '', (string) $text);
        if ($clean === '') {
            return 0.0;
        }

        if (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', str_replace('.', '', $clean));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
            // "1.500" no padrão espanhol é milhar, não decimal
            $clean = str_replace('.', '', $clean);
        }

        return min(10000000, max(0, (float) $clean));
    }

    /**
     * Redes da anamnese → plataformas de anúncio (Instagram, Facebook e Threads anunciam pela Meta).
     */
    protected function adPlatforms(array $networks): array
    {
        $map = ['instagram' => 'meta', 'facebook' => 'meta', 'threads' => 'meta', 'youtube' => 'youtube', 'tiktok' => 'tiktok', 'linkedin' => 'linkedin', 'pinterest' => 'pinterest', 'x' => 'x'];

        return array_values(array_unique(array_filter(array_map(fn ($n) => $map[$n] ?? null, $networks))));
    }

    /**
     * Horas de edição por vídeo: as do tipo escolhido, senão o padrão da produtora.
     */
    protected function editHoursPerVideo(?VideoType $videoType, array $defaults): float
    {
        return $videoType?->edit_hours_per_video !== null
            ? (float) $videoType->edit_hours_per_video
            : (float) $defaults['horas_edicao_por_video'];
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
