<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class GatewayAndFiscalSettingsSeeder extends Seeder
{
    /**
     * Popula as credenciais oficiais de Sandbox do BBVA Redsys e parâmetros fiscais de Zaragoza.
     */
    public function run(): void
    {
        // 1. BBVA / Redsys TPV Virtual & Bizum (Sandbox Oficial Redsys)
        Setting::set('redsys_enabled', '1', 'payment', false, 'Habilita pagamentos via BBVA Redsys');
        Setting::set('redsys_environment', 'test', 'payment', false, 'Ambiente Redsys: test (sandbox) ou live');
        Setting::set('redsys_merchant_code', '999008881', 'payment', false, 'FUC Oficial de Pruebas Redsys');
        Setting::set('redsys_terminal', '1', 'payment', false, 'Número de Terminal Redsys');
        Setting::set('redsys_secret_key', 'sq7HjrUqKpRHAJsiuz6100BxqEquecNT', 'payment', true, 'Clave secreta SHA-256 de pruebas Redsys');

        // 2. Parâmetros Fiscais da Agência Tributária (AEAT) - Zaragoza, España
        Setting::set('tax_default_iva_rate', '21.00', 'fiscal', false, 'Tipo de IVA estándar en España');
        Setting::set('fiscal_company_name', 'NUME Creative S.L.', 'fiscal', false, 'Razón Social de la Productora');
        Setting::set('fiscal_company_cif', 'B50987654', 'fiscal', false, 'NIF / CIF de la Productora en España');
        Setting::set('fiscal_company_address', 'Calle Alfonso I, 12, Planta 3', 'fiscal', false, 'Domicilio Fiscal');
        Setting::set('fiscal_company_postal_code', '50003', 'fiscal', false, 'Código Postal (Zaragoza)');
        Setting::set('fiscal_company_city', 'Zaragoza', 'fiscal', false, 'Ciudad de la Sede');
        Setting::set('fiscal_company_email', 'facturacion@numecreative.es', 'fiscal', false, 'Email de Facturación Oficial');
        Setting::set('fiscal_invoice_series', 'NUM-' . date('Y') . '-', 'fiscal', false, 'Serie de Facturación Correlativa');

        $this->command?->info('Configurações de Sandbox BBVA Redsys e Fiscais da AEAT semeadas com sucesso!');
    }
}
