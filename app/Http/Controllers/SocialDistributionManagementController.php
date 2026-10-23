<?php

namespace App\Http\Controllers;

use App\Contracts\SocialPublisherContract;
use App\Enums\PlataformaAlvo;
use App\Models\VideoPublication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialDistributionManagementController extends Controller
{
    public function __construct(private readonly SocialPublisherContract $publisher)
    {
    }

    /**
     * Painel da Produtora: Monitoramento e Auditoria de Distribuição Social.
     */
    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $query = VideoPublication::with(['cliente', 'video.production'])
            ->where('tenant_id', $tenantId);

        // Filtro por Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por Plataforma
        if ($request->filled('platform')) {
            $query->whereJsonContains('plataformas', $request->platform);
        }

        // Busca por Título ou Nome do Cliente
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', $search)
                    ->orWhereHas('cliente', fn ($c) => $c->where('name', 'like', $search)->orWhere('company_name', 'like', $search));
            });
        }

        $publications = $query->latest()->paginate(15)->withQueryString();

        // Métricas rápidas
        $stats = [
            'total' => VideoPublication::where('tenant_id', $tenantId)->count(),
            'publicados' => VideoPublication::where('tenant_id', $tenantId)->where('status', 'publicado')->count(),
            'agendados' => VideoPublication::where('tenant_id', $tenantId)->where('status', 'agendado')->count(),
            'processando' => VideoPublication::where('tenant_id', $tenantId)->whereIn('status', ['processando', 'pendente'])->count(),
            'falhas' => VideoPublication::where('tenant_id', $tenantId)->where('status', 'falhou')->count(),
        ];

        $platforms = PlataformaAlvo::cases();

        return view('distributions.index', compact('publications', 'stats', 'platforms'));
    }

    /**
     * Força a atualização do status da publicação com o provedor social.
     */
    public function refresh(VideoPublication $videoPublication): RedirectResponse
    {
        abort_unless($videoPublication->tenant_id === auth()->user()->tenant_id, 403);

        $refreshed = $this->publisher->refreshStatus($videoPublication);

        return redirect()->back()->with('success', __('Status da publicação atualizado: :status', [
            'status' => ucfirst($refreshed->status),
        ]));
    }
}
