<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\StripeClient;
use Exception;

class GatewaySettingController extends Controller
{
    /**
     * Exibir tela de configuração segura de gateways de pagamento.
     */
    public function index(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $stripeKey = Setting::get('stripe_key', config('services.stripe.key'));
        $stripeSecret = Setting::get('stripe_secret', config('services.stripe.secret'));
        $stripeWebhook = Setting::get('stripe_webhook_secret', config('services.stripe.webhook_secret'));
        $stripePriceId = Setting::get('stripe_price_id', config('services.stripe.price_id'));
        $stripeFee = Setting::get('stripe_platform_fee_percent', config('services.stripe.platform_fee_percent', 10));
        $stripeMode = Setting::get('stripe_mode', 'test');
        $stripeEnabled = (bool) Setting::get('stripe_enabled', true);

        // BBVA Redsys Settings
        $redsysEnabled = (bool) Setting::get('redsys_enabled', config('services.redsys.enabled', true));
        $redsysBizumEnabled = (bool) Setting::get('redsys_bizum_enabled', true);
        $redsysCardEnabled = (bool) Setting::get('redsys_card_enabled', true);
        $redsysEnvironment = Setting::get('redsys_environment', config('services.redsys.environment', 'test'));
        $redsysMerchantCode = Setting::get('redsys_merchant_code', config('services.redsys.merchant_code', '999008881'));
        $redsysTerminal = Setting::get('redsys_terminal', config('services.redsys.terminal', '001'));
        $redsysSecretKey = Setting::get('redsys_secret_key', config('services.redsys.secret_key', ''));

        // Fiscal & Invoice Settings
        $fiscalRegime = Setting::get('fiscal_regime', 'autonomo');
        $fiscalIrpfRate = Setting::get('fiscal_irpf_rate', 15.00);
        $taxDefaultIvaRate = Setting::get('tax_default_iva_rate', 21.00);
        $fiscalCompanyName = Setting::get('fiscal_company_name', 'Nume Creative S.L.');
        $fiscalCompanyCif = Setting::get('fiscal_company_cif', 'B12345678');
        $fiscalInvoiceSeries = Setting::get('fiscal_invoice_series', 'NUM-' . date('Y') . '-');
        $fiscalCompanyAddress = Setting::get('fiscal_company_address', 'Calle Alfonso I, 12, Planta 3');
        $fiscalCompanyPostalCode = Setting::get('fiscal_company_postal_code', '50003');
        $fiscalCompanyCity = Setting::get('fiscal_company_city', 'Zaragoza');
        $fiscalCompanyEmail = Setting::get('fiscal_company_email', 'facturacion@numecreative.es');

        // Google OAuth Settings
        $googleClientId = Setting::get('google_client_id', config('services.google.client_id'));
        $googleClientSecret = Setting::get('google_client_secret', config('services.google.client_secret'));

        // Teste de conexão com a API do Stripe
        $stripeStatus = [
            'connected' => false,
            'message' => 'Nenhuma chave configurada.',
            'account_id' => null,
        ];

        if (! empty($stripeSecret)) {
            try {
                $stripe = new StripeClient($stripeSecret);
                // Testa listagem básica de balance ou conta
                $balance = $stripe->balance->retrieve();
                $stripeStatus['connected'] = true;
                $stripeStatus['message'] = 'Conectado e validado com sucesso pela API do Stripe!';
            } catch (Exception $e) {
                $stripeStatus['connected'] = false;
                $stripeStatus['message'] = 'Erro ao autenticar no Stripe: ' . $e->getMessage();
            }
        }

        return view('admin.settings.gateways', compact(
            'stripeKey',
            'stripeSecret',
            'stripeWebhook',
            'stripePriceId',
            'stripeFee',
            'stripeMode',
            'stripeEnabled',
            'stripeStatus',
            'redsysEnabled',
            'redsysBizumEnabled',
            'redsysCardEnabled',
            'redsysEnvironment',
            'redsysMerchantCode',
            'redsysTerminal',
            'redsysSecretKey',
            'fiscalRegime',
            'fiscalIrpfRate',
            'taxDefaultIvaRate',
            'fiscalCompanyName',
            'fiscalCompanyCif',
            'fiscalInvoiceSeries',
            'fiscalCompanyAddress',
            'fiscalCompanyPostalCode',
            'fiscalCompanyCity',
            'fiscalCompanyEmail',
            'googleClientId',
            'googleClientSecret'
        ));
    }

