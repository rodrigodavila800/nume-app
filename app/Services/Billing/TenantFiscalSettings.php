<?php

namespace App\Services\Billing;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSetting;

class TenantFiscalSettings
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $regime,
        public readonly float $irpfRate,
        public readonly float $ivaRate,
        public readonly string $companyName,
        public readonly string $companyCif,
        public readonly string $companyAddress,
        public readonly string $companyPostalCode,
        public readonly string $companyCity,
        public readonly string $companyEmail,
        public readonly string $invoiceSeries
    ) {}

    /**
     * Resolve as configurações fiscais do tenant com fallback para as configurações globais.
     */
    public static function for(Tenant|int $tenant): self
    {
        $tenantModel = $tenant instanceof Tenant
            ? $tenant
            : Tenant::withoutGlobalScope('tenant')->findOrFail($tenant);

        $regime = TenantSetting::get($tenantModel, 'fiscal_regime');
        if (empty($regime)) {
            $regime = Setting::get('fiscal_regime', 'autonomo');
        }

        $rawIrpfRate = TenantSetting::get($tenantModel, 'fiscal_irpf_rate');
        if ($rawIrpfRate === null || $rawIrpfRate === '') {
            $rawIrpfRate = Setting::get('fiscal_irpf_rate', 15.00);
        }
        $irpfRate = ($regime === 'autonomo') ? (float) $rawIrpfRate : 0.00;

        $rawIvaRate = TenantSetting::get($tenantModel, 'tax_default_iva_rate');
        if ($rawIvaRate === null || $rawIvaRate === '') {
            $rawIvaRate = Setting::get('tax_default_iva_rate', 21.00);
        }
        $ivaRate = (float) $rawIvaRate;

        $companyName = TenantSetting::get($tenantModel, 'fiscal_company_name');
        if (empty($companyName)) {
            $companyName = Setting::get('fiscal_company_name', $tenantModel->name ?: 'Nume Creative S.L.');
        }

        $companyCif = TenantSetting::get($tenantModel, 'fiscal_company_cif');
        if (empty($companyCif)) {
            $companyCif = Setting::get('fiscal_company_cif', 'B12345678');
        }

        $companyAddress = TenantSetting::get($tenantModel, 'fiscal_company_address');
        if (empty($companyAddress)) {
            $companyAddress = Setting::get('fiscal_company_address', 'Calle Alfonso I, 12, Planta 3');
        }

        $companyPostalCode = TenantSetting::get($tenantModel, 'fiscal_company_postal_code');
        if (empty($companyPostalCode)) {
            $companyPostalCode = Setting::get('fiscal_company_postal_code', '50003');
        }

        $companyCity = TenantSetting::get($tenantModel, 'fiscal_company_city');
        if (empty($companyCity)) {
            $companyCity = Setting::get('fiscal_company_city', 'Zaragoza');
        }

        $companyEmail = TenantSetting::get($tenantModel, 'fiscal_company_email');
        if (empty($companyEmail)) {
            $companyEmail = Setting::get('fiscal_company_email', 'facturacion@nume.es');
        }

        $invoiceSeries = TenantSetting::get($tenantModel, 'fiscal_invoice_series');
        if (empty($invoiceSeries)) {
            $invoiceSeries = Setting::get('fiscal_invoice_series', 'NUM-'.date('Y').'-');
        }

        return new self(
            tenant: $tenantModel,
            regime: $regime,
            irpfRate: $irpfRate,
            ivaRate: $ivaRate,
            companyName: (string) $companyName,
            companyCif: (string) $companyCif,
            companyAddress: (string) $companyAddress,
            companyPostalCode: (string) $companyPostalCode,
            companyCity: (string) $companyCity,
            companyEmail: (string) $companyEmail,
            invoiceSeries: (string) $invoiceSeries
        );
    }

    /**
     * Atualiza as configurações fiscais do tenant.
     */
    public function update(array $attributes): self
    {
        $allowedKeys = [
            'fiscal_regime',
            'fiscal_irpf_rate',
            'tax_default_iva_rate',
            'fiscal_company_name',
            'fiscal_company_cif',
            'fiscal_invoice_series',
            'fiscal_company_address',
            'fiscal_company_postal_code',
            'fiscal_company_city',
            'fiscal_company_email',
        ];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $attributes)) {
                TenantSetting::set($this->tenant, $key, $attributes[$key]);
            }
        }

        return static::for($this->tenant);
    }
}
