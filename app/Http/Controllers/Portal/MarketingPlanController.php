<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MarketingPlan;
use App\Services\MarketingPlanGeneratorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingPlanController extends Controller
{
    public function __construct(
        protected MarketingPlanGeneratorService $planGenerator
    ) {}

    /**
     * Exibe o Plano de Marketing & Grade Editorial do Cliente.
     */
    public function index(Request $request): View
    {
        /** @var \App\Models\Cliente $cliente */
        $cliente = auth('cliente')->user();
        $currentMonth = now()->format('Y-m');

        // Busca plano existente ou gera automaticamente para o mês
        $plan = MarketingPlan::where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('month_year', $currentMonth)
            ->with(['items.production'])
            ->first();

        // Detecta se o cliente opera em espanhol e calibra o idioma
        $isEs = MarketingPlanGeneratorService::isSpanishLocale($cliente);
        if ($isEs && session('locale') !== 'es' && MarketingPlanGeneratorService::isSpanishProfile($cliente)) {
            session()->put('locale', 'es');
            app()->setLocale('es');
        }

        if (!$plan) {
            // Se o cliente tem compras de pacotes, pega a quantidade de vídeos do último pacote
            $latestPurchase = $cliente->packPurchases()->latest('id')->first();
            $videoQty = $latestPurchase?->video_quantity ?: 4;

            $plan = $this->planGenerator->generate($cliente, $videoQty, $latestPurchase, $currentMonth);
        } elseif ($isEs && $plan->items()->whereNotNull('production_id')->count() === 0) {
            // Se o plano foi originalmente gerado em PT mas o cliente é hispanofalante, sincroniza a grade para ES
            $firstTopic = $plan->items->first()?->topic ?? '';
            if (str_starts_with($firstTopic, 'O maior erro') || str_starts_with($plan->title, 'Plano')) {
                $videoQty = $plan->items->count() ?: 4;
                $this->planGenerator->regeneratePlan($plan, $cliente, $videoQty);
                $plan = $plan->fresh(['items.production']);
            }
        }

        $allPlans = MarketingPlan::where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->orderBy('month_year', 'desc')
            ->get();

        return view('portal.marketing_plan.index', compact('plan', 'allPlans'));
    }

    /**
     * Regera a grade editorial com a Sofi IA preservando produções já criadas.
     */
    public function regenerate(Request $request)
    {
        /** @var \App\Models\Cliente $cliente */
        $cliente = auth('cliente')->user();
        $currentMonth = now()->format('Y-m');

        $existingPlan = MarketingPlan::where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('month_year', $currentMonth)
            ->first();

        $latestPurchase = $cliente->packPurchases()->latest('id')->first();
        $videoQty = $latestPurchase?->video_quantity ?: 4;

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

        // 2. Executa a regeneração parcial preservando as produções ativas
        $newGeneratedCount = $this->planGenerator->regeneratePlan($existingPlan, $cliente, $videoQty);

        $msg = $preservedCount > 0
            ? __("Grade recalculada com sucesso! :new novas ideias geradas pela Sofi IA. Seus :preserved vídeos já em produção foram 100% preservados na esteira.", [
                'new' => $newGeneratedCount,
                'preserved' => $preservedCount,
            ])
            : __('Grade Editorial e Plano de Marketing recalculados com sucesso pela Sofi IA!');

        return redirect()->route('portal.marketing-plan.index')->with('status', $msg);
    }

    /**
     * Transforma um item da grade editorial em um projeto de produção audiovisual real.
     */
    public function convertToProduction(Request $request, \App\Models\MarketingPlanItem $item)
    {
        /** @var \App\Models\Cliente $cliente */
        $cliente = auth('cliente')->user();

        // Recupera o plano ignorando global scope para verificação precisa de autorização multi-tenant
        $plan = \App\Models\MarketingPlan::withoutGlobalScope('tenant')->find($item->marketing_plan_id);

        if (! $plan || $plan->cliente_id !== $cliente->id || $plan->tenant_id !== $cliente->tenant_id) {
            abort(403, 'Ação não autorizada.');
        }

        // Se já foi convertido para produção, redireciona diretamente
        if ($item->production_id && $item->production) {
            return redirect()->route('portal.productions.show', $item->production)
                ->with('status', __('Este vídeo já está em produção na esteira!'));
        }

        // Responsável da produtora que figura como criador da produção (de preferência um admin).
        // Nunca criamos usuário aqui: uma conta da equipe só nasce no cadastro da produtora.
        $tenantUser = $cliente->tenant->users()->orderByRaw("role = 'admin' desc")->orderBy('id')->first();
        if (! $tenantUser) {
            return redirect()->route('portal.marketing-plan.index')
                ->with('error', __('A produtora ainda não tem equipe cadastrada para receber este vídeo. Fale com a sua produtora.'));
        }

        // 1. Cria a Production no banco
        $production = \App\Models\Production::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'pack_purchase_id' => $plan->pack_purchase_id,
            'created_by' => $tenantUser->id,
            'title' => $item->topic,
            'description' => "Vídeo planejado pela Sofi na Grade Editorial (Semana {$item->week_number} - Funil " . strtoupper($item->funnel_stage) . "). " . ($item->seo_caption ?? ''),
            'status' => 'pending_review',
            'seo_keywords' => is_array($item->hashtags) ? implode(', ', $item->hashtags) : null,
        ]);

        // 2. Cria o Roteiro estruturado inicial
        $hook = $item->hook_text ?? 'Gancho de alto impacto para reter atenção nos primeiros 3 segundos.';
        $body = $item->seo_caption ?? 'Desenvolvimento da mensagem com base no nicho e diferenciais da marca.';
        $cta = 'Acesse nosso perfil e clique no link da bio para saber mais!';

        $fullScript = "🎬 [CENA 1 - GANCHO 0-3S]\n{$hook}\n\n💡 [CENA 2 - CORPO & DIFERENCIAIS]\n{$body}\n\n🎯 [CENA 3 - CHAMADA PARA AÇÃO]\n{$cta}";

        \App\Models\Roteiro::create([
            'tenant_id' => $cliente->tenant_id,
            'production_id' => $production->id,
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
}

