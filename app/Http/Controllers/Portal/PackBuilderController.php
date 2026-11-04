<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\SendQuoteProposalDocuments;
use App\Services\Quotes\PackBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Monte seu pacote" no portal: o cliente logado escolhe tipos de vídeo, quantidades e extras, e a
 * montagem vira proposta (PDF por e-mail) para aprovar e pagar. Catálogo e preço sempre do tenant
 * do cliente, recalculados no servidor; o que vem do navegador é só a escolha.
 */
class PackBuilderController extends Controller
{
    /** Montagem simulada na vitrine antes do cadastro ou login. */
    public const SESSION_KEY = 'pack_builder_selection';

    public function __construct(private readonly PackBuilderService $builder) {}

    public function show(Request $request): View
    {
        $cliente = $request->user('cliente');
        $selection = (array) $request->session()->get(self::SESSION_KEY, []);

        return view('portal.builder', [
            'catalog' => $this->builder->catalog($cliente->tenant),
            'selectedItems' => array_map('intval', (array) old('items', $selection['items'] ?? [])),
            'selectedAddons' => array_map('intval', (array) old('addons', $selection['addons'] ?? [])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array', 'max:50'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:'.PackBuilderService::MAX_PER_TYPE],
            'addons' => ['nullable', 'array', 'max:50'],
            'addons.*' => ['integer'],
        ]);

        $cliente = $request->user('cliente');
        $tenant = $cliente->tenant;
        $priced = $this->builder->price($tenant, $validated);

        if ($priced === null) {
            return back()->withInput()->with('error', __('Escolha pelo menos um vídeo para montar o seu pacote.'));
        }

        $quote = $this->builder->createQuote($tenant, $priced, [
            'responsible' => (string) $cliente->name,
            'company' => (string) ($cliente->company_name ?: $cliente->name),
            'email' => (string) $cliente->email,
        ], 'portal', $cliente, null, str_starts_with((string) ($cliente->locale ?? app()->getLocale()), 'es'));

        $request->session()->forget(self::SESSION_KEY);

        // PDF formal por e-mail (sem WhatsApp: o pedido veio do portal).
        if ($quote->wasRecentlyCreated) {
            SendQuoteProposalDocuments::dispatch($quote->id, $tenant->id);
        }

        return redirect()->route('quotes.public.show', $quote->approval_token)
            ->with('info', __('Pacote montado! Revise a proposta e escolha a forma de pagamento.'));
    }
}
