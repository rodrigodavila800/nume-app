<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\MarketingPlanItem;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Roteiro;
use App\Services\MarketingPlanGeneratorService;
use App\Services\Quotes\QuoteRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Plano de marketing do cliente: anamnese → plano em rascunho → aprovação do cliente.
 * Só item de plano aprovado vira produção (e só então nasce roteiro).
 */
class MarketingPlanController extends Controller
{
    public function __construct(
        protected MarketingPlanGeneratorService $planGenerator
    ) {}

    /**
     * Exibe o Plano de Marketing & Grade Editorial do Cliente.
     */
    public function index(Request $request): View|RedirectResponse
    {
        /** @var Cliente $cliente */
        $cliente = auth('cliente')->user();

        if (! $cliente->hasCompletedAnamnesis()) {
            return redirect()->route('portal.anamnesis.edit')
                ->with('warning', __('Antes do plano de marketing, responda a anamnese da sua marca. Leva poucos minutos.'));
        }

        $currentMonth = now()->format('Y-m');

        // Busca plano existente ou gera automaticamente para o mês
        $plan = $this->planFor($cliente, $currentMonth)?->load(['items.production', 'items.videoType']);

        // Detecta se o cliente opera em espanhol e calibra o idioma
        $isEs = MarketingPlanGeneratorService::isSpanishLocale($cliente);
        if ($isEs && session('locale') !== 'es' && MarketingPlanGeneratorService::isSpanishProfile($cliente)) {
            session()->put('locale', 'es');
            app()->setLocale('es');
        }

        if (! $plan) {
            $latestPurchase = $cliente->packPurchases()->latest('id')->first();
            $videoQty = MarketingPlanGeneratorService::videoQuantityFor($cliente, $latestPurchase);

            $plan = $this->planGenerator->generate($cliente, $videoQty, $latestPurchase, $currentMonth)
                ->load(['items.production', 'items.videoType']);
        }

        $allPlans = MarketingPlan::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->orderBy('month_year', 'desc')
            ->get();

        $relatedQuote = $plan
            ? Quote::forTenant($cliente->tenant_id)
                ->where('cliente_id', $cliente->id)
                ->where('marketing_plan_id', $plan->id)
                ->where('kind', Quote::KIND_PRODUCTION)
                ->latest('id')
                ->first()
            : null;

        return view('portal.marketing_plan.index', compact('plan', 'allPlans', 'relatedQuote'));
    }

    /**
     * Regera a grade editorial com a Sofi IA preservando produções já criadas.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        /** @var Cliente $cliente */
        $cliente = auth('cliente')->user();

        if (! $cliente->hasCompletedAnamnesis()) {
            return redirect()->route('portal.anamnesis.edit');
        }

        $currentMonth = now()->format('Y-m');
        $existingPlan = $this->planFor($cliente, $currentMonth);

        $latestPurchase = $cliente->packPurchases()->latest('id')->first();
        $videoQty = MarketingPlanGeneratorService::videoQuantityFor($cliente, $latestPurchase);

        if (! $existingPlan) {
            $this->planGenerator->generate($cliente, $videoQty, $latestPurchase, $currentMonth);

            return redirect()->route('portal.marketing-plan.index')
                ->with('status', __('Grade Editorial e Plano de Marketing calculados com sucesso pela Sofi IA!'));
        }

        // 1. Identifica os itens que já foram transformados em produção audiovisual
        $preservedCount = $existingPlan->items()->whereNotNull('production_id')->count();

