<?php

namespace App\Services\Payment;

use App\Models\Pack;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\StripeCredentials;

class PaymentMethodAvailability
{
    public function __construct(
        public readonly ?Tenant $tenant = null
    ) {}

    public static function forTenant(?Tenant $tenant): self
    {
        return new self($tenant);
    }

    /**
     * Verifica se as credenciais do Redsys estão configuradas para o tenant.
     * Em ambiente local e testing, aceita fallback de teste se for tenant 'nume' ou se não houver credencial própria.
     * Em produção, SEM credencial explícita do tenant, retorna falso.
     */
    public function redsysConfigured(): bool
    {
        if (! $this->tenant) {
            return false;
        }

        $merchantCode = TenantSetting::get($this->tenant, 'redsys_merchant_code');
        $secretKey = TenantSetting::get($this->tenant, 'redsys_secret_key');

        if (! empty($merchantCode) && ! empty($secretKey)) {
            return true;
        }

        // Fallback especial exclusivo para tenant 'nume' ou ambiente local/testing
        if (app()->environment('local', 'testing') || $this->tenant->slug === 'nume') {
            $globalCode = Setting::get('redsys_merchant_code', config('services.redsys.merchant_code'));
            $globalKey = Setting::get('redsys_secret_key', config('services.redsys.secret_key'));

            return ! empty($globalCode) && ! empty($globalKey);
        }

        return false;
    }

    /**
     * Verifica se o TPV Redsys está ativo para a produtora.
     */
    public function redsysEnabled(): bool
    {
        if (! $this->redsysConfigured() || ! $this->tenant) {
            return false;
        }

        $enabled = TenantSetting::get($this->tenant, 'redsys_enabled');
        if ($enabled !== null && $enabled !== '') {
            return (bool) $enabled;
        }

        // Fallback para o tenant 'nume' ou testing
        if (app()->environment('local', 'testing') || $this->tenant->slug === 'nume') {
            return (bool) Setting::get('redsys_enabled', config('services.redsys.enabled', true));
        }

        return false;
    }

    /**
     * Verifica se o Bizum está ativo para o tenant e compatível com a moeda do pacote.
     */
    public function bizumEnabled(?Pack $pack = null): bool
    {
        if (! $this->redsysEnabled()) {
            return false;
        }

        $currency = strtolower($pack?->currency ?: 'eur');
        if ($currency !== 'eur') {
            return false;
        }

        $specific = TenantSetting::get($this->tenant, 'redsys_bizum_enabled');
        if ($specific !== null && $specific !== '') {
            return (bool) $specific;
        }

        return (bool) Setting::get('redsys_bizum_enabled', true);
    }

    /**
     * Verifica se o Cartão Redsys está ativo para o tenant e compatível com a moeda do pacote.
     */
    public function redsysCardEnabled(?Pack $pack = null): bool
    {
        if (! $this->redsysEnabled()) {
            return false;
        }

        $currency = strtolower($pack?->currency ?: 'eur');
        if ($currency !== 'eur') {
            return false;
        }

        $specific = TenantSetting::get($this->tenant, 'redsys_card_enabled');
        if ($specific !== null && $specific !== '') {
            return (bool) $specific;
        }

        return (bool) Setting::get('redsys_card_enabled', true);
    }

    /**
     * Verifica se a Stripe está ativa e configurada.
     */
    public function stripeEnabled(?Pack $pack = null): bool
    {
        $stripeConfigured = ! empty(StripeCredentials::secret());
        $stripeGeneral = (bool) Setting::get('stripe_enabled', true);
        $stripeTenantReady = $this->tenant ? ($this->tenant->canReceivePayments() || $this->tenant->slug === 'nume') : true;

        return $stripeGeneral && $stripeConfigured && $stripeTenantReady;
    }

    /**
     * Verifica se um método de pagamento específico está disponível.
     */
    public function isMethodActive(string $method, ?Pack $pack = null): bool
    {
        return match ($method) {
            'bizum', 'redsys_bizum' => $this->bizumEnabled($pack),
            'redsys_card' => $this->redsysCardEnabled($pack),
            'stripe' => $this->stripeEnabled($pack),
            default => false,
        };
    }

    /**
     * Retorna mapa de métodos ativos para a vitrine e portal.
     *
     * @return array<string, bool>
     */
    public function getActiveMethods(?Pack $pack = null): array
    {
        return [
            'bizum' => $this->bizumEnabled($pack),
            'redsys_card' => $this->redsysCardEnabled($pack),
            'stripe' => $this->stripeEnabled($pack),
        ];
    }
}
