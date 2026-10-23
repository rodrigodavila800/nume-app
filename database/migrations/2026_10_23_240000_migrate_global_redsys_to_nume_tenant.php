<?php

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Copia as credenciais globais existentes de Redsys para o tenant oficial 'nume' (se existir).
     */
    public function up(): void
    {
        $tenant = Tenant::where('slug', 'nume')->first();
        if (! $tenant) {
            return;
        }

        $merchantCode = Setting::get('redsys_merchant_code', config('services.redsys.merchant_code'));
        $terminal = Setting::get('redsys_terminal', config('services.redsys.terminal', '001'));
        $secretKey = Setting::get('redsys_secret_key', config('services.redsys.secret_key'));
        $environment = Setting::get('redsys_environment', config('services.redsys.environment', 'test'));
        $enabled = Setting::get('redsys_enabled', config('services.redsys.enabled', true));
        $bizumEnabled = Setting::get('redsys_bizum_enabled', true);
        $cardEnabled = Setting::get('redsys_card_enabled', true);

        if (! empty($merchantCode)) {
            TenantSetting::set($tenant, 'redsys_merchant_code', $merchantCode);
        }
        if (! empty($terminal)) {
            TenantSetting::set($tenant, 'redsys_terminal', $terminal);
        }
        if (! empty($secretKey)) {
            TenantSetting::set($tenant, 'redsys_secret_key', $secretKey);
        }
        if (! empty($environment)) {
            TenantSetting::set($tenant, 'redsys_environment', $environment);
        }
        TenantSetting::set($tenant, 'redsys_enabled', $enabled ? '1' : '0');
        TenantSetting::set($tenant, 'redsys_bizum_enabled', $bizumEnabled ? '1' : '0');
        TenantSetting::set($tenant, 'redsys_card_enabled', $cardEnabled ? '1' : '0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tenant = Tenant::where('slug', 'nume')->first();
        if (! $tenant) {
            return;
        }

        $keys = [
            'redsys_merchant_code',
            'redsys_terminal',
            'redsys_secret_key',
            'redsys_environment',
            'redsys_enabled',
            'redsys_bizum_enabled',
            'redsys_card_enabled',
        ];

        foreach ($keys as $key) {
            TenantSetting::forTenant($tenant->id)->where('key', $key)->delete();
        }
    }
};