        // Se todos os vídeos da cota já viraram produção, não recalcula
        if ($preservedCount >= $videoQty) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('warning', __('Todos os vídeos da sua grade deste mês já foram transformados em produção audiovisual na sua esteira! Para produzir novos vídeos, contrate um novo pacote.'));
        }

        // 2. Executa a regeneração parcial preservando as produções ativas (com o pedido de ajuste, se houver)
        $newGeneratedCount = $this->planGenerator->regeneratePlan($existingPlan, $cliente, $videoQty, $existingPlan->adjustment_request);

        $msg = $preservedCount > 0
            ? __('Grade recalculada com sucesso! :new novas ideias geradas pela Sofi IA. Seus :preserved vídeos já em produção foram 100% preservados na esteira.', [
                'new' => $newGeneratedCount,
                'preserved' => $preservedCount,
            ])
            : __('Grade Editorial e Plano de Marketing recalculados com sucesso pela Sofi IA!');

        return redirect()->route('portal.marketing-plan.index')->with('status', $msg.' '.__('Revise e aprove o plano para seguir.'));
    }

    /**
     * Cliente aprova o plano: a partir daqui os itens podem virar produção e roteiro.
     */
    public function approve(Request $request, int $plan, QuoteRequestService $quoteRequests): RedirectResponse
    {
        $cliente = $request->user('cliente');
        $model = $this->ownPlan($cliente, $plan);

        if (! $model->isApproved()) {
            $model->approveBy($cliente);
        }

        // Se o cliente não tem pacote ativo que cubra a cota, gera automaticamente a solicitação de orçamento
        $hasActivePack = $cliente->packPurchases()
            ->whereIn('status', ['paid', 'active'])
            ->whereColumn('videos_used', '<', 'video_quantity')
            ->exists();

        if (! $hasActivePack) {
            $quote = $quoteRequests->ensureRequestForPlan($cliente, $model);
            $msg = $quote
                ? __('Plano aprovado com sucesso! A solicitação de orçamento #:num foi gerada e enviada para a equipe da produtora.', ['num' => $quote->number])
                : __('Plano aprovado! Agora os vídeos podem seguir para orçamento e produção.');

            return redirect()->route('portal.marketing-plan.index')->with('status', $msg);
        }

        return redirect()->route('portal.marketing-plan.index')
            ->with('status', __('Plano aprovado! Seus vídeos foram liberados para a esteira de produção.'));
    }

    /**
     * Cliente pede ajuste: o texto vai para a Sofi na próxima geração (como dado, não como ordem).
     */
    public function requestAdjustment(Request $request, int $plan): RedirectResponse
    {
        $cliente = $request->user('cliente');
        $model = $this->ownPlan($cliente, $plan);

        $validated = $request->validate(['adjustment_request' => ['required', 'string', 'min:10', 'max:1000']]);

        if ($model->isApproved()) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('warning', __('Este plano já foi aprovado. Para mudar, fale com a sua produtora.'));
        }

        $model->forceFill(['status' => MarketingPlan::STATUS_ADJUSTMENT, 'adjustment_request' => $validated['adjustment_request']])->save();

        $latestPurchase = $cliente->packPurchases()->latest('id')->first();
        $this->planGenerator->regeneratePlan($model, $cliente, MarketingPlanGeneratorService::videoQuantityFor($cliente, $latestPurchase), $validated['adjustment_request']);

        return redirect()->route('portal.marketing-plan.index')
            ->with('status', __('A Sofi refez o plano com o seu pedido. Revise e aprove para seguir.'));
    }

    /**
     * Transforma um item da grade editorial em um projeto de produção audiovisual real.
     */
    public function convertToProduction(Request $request, MarketingPlanItem $item): RedirectResponse
    {
        /** @var Cliente $cliente */
        $cliente = auth('cliente')->user();

        // Recupera o plano ignorando global scope para verificação precisa de autorização multi-tenant
        $plan = MarketingPlan::withoutGlobalScope('tenant')->find($item->marketing_plan_id);

        if (! $plan || $plan->cliente_id !== $cliente->id || $plan->tenant_id !== $cliente->tenant_id) {
            abort(403, 'Ação não autorizada.');
        }

        // Se já foi convertido para produção, redireciona diretamente
        if ($item->production_id && $item->production) {
            return redirect()->route('portal.productions.show', $item->production)
                ->with('status', __('Este vídeo já está em produção na esteira!'));
        }

        // Roteiro só depois do plano aprovado
        if (! $plan->isApproved()) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('warning', __('Aprove o plano de marketing antes de enviar vídeos para produção.'));
        }

        // Validação de cobertura comercial: pacote ativo OU orçamento aprovado/aceito
        $hasActivePack = $cliente->packPurchases()
            ->whereIn('status', ['paid', 'active'])
            ->whereColumn('videos_used', '<', 'video_quantity')
            ->exists();

        $hasAcceptedQuote = Quote::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('marketing_plan_id', $plan->id)
            ->where('status', 'accepted')
            ->exists();

        if (! $hasActivePack && ! $hasAcceptedQuote) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('warning', __('Para enviar vídeos para a esteira de produção, é necessário que o orçamento esteja aprovado e o sinal liquidado (ou a contratação de um pacote de vídeos).'));
        }

        // Responsável da produtora que figura como criador da produção (de preferência um admin).
        // Nunca criamos usuário aqui: uma conta da equipe só nasce no cadastro da produtora.
        $tenantUser = $cliente->tenant->users()->orderByRaw("role = 'admin' desc")->orderBy('id')->first();
        if (! $tenantUser) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('error', __('A produtora ainda não tem equipe cadastrada para receber este vídeo. Fale com a sua produtora.'));
        }

        $videoType = $item->videoType()->where('tenant_id', $cliente->tenant_id)->first();

        // 1. Cria a Production no banco
        $production = Production::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'pack_purchase_id' => $plan->pack_purchase_id,
            'video_type_id' => $videoType?->id,
            'created_by' => $tenantUser->id,
            'title' => $item->topic,
            'description' => "Vídeo planejado pela Sofi na Grade Editorial (Semana {$item->week_number} - Funil ".strtoupper($item->funnel_stage).'). '.($item->seo_caption ?? ''),
            'status' => 'pending_review',
            'seo_keywords' => is_array($item->hashtags) ? implode(', ', $item->hashtags) : null,
        ]);

        // 2. Cria o Roteiro estruturado inicial
        $hook = $item->hook_text ?? 'Gancho de alto impacto para reter atenção nos primeiros 3 segundos.';
        $body = $item->seo_caption ?? 'Desenvolvimento da mensagem com base no nicho e diferenciais da marca.';
        $cta = 'Acesse nosso perfil e clique no link da bio para saber mais!';

        $fullScript = "🎬 [CENA 1 - GANCHO 0-3S]\n{$hook}\n\n💡 [CENA 2 - CORPO & DIFERENCIAIS]\n{$body}\n\n🎯 [CENA 3 - CHAMADA PARA AÇÃO]\n{$cta}";

        Roteiro::create([
            'tenant_id' => $cliente->tenant_id,
            'production_id' => $production->id,
            'video_type_id' => $videoType?->id,
            'structure' => $videoType?->snapshot(),
            'created_by' => $tenantUser->id,
            'status' => 'draft',
            'content' => $fullScript,
        ]);

        // 3. Vincula o item da grade à nova produção
        $item->update([
            'production_id' => $production->id,
            'status' => 'in_production',
        ]);

        return redirect()->route('portal.productions.show', $production)
            ->with('status', __('Projeto criado com sucesso! Seu roteiro foi encaminhado para a Esteira de Produção.'));
    }

    private function planFor(Cliente $cliente, string $monthYear): ?MarketingPlan
    {
        return MarketingPlan::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('month_year', $monthYear)
            ->first();
    }

    private function ownPlan(Cliente $cliente, int $id): MarketingPlan
    {
        return MarketingPlan::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->findOrFail($id);
    }
}
