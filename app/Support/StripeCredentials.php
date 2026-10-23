<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Fonte única de verdade para credenciais Stripe (secret, key pública, webhook secret).
 * Prioriza Setting (banco criptografado) com fallback para config (env).
 * Memoriza os valores em memória durante a requisição.
 */
class StripeCredentials
{
    private static ?string $secret = null;

    private static ?string $publishableKey = null;

    private static ?string $webhookSecret = null;

    public static function secret(): ?string
    {
        if (static::$secret !== null) {
            return static::$secret;
        }

        $val = Setting::get('stripe_secret');
        if (empty($val)) {
            $val = config('services.stripe.secret');
        }

        return static::$secret = (! empty($val) ? (string) $val : null);
    }

    public static function publishableKey(): ?string
    {
        if (static::$publishableKey !== null) {
            return static::$publishableKey;
        }

        $val = Setting::get('stripe_key');
        if (empty($val)) {
            $val = config('services.stripe.key');
        }

        return static::$publishableKey = (! empty($val) ? (string) $val : null);
    }

    public static function webhookSecret(): ?string
    {
        if (static::$webhookSecret !== null) {
            return static::$webhookSecret;
        }

        $val = Setting::get('stripe_webhook_secret');
        if (empty($val)) {
            $val = config('services.stripe.webhook_secret');
        }

        return static::$webhookSecret = (! empty($val) ? (string) $val : null);
    }

    /**
     * Limpa os valores memorizados em memória.
     */
    public static function flush(): void
    {
        static::$secret = null;
        static::$publishableKey = null;
        static::$webhookSecret = null;
    }
}
