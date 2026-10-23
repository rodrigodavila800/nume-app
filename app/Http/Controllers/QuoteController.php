<?php

namespace App\Http\Controllers;

use App\Mail\QuoteProposalMail;
use App\Models\Cliente;
use App\Models\Quote;
use App\Services\Quotes\QuoteCalculatorService;
use App\Services\Quotes\QuotePdfService;
use App\Services\Quotes\SofiQuoteEstimatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(
        protected QuoteCalculatorService $calculator,
        protected QuotePdfService $pdfService,
        protected SofiQuoteEstimatorService $estimator
    ) {}

    /**
     * Listagem de orçamentos e métricas da produtora.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);

        $status = $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));

        $query = Quote::query();

        if ($status !== 'all' && ! empty($status)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('project_title', 'like', "%{$search}%");
            });
        }

        $allQuotes = Quote::query();
        $metrics = [
            'total_count' => (int) (clone $allQuotes)->count(),
            'draft_count' => (int) (clone $allQuotes)->where('status', 'draft')->count(),
            'sent_count' => (int) (clone $allQuotes)->where('status', 'sent')->count(),
            'accepted_count' => (int) (clone $allQuotes)->where('status', 'accepted')->count(),
            'accepted_volume' => (float) (clone $allQuotes)->where('status', 'accepted')->sum('total_amount'),
            'pipeline_volume' => (float) (clone $allQuotes)->whereIn('status', ['draft', 'sent'])->sum('total_amount'),
        ];

        $quotes = $query->latest('id')->paginate(12)->withQueryString();

        return view('quotes.index', compact('quotes', 'metrics', 'status', 'search'));
    }

    /**
     * Tela de criação / calculadora de orçamento.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Quote::class);

        $tenant = $request->user()->tenant;
        $defaults = $this->calculator->getDefaults($tenant);
        $clientes = Cliente::orderBy('name')->get();

        return view('quotes.create', compact('defaults', 'clientes'));
    }

    /**
     * Salva o orçamento no banco de dados.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Quote::class);

        $validated = $this->validateQuoteInput($request);

        $calc = $this->calculator->calculate($validated['inputs']);

        $validDays = max(1, (int) ($validated['inputs']['prazo'] ?? 10));
        $validUntil = now()->addDays($validDays);

        $quote = Quote::create([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'cliente_id' => $validated['cliente_id'] ?? null,
            'client_name' => $validated['client_name'] ?: 'Cliente',
            'client_contact' => $validated['client_contact'] ?? null,
            'client_email' => $validated['client_email'] ?? null,
            'project_title' => $validated['project_title'] ?: 'Vídeos para redes sociales',
            'language' => $validated['language'] ?? 'es',
            'request_text' => $validated['request_text'] ?? null,
            'inputs' => $calc['inputs_sanitized'],
            'calculation' => $calc,
            'ai_reading' => ! empty($validated['ai_reading']) ? json_decode($validated['ai_reading'], true) : null,
            'subtotal' => $calc['preco'],
            'margin_amount' => $calc['margem'],
            'adjustment_amount' => $calc['adicional'],
            'tax_rate' => $calc['inputs_sanitized']['iva'],
            'tax_amount' => $calc['iva'],
            'total_amount' => $calc['total_com_iva'],
            'deposit_amount' => $calc['sinal'],
            'balance_amount' => $calc['na_entrega'],
            'valid_until' => $validUntil,
            'status' => 'draft',
        ]);

        return redirect()->route('quotes.edit', $quote)
            ->with('success', __('Orçamento salvo com sucesso! Nº :num', ['num' => $quote->number]));
    }

    /**
     * Tela de edição e simulação da proposta.
     */
    public function edit(Quote $quote): View
    {
        $this->authorize('update', $quote);

        $clientes = Cliente::orderBy('name')->get();
        $defaults = $this->calculator->getDefaults($quote->tenant);

        return view('quotes.edit', compact('quote', 'clientes', 'defaults'));
    }

    /**
     * Atualiza os dados do orçamento.
     */
    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $validated = $this->validateQuoteInput($request);

        $calc = $this->calculator->calculate($validated['inputs']);

        $quote->update([
            'cliente_id' => $validated['cliente_id'] ?? null,
            'client_name' => $validated['client_name'] ?: 'Cliente',
            'client_contact' => $validated['client_contact'] ?? null,
            'client_email' => $validated['client_email'] ?? null,
            'project_title' => $validated['project_title'] ?: 'Vídeos para redes sociales',
            'language' => $validated['language'] ?? 'es',
            'request_text' => $validated['request_text'] ?? null,
            'inputs' => $calc['inputs_sanitized'],
            'calculation' => $calc,
            'ai_reading' => ! empty($validated['ai_reading']) ? json_decode($validated['ai_reading'], true) : $quote->ai_reading,
            'subtotal' => $calc['preco'],
            'margin_amount' => $calc['margem'],
            'adjustment_amount' => $calc['adicional'],
            'tax_rate' => $calc['inputs_sanitized']['iva'],
            'tax_amount' => $calc['iva'],
            'total_amount' => $calc['total_com_iva'],
            'deposit_amount' => $calc['sinal'],
            'balance_amount' => $calc['na_entrega'],
        ]);

        return redirect()->route('quotes.edit', $quote)
            ->with('success', __('Orçamento :num atualizado com sucesso!', ['num' => $quote->number]));
    }

    /**
     * Remove o orçamento.
     */
    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return redirect()->route('quotes.index')
            ->with('success', __('Orçamento removido com sucesso.'));
    }

    /**
     * Endpoint AJAX da Sofi para estimativa rápida pelo texto do pedido.
     */
    public function estimate(Request $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        $validated = $request->validate([
            'description' => 'required|string|max:20000',
        ]);

        $reading = $this->estimator->estimate($validated['description'], $request->user()->tenant);

        $defaults = $this->calculator->getDefaults($request->user()->tenant);
        $mergedInputs = array_merge($defaults, $reading['pedido']);
        $calc = $this->calculator->calculate($mergedInputs);

        return response()->json([
            'success' => true,
            'reading' => $reading,
            'calc' => $calc,
        ]);
    }

    /**
     * Download do PDF oficial em espanhol.
     */
    public function pdf(Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $pdf = $this->pdfService->renderPdf($quote);
        $cleanClient = preg_replace('/[^\p{L}\p{N}]+/u', '_', $quote->client_name ?: 'Cliente');

        return $pdf->download("Presupuesto_{$cleanClient}_{$quote->number}.pdf");
    }

    /**
     * Envia a proposta por e-mail com o PDF em anexo via SMTP.
     */
    public function send(Request $request, Quote $quote): JsonResponse|RedirectResponse
    {
        $this->authorize('send', $quote);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
        ]);

        try {
            Mail::to($validated['email'])->send(
                new QuoteProposalMail(
                    quote: $quote,
                    customSubject: $validated['subject'] ?? null,
                    customBody: $validated['message'] ?? null
                )
            );

            $quote->update([
                'status' => 'sent',
                'sent_at' => now(),
                'client_email' => $validated['email'],
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('Proposta enviada com sucesso para :email com o PDF anexo.', ['email' => $validated['email']]),
                ]);
            }

            return back()->with('success', __('Proposta enviada com sucesso para :email.', ['email' => $validated['email']]));
        } catch (\Throwable $e) {
            Log::error("QuoteController send error: " . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Falha ao enviar e-mail: :error', ['error' => $e->getMessage()]),
                ], 500);
            }

            return back()->with('error', __('Falha ao enviar e-mail: :error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Atualização rápida de status (ex: marcar como aceito/recusado).
     */
    public function updateStatus(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'accepted') {
            $updates['accepted_at'] = now();
        }

        $quote->update($updates);

        return back()->with('success', __('Status do orçamento atualizado para :status.', ['status' => ucfirst($validated['status'])]));
    }

    /**
     * Validação rigorosa dos campos de entrada.
     */
    protected function validateQuoteInput(Request $request): array
    {
        return $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'client_name' => 'required|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'project_title' => 'nullable|string|max:255',
            'language' => 'nullable|string|in:es,pt_BR,en',
            'request_text' => 'nullable|string|max:20000',
            'ai_reading' => 'nullable|string',

            'inputs' => 'required|array',
            'inputs.videos' => 'required|integer|min:1|max:500',
            'inputs.pre' => 'required|numeric|min:0|max:100',
            'inputs.gravacao' => 'required|numeric|min:0|max:100',
            'inputs.edicao' => 'required|numeric|min:0|max:200',
            'inputs.motion' => 'required|numeric|min:0|max:100',
            'inputs.filmmakers' => 'required|integer|min:1|max:50',
            'inputs.fm_n' => 'nullable|integer|min:4|max:50',
            'inputs.valor_hora' => 'required|numeric|min:0',
            'inputs.valor_hora_editor' => 'required|numeric|min:0',
            'inputs.drone' => 'nullable|boolean',
            'inputs.drone_valor' => 'nullable|numeric|min:0',
            'inputs.iva' => 'required|numeric|min:0|max:100',
            'inputs.margem' => 'nullable|numeric|min:0|max:100',
            'inputs.valor_unit' => 'nullable|numeric|min:0',
            'inputs.adicional' => 'nullable|numeric',
            'inputs.cambios' => 'required|integer|min:1|max:10',
            'inputs.prazo' => 'required|integer|min:1|max:365',
            'inputs.extras' => 'nullable|array',
            'inputs.extras.*.nome' => 'nullable|string|max:255',
            'inputs.extras.*.valor' => 'nullable|numeric|min:0',
        ]);
    }
}
