<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\TenantFiscalSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantFiscalSettingsController extends Controller
{
    /**
     * Exibe a tela de configuração fiscal da produtora.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() && $user->tenant, 403);

        $fiscal = TenantFiscalSettings::for($user->tenant);

        return view('admin.settings.fiscal', [
            'tenant' => $user->tenant,
            'fiscal' => $fiscal,
        ]);
    }

    /**
     * Salva as configurações fiscais da produtora.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() && $user->tenant, 403);

        $validated = $request->validate([
            'fiscal_regime' => ['required', 'in:autonomo,empresa_sl'],
            'fiscal_irpf_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_default_iva_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'fiscal_company_name' => ['nullable', 'string', 'max:255'],
            'fiscal_company_cif' => ['nullable', 'string', 'max:50'],
            'fiscal_invoice_series' => ['nullable', 'string', 'max:30'],
            'fiscal_company_address' => ['nullable', 'string', 'max:255'],
            'fiscal_company_postal_code' => ['nullable', 'string', 'max:20'],
            'fiscal_company_city' => ['nullable', 'string', 'max:100'],
            'fiscal_company_email' => ['nullable', 'email', 'max:255'],
        ]);

        TenantFiscalSettings::for($user->tenant)->update($validated);

        return redirect()->route('admin.settings.fiscal')
            ->with('status', __('Configurações fiscais da produtora atualizadas com sucesso!'));
    }
}
