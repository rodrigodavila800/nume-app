<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantBankTransferSettingsController extends Controller
{
    /**
     * Exibe a tela de configuração de transferência bancária da produtora.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin() && $user->tenant, 403);

        $tenant = $user->tenant;

        $enabled = (bool) TenantSetting::get($tenant, 'bank_transfer_enabled', false);
        $iban = (string) TenantSetting::get($tenant, 'bank_transfer_iban', '');
        $bic = (string) TenantSetting::get($tenant, 'bank_transfer_bic', '');
        $accountHolder = (string) (TenantSetting::get($tenant, 'bank_transfer_account_holder')
            ?: TenantSetting::get($tenant, 'bank_transfer_beneficiary', $tenant->name));
        $bankName = (string) TenantSetting::get($tenant, 'bank_transfer_bank_name', '');
        $expirationDays = (int) (TenantSetting::get($tenant, 'bank_transfer_expiration_days')
            ?: TenantSetting::get($tenant, 'bank_transfer_expires_days', 3));
        $instructions = (string) TenantSetting::get($tenant, 'bank_transfer_instructions', '');

        return view('admin.settings.bank_transfer', [
            'tenant' => $tenant,
            'enabled' => $enabled,
            'iban' => $iban,
            'bic' => $bic,
            'accountHolder' => $accountHolder,
            'bankName' => $bankName,
            'expirationDays' => $expirationDays,
            'instructions' => $instructions,
        ]);
    }

    /**
     * Salva as configurações de transferência bancária da produtora.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isAdmin() && $user->tenant, 403);

        $tenant = $user->tenant;

        $validated = $request->validate([
            'bank_transfer_enabled' => ['nullable', 'boolean'],
            'bank_transfer_iban' => ['nullable', 'string', 'max:50'],
            'bank_transfer_bic' => ['nullable', 'string', 'max:20'],
            'bank_transfer_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_transfer_beneficiary' => ['nullable', 'string', 'max:255'],
            'bank_transfer_bank_name' => ['nullable', 'string', 'max:255'],
            'bank_transfer_expiration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'bank_transfer_expires_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'bank_transfer_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $accountHolder = trim($validated['bank_transfer_account_holder'] ?? $validated['bank_transfer_beneficiary'] ?? '');
        $expirationDays = (int) ($validated['bank_transfer_expiration_days'] ?? $validated['bank_transfer_expires_days'] ?? 3);

        TenantSetting::set($tenant, 'bank_transfer_enabled', $request->boolean('bank_transfer_enabled') ? '1' : '0');
        TenantSetting::set($tenant, 'bank_transfer_iban', strtoupper(str_replace(' ', '', $validated['bank_transfer_iban'] ?? '')));
        TenantSetting::set($tenant, 'bank_transfer_bic', strtoupper(trim($validated['bank_transfer_bic'] ?? '')));
        TenantSetting::set($tenant, 'bank_transfer_account_holder', $accountHolder);
        TenantSetting::set($tenant, 'bank_transfer_beneficiary', $accountHolder);
        TenantSetting::set($tenant, 'bank_transfer_bank_name', trim($validated['bank_transfer_bank_name'] ?? ''));
        TenantSetting::set($tenant, 'bank_transfer_expiration_days', (string) $expirationDays);
        TenantSetting::set($tenant, 'bank_transfer_expires_days', (string) $expirationDays);
        TenantSetting::set($tenant, 'bank_transfer_instructions', trim($validated['bank_transfer_instructions'] ?? ''));

        return redirect()->route('admin.settings.bank-transfer')
            ->with('status', __('Dados bancários para transferência atualizados com sucesso!'));
    }
}
