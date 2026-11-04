<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Portal\PackBuilderController;
use App\Models\Tenant;
use App\Services\Quotes\PackBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Monte seu pacote" na vitrine pública: aqui só se simula. Ao contratar, a montagem fica guardada
 * na sessão (ids e quantidades, nada de preço) e o visitante vai para o cadastro, ou para o login se
 * já tiver conta; no portal ela volta preenchida para confirmar.
 */
class StorefrontBuilderController extends Controller
{
    public function __construct(private readonly PackBuilderService $builder) {}

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->isActive(), 404);

        $validated = $request->validate([
            'items' => ['nullable', 'array', 'max:50'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:'.PackBuilderService::MAX_PER_TYPE],
            'addons' => ['nullable', 'array', 'max:50'],
            'addons.*' => ['integer'],
        ]);

        $back = $tenant->slug === 'nume' ? route('marketplace.index') : route('storefront.show', $tenant->slug);

        if ($this->builder->price($tenant, $validated) === null) {
            return redirect()->to($back.'#monte-seu-pacote')->withInput()
                ->with('builder_error', __('Escolha pelo menos um vídeo para montar o seu pacote.'));
        }

        $request->session()->put(PackBuilderController::SESSION_KEY, [
            'tenant_id' => $tenant->id,
            'items' => array_map('intval', (array) ($validated['items'] ?? [])),
            'addons' => array_map('intval', (array) ($validated['addons'] ?? [])),
        ]);

        $cliente = $request->user('cliente');
        if ($cliente && (int) $cliente->tenant_id === (int) $tenant->id) {
            return redirect()->route('portal.builder');
        }

        // Login devolve para o montador (url.intended); o cadastro olha a sessão da montagem.
        $request->session()->put('url.intended', route('portal.builder'));

        return redirect()->route('portal.register')
            ->with('status', __('Quase lá! Crie a sua conta (ou entre, se já tiver) para confirmar o pacote que você montou.'));
    }
}
