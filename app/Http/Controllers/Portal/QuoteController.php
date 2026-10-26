<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Quote;
use App\Models\VideoType;
use App\Services\MarketingPlanGeneratorService;
use App\Services\Quotes\QuoteRequestService;
use App\Services\VideoTypes\VideoTypeCatalog;
use App\Support\Anamnesis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    /**
     * Lista todos os orçamentos e propostas enviados para o cliente autenticado.
     */
    public function index(Request $request): View
    {
        $cliente = $request->user('cliente');

        $quotes = $cliente->allQuotes()
            ->whereIn('status', [Quote::STATUS_REQUESTED, 'sent', 'accepted', 'rejected'])
            ->latest()
            ->paginate(10);

        $canRequest = $this->approvedPlan($cliente) !== null;

        return view('portal.quotes.index', compact('quotes', 'cliente', 'canRequest'));
    }

    /**
     * Formulário de pedido de orçamento, preenchido com o plano aprovado e a anamnese.
     */
    public function requestForm(Request $request, VideoTypeCatalog $catalog): View|RedirectResponse
    {
        $cliente = $request->user('cliente');
        $plan = $this->approvedPlan($cliente);

        if (! $plan) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('warning', __('O orçamento parte do seu plano de marketing. Aprove o plano para pedir o orçamento.'));
        }

        $plan->load('items');
        $videoTypes = $catalog->active($cliente->tenant_id);
        $mainType = $plan->items->pluck('video_type_id')->filter()->countBy()->sortDesc()->keys()->first();

        return view('portal.quotes.request', [
            'plan' => $plan,
            'videoTypes' => $videoTypes,
            'defaults' => [
                'videos' => $plan->items->count() ?: (Anamnesis::monthlyVideos($cliente->anamnesis) ?? 4),
                'video_type_id' => $mainType,
                'social_networks' => max(1, count($cliente->anamnesis['priority_networks'] ?? [])),
                'wants_ads' => (bool) ($cliente->anamnesis['wants_ads'] ?? false),
                'ads_budget' => $cliente->anamnesis['ads_monthly_budget'] ?? null,
            ],
        ]);
    }

    /**
     * Cria o orçamento em análise: a calculadora recebe o pedido e a equipe ajusta e envia.
     */
    public function storeRequest(Request $request, QuoteRequestService $requests): RedirectResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');
        $plan = $this->approvedPlan($cliente);

        if (! $plan) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('warning', __('O orçamento parte do seu plano de marketing. Aprove o plano para pedir o orçamento.'));
        }

        $validated = $request->validate([
            'videos' => ['required', 'integer', 'min:1', 'max:60'],
            'video_type_id' => ['nullable', 'integer'],
            'social_networks' => ['required', 'integer', 'min:1', 'max:8'],
            'wants_ads' => ['boolean'],
            'ads_budget' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $videoType = null;
        if (! empty($validated['video_type_id'])) {
            $videoType = VideoType::forTenant($cliente->tenant_id)->active()->find($validated['video_type_id']);
            if (! $videoType) {
                return back()->withErrors(['video_type_id' => __('Tipo de vídeo inválido ou inativo.')])->withInput();
            }
        }

        if ($open = $requests->openRequest($cliente, $plan)) {
            return redirect()->route('portal.quotes.index')
                ->with('status', __('Seu pedido de orçamento #:num já está com a produtora.', ['num' => $open->number]));
        }

        $quote = $requests->createFromPortal($cliente, $plan, [
            'videos' => (int) $validated['videos'],
            'video_type_id' => $videoType?->id,
            'social_networks' => (int) $validated['social_networks'],
            'wants_ads' => (bool) ($validated['wants_ads'] ?? false),
            'ads_budget' => $validated['ads_budget'] ?? null,
            'message' => $validated['message'] ?? null,
        ], $videoType, MarketingPlanGeneratorService::isSpanishLocale($cliente));

        return redirect()->route('portal.quotes.index')
            ->with('status', __('Pedido de orçamento #:num enviado. A produtora revisa os valores e manda a proposta para você aprovar.', ['num' => $quote->number]));
    }

    /**
     * Plano aprovado mais recente do cliente (o do mês, se houver).
     */
    private function approvedPlan(Cliente $cliente): ?MarketingPlan
    {
        return MarketingPlan::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('status', MarketingPlan::STATUS_APPROVED)
            ->orderByDesc('month_year')
            ->orderByDesc('id')
            ->first();
    }
}
