<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackPurchase;
use App\Services\Billing\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceManagementController extends Controller
{
    /**
     * Listagem de faturas e painel fiscal para a produtora.
     */
    public function index(Request $request): View
    {
        $tenant = $request->user()->tenant;

        $query = $this->buildFilteredQuery($request, $tenant->id);

        // Clona a query para os somatórios fiscais antes da paginação
        $statsQuery = clone $query;
        $paidStatsQuery = (clone $statsQuery)->where('status', 'paid');

        $metrics = [
            'total_billed' => (float) $paidStatsQuery->sum('total_amount'),
            'total_base' => (float) $paidStatsQuery->sum('subtotal'),
            'total_iva' => (float) $paidStatsQuery->sum('tax_amount'),
            'total_irpf' => (float) $paidStatsQuery->sum('irpf_amount'),
            'total_pending_balance' => (float) (clone $statsQuery)->where('billing_mode', 'split_50_50')->where('balance_status', 'pending')->sum('balance_amount'),
            'count_escrow_pending' => (int) (clone $statsQuery)->where('billing_mode', 'split_50_50')->where('balance_status', 'pending')->count(),
            'count_paid' => (int) $paidStatsQuery->count(),
            'count_total' => (int) $statsQuery->count(),
        ];

        $invoices = $query->latest('id')->paginate(15)->withQueryString();

        return view('admin.invoices.index', [
            'tenant' => $tenant,
            'invoices' => $invoices,
            'metrics' => $metrics,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status', 'paid'),
                'billing_mode' => $request->input('billing_mode'),
                'gateway' => $request->input('gateway'),
                'year' => $request->input('year', date('Y')),
                'quarter' => $request->input('quarter'),
                'month' => $request->input('month'),
            ],
        ]);
    }

    /**
     * Exportação de faturas em formato CSV/Excel otimizado para Gestorías e AEAT (Espanha).
     */
    public function export(Request $request): StreamedResponse
    {
        $tenant = $request->user()->tenant;
        $query = $this->buildFilteredQuery($request, $tenant->id)->latest('paid_at');

        $filename = sprintf(
            'Facturas-Gestoria-%s-%s.csv',
            $tenant->slug ?? 'tenant',
            now()->format('Ymd-His')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM para garantir correta abertura no Microsoft Excel / LibreOffice
            fputs($handle, "\xEF\xBB\xBF");

            // Cabeçalho compatível com softwares de assessoria contábil espanhola (A3, Sage, Contasol)
            fputcsv($handle, [
                'Fecha_Factura',
                'Numero_Factura',
                'Serie',
                'NIF_CIF_Cliente',
                'Razon_Social_Cliente',
                'Concepto_Servicio',
                'Modalidad_Cobro',
                'Base_Imponible_EUR',
                'Tipo_IVA_PCT',
                'Cuota_IVA_EUR',
                'Tipo_IRPF_PCT',
                'Retencion_IRPF_EUR',
                'Total_Factura_EUR',
                'Deposito_Cobrado_EUR',
                'Saldo_Entrega_EUR',
                'Estado_Saldo',
                'Metodo_Pago',
                'Referencia_Pasarela',
                'Estado',
            ], ';', '"', '\\');

            $query->chunk(100, function ($purchases) use ($handle) {
                foreach ($purchases as $purchase) {
                    $date = $purchase->paid_at ?? $purchase->invoice_generated_at ?? $purchase->created_at;
                    $concept = $purchase->pack?->name ?? 'Paquete Audiovisual';
                    if (!empty($purchase->addons_purchased)) {
                        $concept .= ' (+ ' . implode(', ', (array) $purchase->addons_purchased) . ')';
                    }

                    $modeLabel = match($purchase->billing_mode) {
                        'split_50_50' => '50/50 Split (Custodia)',
                        'pilot_entry' => 'Piloto de Entrada',
                        default => '100% Anticipado',
                    };

                    fputcsv($handle, [
                        $date ? Carbon::parse($date)->format('d/m/Y') : '',
                        $purchase->invoice_number ?? "REC-{$purchase->id}",
                        $purchase->invoice_series ?? 'NUM',
                        $purchase->billing_tax_id ?? 'N/D',
                        $purchase->billing_legal_name ?: ($purchase->cliente?->name ?? 'Cliente Final'),
                        $concept,
                        $modeLabel,
                        number_format((float) ($purchase->subtotal ?? $purchase->amount), 2, ',', ''),
                        number_format((float) ($purchase->tax_rate ?? 21.0), 2, ',', ''),
                        number_format((float) ($purchase->tax_amount ?? 0.0), 2, ',', ''),
                        number_format((float) ($purchase->irpf_rate ?? 0.0), 2, ',', ''),
                        number_format((float) ($purchase->irpf_amount ?? 0.0), 2, ',', ''),
                        number_format((float) ($purchase->total_amount ?? $purchase->amount), 2, ',', ''),
                        number_format((float) ($purchase->deposit_amount ?: $purchase->amount), 2, ',', ''),
                        number_format((float) ($purchase->balance_amount ?: 0.0), 2, ',', ''),
                        strtoupper($purchase->balance_status ?? 'N/A'),
                        strtoupper($purchase->payment_gateway ?? 'STRIPE'),
                        $purchase->gateway_order_reference ?? $purchase->stripe_checkout_session_id ?? '-',
                        strtoupper($purchase->status),
                    ], ';', '"', '\\');
                }
            });

            fclose($handle);
        }, Response::HTTP_OK, $headers);
    }

    /**
     * Download do PDF oficial da fatura pelo painel do administrador.
     */
    public function download(
        Request $request,
        PackPurchase $purchase,
        InvoiceService $invoiceService
    ): \Symfony\Component\HttpFoundation\Response {
        $tenant = $request->user()->tenant;

        abort_unless($purchase->tenant_id === $tenant->id, 403, 'Acesso negado.');
        abort_unless($purchase->isPaid(), 404, 'Factura no emitida para pedidos no liquidados.');

        $pdf = $invoiceService->renderPdf($purchase);

        return $pdf->download("Factura-{$purchase->invoice_number}.pdf");
    }

    /**
     * Constrói a query com filtros tributários e de busca.
     */
    protected function buildFilteredQuery(Request $request, int $tenantId)
    {
        $query = PackPurchase::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->with(['cliente', 'pack']);

        // Filtro por Status
        $status = $request->input('status', 'paid');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Filtro por Modalidade Comercial (50/50, Custódia, Assinatura, Upfront)
        if ($billingMode = $request->input('billing_mode')) {
            if ($billingMode === 'pending_escrow') {
                $query->where('billing_mode', 'split_50_50')->where('balance_status', 'pending');
            } elseif ($billingMode === 'paid_escrow') {
                $query->where('billing_mode', 'split_50_50')->where('balance_status', 'paid');
            } elseif ($billingMode === 'split_50_50') {
                $query->where('billing_mode', 'split_50_50');
            } elseif ($billingMode === 'full_upfront') {
                $query->where(function ($q) {
                    $q->where('billing_mode', 'full_upfront')->orWhereNull('billing_mode');
                });
            } elseif ($billingMode === 'subscription') {
                $query->where('billing_type', 'subscription');
            }
        }

        // Filtro por Gateway
        if ($gateway = $request->input('gateway')) {
            $query->where('payment_gateway', $gateway);
        }

        // Filtro por Ano
        if ($year = $request->input('year')) {
            $query->whereYear('paid_at', $year);
        }

        // Filtro por Trimestre (Trimestres Fiscais da AEAT: 1T, 2T, 3T, 4T)
        if ($quarter = $request->input('quarter')) {
            match ((string) $quarter) {
                '1' => $query->whereMonth('paid_at', '>=', 1)->whereMonth('paid_at', '<=', 3),
                '2' => $query->whereMonth('paid_at', '>=', 4)->whereMonth('paid_at', '<=', 6),
                '3' => $query->whereMonth('paid_at', '>=', 7)->whereMonth('paid_at', '<=', 9),
                '4' => $query->whereMonth('paid_at', '>=', 10)->whereMonth('paid_at', '<=', 12),
                default => null,
            };
        }

        // Filtro por Mês específico
        if ($month = $request->input('month')) {
            $query->whereMonth('paid_at', $month);
        }

        // Busca textual por Nº de fatura, NIF ou Nome/Email
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('billing_tax_id', 'like', "%{$search}%")
                    ->orWhere('billing_legal_name', 'like', "%{$search}%")
                    ->orWhere('gateway_order_reference', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
