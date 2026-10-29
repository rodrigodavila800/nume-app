<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Roteiro;
use App\Models\SupportTicket;
use App\Models\Video;
use App\Services\Notification\SofiProductionNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(
        protected SofiProductionNotificationService $notificationService
    ) {}

    /**
     * Listar todas as productions do tenant autenticado
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Production::class);

        $viewMode = $request->input('view', 'list'); // 'list' ou 'kanban'
        $clienteId = $request->input('cliente_id');

        $query = Production::with(['creator', 'cliente', 'roteiros', 'videos'])
            ->withCount(['roteiros', 'videos']);

        if ($clienteId) {
            $query->where('cliente_id', $clienteId);
        }

        $productions = (clone $query)->orderBy('created_at', 'desc')->paginate(15);

        $kanbanColumns = [];
        if ($viewMode === 'kanban') {
            $allProductions = (clone $query)->orderBy('created_at', 'desc')->get();
            $kanbanColumns = [
                'draft' => [
                    'label' => __('Planejamento & Rascunho'),
                    'badge' => 'bg-zinc-800 text-zinc-300 border-zinc-700',
                    'icon' => '01',
                    'status_target' => 'draft',
                    'items' => $allProductions->whereIn('status', ['draft', 'planejamento']),
                ],
                'pending_review' => [
                    'label' => __('Aguardando Revisão'),
                    'badge' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                    'icon' => '02',
                    'status_target' => 'pending_review',
                    'items' => $allProductions->whereIn('status', ['pending_review', 'submitted', 'roteirizacao']),
                ],
                'in_production' => [
                    'label' => __('Em Gravação & Edição'),
                    'badge' => 'bg-orange-500/20 text-orange-300 border-orange-500/30',
                    'icon' => '03',
                    'status_target' => 'in_production',
                    'items' => $allProductions->whereIn('status', ['in_production', 'in_progress', 'gravacao']),
                ],
                'approved' => [
                    'label' => __('Aprovado / Pronto'),
                    'badge' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                    'icon' => '04',
                    'status_target' => 'approved',
                    'items' => $allProductions->whereIn('status', ['approved', 'ready', 'completed', 'entregue']),
                ],
                'published' => [
                    'label' => __('Publicado nas Redes'),
                    'badge' => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                    'icon' => '05',
                    'status_target' => 'published',
                    'items' => $allProductions->where('status', 'published'),
                ],
            ];
        }

        $stats = [
            'total' => Production::count(),
            'approved' => Production::whereIn('status', ['approved', 'ready'])->count(),
            'in_production' => Production::where('status', 'in_production')->count(),
            'published' => Production::where('status', 'published')->count(),
            'total_clientes' => Cliente::count(),
            'total_roteiros' => Roteiro::count(),
            'total_videos' => Video::count(),
            'quote_requests' => Quote::where('status', 'requested')->count(),
            'plan_adjustments' => MarketingPlan::where('status', MarketingPlan::STATUS_ADJUSTMENT)->count(),
            'roteiros_rejected' => Roteiro::where('status', 'rejected')->count(),
            'open_tickets' => SupportTicket::whereIn('status', SupportTicket::OPEN_STATUSES)->count(),
        ];

        $clientes = Cliente::orderBy('name')->get();

        return view('productions.index', compact('productions', 'stats', 'viewMode', 'kanbanColumns', 'clientes', 'clienteId'));
    }

    /**
     * Exibir formulário para criar nova production
     */
    public function create(): View
    {
        $this->authorize('create', Production::class);

        $clientes = Cliente::orderBy('name')->get();

        return view('productions.create', compact('clientes'));
    }

    /**
     * Armazenar nova production
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Production::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'cliente_id' => 'nullable|exists:clientes,id',
            'target_publication_date' => 'nullable|date|after:today',
            'seo_keywords' => 'nullable|string|max:500',
        ]);

        $production = Production::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('productions.show', $production)
            ->with('success', 'Produção criada com sucesso!');
    }

    /**
     * Exibir detalhes da production com roteiros e vídeos
     */
    public function show(Production $production): View
    {
        $this->authorize('view', $production);
        $production->load(['cliente', 'creator', 'roteiros']);

        $roteiros = $production->roteiros()->paginate(10, ['*'], 'roteiros_page');
        $videos = $production->videos()->paginate(10, ['*'], 'videos_page');

        $whatsappStatusMessage = $this->notificationService->getWhatsAppMessage($production, $production->status);
        $whatsappStatusUrl = $this->notificationService->getWhatsAppShareUrl($production, $production->status);

        return view('productions.show', compact('production', 'roteiros', 'videos', 'whatsappStatusMessage', 'whatsappStatusUrl'));
    }

    /**
     * Exibir formulário para editar production
     */
    public function edit(Production $production): View
    {
        $this->authorize('update', $production);

        return view('productions.edit', compact('production'));
    }

    /**
     * Atualizar production
     */
    public function update(Request $request, Production $production): RedirectResponse
    {
        $this->authorize('update', $production);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_publication_date' => 'nullable|date|after:today',
            'seo_keywords' => 'nullable|string|max:500',
            'status' => 'in:draft,submitted,in_production,pending_review,ready,approved,published,archived',
        ]);

        $production->update($validated);

        return redirect()->route('productions.show', $production)
            ->with('success', 'Produção atualizada com sucesso!');
    }

    /**
     * Deletar production
     */
    public function destroy(Production $production): RedirectResponse
    {
        $this->authorize('delete', $production);

        $production->delete();

        return redirect()->route('productions.index')
            ->with('success', 'Produção removida com sucesso!');
    }

    /**
     * Marcar production como pronta para publicação
     */
    public function markAsApproved(Production $production): RedirectResponse
    {
        $this->authorize('update', $production);

        if (! $production->isReadyForProduction()) {
            return redirect()->back()
                ->with('error', 'Production precisa ter um roteiro aprovado para ser marcada como pronta.');
        }

        $production->update(['status' => 'approved']);

        return redirect()->back()
            ->with('success', 'Production marcada como aprovada!');
    }

    /**
     * Atualizar status da produção via pipeline Kanban ou ação rápida
     */
    public function updateStatus(Request $request, Production $production): RedirectResponse
    {
        $this->authorize('update', $production);

        $validated = $request->validate([
            'status' => 'required|in:draft,submitted,in_production,pending_review,ready,approved,published,archived',
            'notify_cliente' => 'nullable|boolean',
        ]);

        $oldStatus = $production->status;
        $newStatus = $validated['status'];
        $production->update(['status' => $newStatus]);

        if ($request->boolean('notify_cliente') && $oldStatus !== $newStatus) {
            $this->notificationService->sendProductionStatusEmail($production, $newStatus, $oldStatus);
        }

        // Se veio com parâmetro de retorno explícito ou do kanban, redireciona graciosamente
        if ($request->header('referer') && ! str_ends_with($request->header('referer'), '/status')) {
            return redirect()->back()->with('success', __('Status da produção atualizado com sucesso.'));
        }

        return redirect()->route('productions.index', ['view' => 'pipeline'])
            ->with('success', __('Status da produção atualizado com sucesso.'));
    }

    /**
     * Disparar notificação de status da produção (E-mail e/ou WhatsApp) para o cliente
     */
    public function notifyCliente(Request $request, Production $production): RedirectResponse
    {
        $this->authorize('update', $production);

        $channel = $request->input('channel', 'email'); // 'email', 'all'
        $cliente = $production->cliente;

        if (! $cliente) {
            return redirect()->back()->with('error', __('Esta produção não possui um cliente associado para notificação.'));
        }

        $emailSent = false;
        if (in_array($channel, ['email', 'all'])) {
            $emailSent = $this->notificationService->sendProductionStatusEmail($production, $production->status);
        }

        $whatsAppUrl = $this->notificationService->getWhatsAppShareUrl($production, $production->status);

        if ($channel === 'email') {
            if ($emailSent) {
                return redirect()->back()->with('success', __('E-mail de status (:status) enviado para :email!', ['status' => ucfirst(str_replace('_', ' ', $production->status)), 'email' => $cliente->email]));
            } else {
                return redirect()->back()->with('error', __('Não foi possível enviar o e-mail de notificação. Verifique o endereço de e-mail do cliente.'));
            }
        }

        return redirect()->back()
            ->with('success', __('Notificação de status processada com sucesso!'))
            ->with('whatsapp_redirect_url', $whatsAppUrl);
    }
}
