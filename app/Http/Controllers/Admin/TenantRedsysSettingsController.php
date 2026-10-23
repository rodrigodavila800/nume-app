<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantRedsysSettingsController extends Controller
{
    /**
     * Exibe a tela de configuração do TPV Redsys / Bizum da produtora.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isAdmin() && $user->tenant, 403);

        $tenant = $user->tenant;

        $enabled = (bool) TenantSetting::get($tenant, 'redsys_enabled', false);
        $bizumEnabled = (bool) TenantSetting::get($tenant, 'redsys_bizum_enabled', true);
        $cardEnabled = (bool) TenantSetting::get($tenant, 'redsys_card_enabled', true);
        $merchantCode = (string) TenantSetting::get($tenant, 'redsys_merchant_code', '');
        $terminal = (string) TenantSetting::get($tenant, 'redsys_terminal', '001');
        $environment = (string) TenantSetting::get($tenant, 'redsys_environment', 'test');
        $hasSecretKey = ! empty(TenantSetting::get($tenant, 'redsys_secret_key', ''));

        return view('admin.settings.redsys', [
            'tenant' => $tenant,
            'enabled' => $enabled,
            'bizumEnabled' => $bizumEnabled,
            'cardEnabled' => $cardEnabled,
            'merchantCode' => $merchantCode,
            'terminal' => $terminal,
            'environment' => $environment,
            'hasSecretKey' => $hasSecretKey,
        ]);
    }

    /**
     * Salva as credenciais e flags do TPV Redsys da produtora.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() && $user->tenant, 403);

        $tenant = $user->tenant;

        $validated = $request->validate([
            'redsys_enabled' => ['nullable', 'boolean'],
            'redsys_bizum_enabled' => ['nullable', 'boolean'],
            'redsys_card_enabled' => ['nullable', 'boolean'],
            'redsys_merchant_code' => ['nullable', 'string', 'max:50'],
            'redsys_terminal' => ['nullable', 'string', 'max:10'],
            'redsys_environment' => ['required', 'in:test,live'],
            'redsys_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        TenantSetting::set($tenant, 'redsys_enabled', $request->boolean('redsys_enabled') ? '1' : '0');
        TenantSetting::set($tenant, 'redsys_bizum_enabled', $request->boolean('redsys_bizum_enabled') ? '1' : '0');
        TenantSetting::set($tenant, 'redsys_card_enabled', $request->boolean('redsys_card_enabled') ? '1' : '0');
        TenantSetting::set($tenant, 'redsys_merchant_code', trim($validated['redsys_merchant_code'] ?? ''));
        TenantSetting::set($tenant, 'redsys_terminal', trim($validated['redsys_terminal'] ?? '001'));
        TenantSetting::set($tenant, 'redsys_environment', $validated['redsys_environment']);

        // Se uma nova chave for digitada, atualiza; se vier vazia, mantém a chave anterior intacta
        if (! empty($validated['redsys_secret_key'])) {
            TenantSetting::set($tenant, 'redsys_secret_key', trim($validated['redsys_secret_key']));
        }

        return redirect()->route('admin.settings.redsys')
            ->with('status', __('Credenciais do TPV Redsys atualizadas com sucesso!'));
    }
}
