<?php

namespace App\Models;

use App\Services\Payment\PaymentMethodAvailability;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'is_encrypted',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get a setting by key with automatic decryption if encrypted.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();

            if (! $setting || $setting->value === null) {
                return $default;
            }

            if ($setting->is_encrypted) {
                try {
                    return Crypt::decryptString($setting->value);
                } catch (Exception $e) {
                    Log::warning("Could not decrypt setting '{$key}': ".get_class($e));

                    return $default;
                }
            }

            return $setting->value;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Set a setting value with optional encryption.
     */
    public static function set(string $key, mixed $value, string $group = 'general', bool $isEncrypted = false, ?string $description = null): static
    {
        $storeValue = $value;

        if ($isEncrypted && ! empty($value)) {
            $storeValue = Crypt::encryptString((string) $value);
        }

        return static::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $storeValue,
                'is_encrypted' => $isEncrypted,
                'description' => $description,
            ]
        );
    }

    /**
     * Load gateway / stripe settings into Laravel runtime config.
     */
    public static function loadIntoConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $stripeSecret = static::get('stripe_secret', config('services.stripe.secret'));
            $stripeKey = static::get('stripe_key', config('services.stripe.key'));
            $stripeWebhook = static::get('stripe_webhook_secret', config('services.stripe.webhook_secret'));
            $stripePriceId = static::get('stripe_price_id', config('services.stripe.price_id'));
            $stripeFee = static::get('stripe_platform_fee_percent', config('services.stripe.platform_fee_percent', 10));

            $googleClientId = static::get('google_client_id', config('services.google.client_id'));
            $googleClientSecret = static::get('google_client_secret', config('services.google.client_secret'));

            config([
                'services.stripe.secret' => $stripeSecret,
                'services.stripe.key' => $stripeKey,
                'services.stripe.webhook_secret' => $stripeWebhook,
                'services.stripe.price_id' => $stripePriceId,
                'services.stripe.platform_fee_percent' => $stripeFee,
                'services.google.client_id' => $googleClientId,
                'services.google.client_secret' => $googleClientSecret,
            ]);
        } catch (Exception $e) {
            // Silently ignore if DB connection is not ready during console boots
        }
    }

    /**
    /**
     * Retorna array com status de ativação de cada método de pagamento.
     *
     * @deprecated Use \App\Services\Payment\PaymentMethodAvailability::forTenant($tenant)->getActiveMethods($pack)
     *
     * @return array<string, bool>
     */
    public static function getActivePaymentMethods(?Tenant $tenant = null, ?Pack $pack = null): array
    {
        return PaymentMethodAvailability::forTenant($tenant)->getActiveMethods($pack);
    }

    /**
     * Verifica se um método de pagamento específico está ativo.
     *
     * @deprecated Use \App\Services\Payment\PaymentMethodAvailability::forTenant($tenant)->isMethodActive($method, $pack)
     */
    public static function isPaymentMethodActive(string $method, ?Tenant $tenant = null, ?Pack $pack = null): bool
    {
        return PaymentMethodAvailability::forTenant($tenant)->isMethodActive($method, $pack);
    }
}