    /**
     * Salvar configurações com criptografia AES-256 no banco de dados.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $validated = $request->validate([
            // Stripe
            'stripe_enabled' => 'nullable|boolean',
            'stripe_mode' => 'sometimes|required|in:test,live',
            'stripe_key' => 'nullable|string|max:255',
            'stripe_secret' => 'nullable|string|max:255',
            'stripe_webhook_secret' => 'nullable|string|max:255',
            'stripe_price_id' => 'nullable|string|max:255',
            'stripe_platform_fee_percent' => 'sometimes|required|numeric|min:0|max:100',

            // BBVA Redsys
            'redsys_enabled' => 'nullable|boolean',
            'redsys_bizum_enabled' => 'nullable|boolean',
            'redsys_card_enabled' => 'nullable|boolean',
            'redsys_environment' => 'sometimes|required|in:test,live',
            'redsys_merchant_code' => 'nullable|string|max:20',
            'redsys_terminal' => 'nullable|string|max:10',
            'redsys_secret_key' => 'nullable|string|max:255',

            // Fiscal
            'fiscal_regime' => 'sometimes|required|in:autonomo,empresa_sl',
            'fiscal_irpf_rate' => 'sometimes|required|numeric|min:0|max:100',
            'tax_default_iva_rate' => 'sometimes|required|numeric|min:0|max:100',
            'fiscal_company_name' => 'nullable|string|max:255',
            'fiscal_company_cif' => 'nullable|string|max:50',
            'fiscal_invoice_series' => 'nullable|string|max:30',
            'fiscal_company_address' => 'nullable|string|max:255',
            'fiscal_company_postal_code' => 'nullable|string|max:20',
            'fiscal_company_city' => 'nullable|string|max:100',
            'fiscal_company_email' => 'nullable|email|max:255',

            // Google OAuth
            'google_client_id' => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string|max:255',
        ]);

        // Stripe
        if ($request->has('stripe_enabled') || isset($validated['stripe_mode'])) {
            Setting::set('stripe_enabled', $request->has('stripe_enabled') ? '1' : '0', 'gateways', false, 'Habilitar pagamentos via Stripe');
        }
        if (isset($validated['stripe_mode'])) {
            Setting::set('stripe_mode', $validated['stripe_mode'], 'gateways', false, 'Modo do Stripe (test/live)');
        }
        if (isset($validated['stripe_platform_fee_percent'])) {
            Setting::set('stripe_platform_fee_percent', $validated['stripe_platform_fee_percent'], 'gateways', false, 'Taxa percentual da plataforma');
        }

        if ($request->filled('stripe_key')) {
            Setting::set('stripe_key', $validated['stripe_key'], 'gateways', false, 'Publishable Key pública');
        }

        if ($request->filled('stripe_secret')) {
            Setting::set('stripe_secret', $validated['stripe_secret'], 'gateways', true, 'Secret Key (criptografada)');
        }

        if ($request->filled('stripe_webhook_secret')) {
            Setting::set('stripe_webhook_secret', $validated['stripe_webhook_secret'], 'gateways', true, 'Webhook Secret (criptografada)');
        }

        if ($request->filled('stripe_price_id')) {
            Setting::set('stripe_price_id', $validated['stripe_price_id'], 'gateways', false, 'Stripe Price ID para assinaturas');
        }

        // BBVA Redsys
        if ($request->has('redsys_enabled') || isset($validated['redsys_environment'])) {
            Setting::set('redsys_enabled', $request->has('redsys_enabled') ? '1' : '0', 'gateways', false, 'Habilitar TPV Virtual BBVA / Redsys');
            Setting::set('redsys_bizum_enabled', $request->has('redsys_bizum_enabled') ? '1' : '0', 'gateways', false, 'Habilitar pagamentos via Bizum');
            Setting::set('redsys_card_enabled', $request->has('redsys_card_enabled') ? '1' : '0', 'gateways', false, 'Habilitar pagamentos via Cartão Redsys');
        }
        if (isset($validated['redsys_environment'])) {
            Setting::set('redsys_environment', $validated['redsys_environment'], 'gateways', false, 'Ambiente Redsys (test/live)');
        }

        if ($request->filled('redsys_merchant_code')) {
            Setting::set('redsys_merchant_code', $validated['redsys_merchant_code'], 'gateways', false, 'FUC / Código de Comercio BBVA');
        }

        if ($request->filled('redsys_terminal')) {
            Setting::set('redsys_terminal', $validated['redsys_terminal'], 'gateways', false, 'Terminal Redsys');
        }

        if ($request->filled('redsys_secret_key')) {
            Setting::set('redsys_secret_key', $validated['redsys_secret_key'], 'gateways', true, 'Chave Secreta SHA-256 Redsys (criptografada)');
        }

        // Fiscal & Impostos
        if (isset($validated['fiscal_regime'])) {
            Setting::set('fiscal_regime', $validated['fiscal_regime'], 'fiscal', false, 'Regime fiscal da produtora (autonomo / empresa_sl)');
        }
        if (isset($validated['fiscal_irpf_rate'])) {
            Setting::set('fiscal_irpf_rate', $validated['fiscal_irpf_rate'], 'fiscal', false, 'Alíquota de retenção IRPF (%)');
        }
        if (isset($validated['tax_default_iva_rate'])) {
            Setting::set('tax_default_iva_rate', $validated['tax_default_iva_rate'], 'fiscal', false, 'Alíquota padrão de IVA (%)');
        }
        if ($request->has('fiscal_company_name')) {
            Setting::set('fiscal_company_name', $validated['fiscal_company_name'] ?? '', 'fiscal', false, 'Razão Social / Nome do Titular');
        }
        if ($request->has('fiscal_company_cif')) {
            Setting::set('fiscal_company_cif', $validated['fiscal_company_cif'] ?? '', 'fiscal', false, 'CIF / NIF Fiscal');
        }
        if ($request->has('fiscal_invoice_series')) {
            Setting::set('fiscal_invoice_series', $validated['fiscal_invoice_series'] ?? '', 'fiscal', false, 'Série de faturamento');
        }
        if ($request->has('fiscal_company_address')) {
            Setting::set('fiscal_company_address', $validated['fiscal_company_address'] ?? '', 'fiscal', false, 'Domicílio fiscal');
        }
        if ($request->has('fiscal_company_postal_code')) {
            Setting::set('fiscal_company_postal_code', $validated['fiscal_company_postal_code'] ?? '', 'fiscal', false, 'Código postal fiscal');
        }
        if ($request->has('fiscal_company_city')) {
            Setting::set('fiscal_company_city', $validated['fiscal_company_city'] ?? '', 'fiscal', false, 'Cidade da sede fiscal');
        }
        if ($request->has('fiscal_company_email')) {
            Setting::set('fiscal_company_email', $validated['fiscal_company_email'] ?? '', 'fiscal', false, 'E-mail de faturamento oficial');
        }

        // Google OAuth
        if ($request->filled('google_client_id')) {
            Setting::set('google_client_id', $validated['google_client_id'], 'oauth', false, 'Google Client ID');
        }
        if ($request->filled('google_client_secret')) {
            Setting::set('google_client_secret', $validated['google_client_secret'], 'oauth', true, 'Google Client Secret (criptografada)');
        }

        // Recarrega no runtime do Laravel
        Setting::loadIntoConfig();

        return redirect()->route('admin.settings.gateways')
            ->with('success', 'Configurações de pagamento e dados fiscais salvas com sucesso!');
    }
}
