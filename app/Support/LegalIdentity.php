<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Billing\TenantFiscalSettings;

/**
 * Titular do site para as páginas legais (aviso legal, privacidade, cookies): os dados fiscais da
 * produtora oficial (`nume`), os mesmos das faturas. Os valores de exemplo que TenantFiscalSettings
 * usa quando o campo está vazio (CIF B12345678, endereço de exemplo) nunca aparecem como se fossem
 * reais: viram null e a página mostra "pendente".
 */
class LegalIdentity
{
    /** Valores de exemplo de TenantFiscalSettings / Setting (não são dados da empresa). */
    private const PLACEHOLDERS = ['B12345678', 'Calle Alfonso I, 12, Planta 3'];

    /**
     * @return array{company: string, cif: ?string, address: ?string, email: string}
     */
    public static function current(): array
    {
        $tenant = Tenant::where('slug', 'nume')->first() ?? Tenant::first();
        $fiscal = $tenant ? TenantFiscalSettings::for($tenant) : null;

        $clean = fn (?string $value): ?string => filled($value) && ! in_array(trim((string) $value), self::PLACEHOLDERS, true) ? trim((string) $value) : null;
        $street = $clean($fiscal?->companyAddress);

        return [
            'company' => (string) ($fiscal?->companyName ?: 'Nume Creative'),
            'cif' => $clean($fiscal?->companyCif),
            'address' => $street ? trim($street.', '.trim(($fiscal->companyPostalCode ?? '').' '.($fiscal->companyCity ?? '')), ', ') : null,
            'email' => (string) Setting::get('privacy_contact_email', 'numecreative.es@gmail.com'),
        ];
    }
}
