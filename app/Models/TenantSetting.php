<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TenantSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Resolve o ID do tenant independentemente de receber Model ou int.
     */
    protected static function resolveTenantId(Tenant|int $tenant): int
    {
        return $tenant instanceof Tenant ? (int) $tenant->id : (int) $tenant;
    }

    /**
     * Obtém uma configuração do tenant com descriptografia automática se criptografada.
     */
    public static function get(Tenant|int $tenant, string $key, mixed $default = null): mixed
    {
        $tenantId = static::resolveTenantId($tenant);

        try {
            $setting = static::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->first();

            if (! $setting || $setting->value === null) {
                return $default;
            }

            if ($setting->is_encrypted) {
                try {
                    return Crypt::decryptString($setting->value);
                } catch (Exception $e) {
                    Log::warning("TenantSetting: Could not decrypt key '{$key}' for tenant {$tenantId}: ".get_class($e));

                    return $default;
                }
            }

            return $setting->value;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Define ou atualiza uma configuração para o tenant.
     */
    public static function set(Tenant|int $tenant, string $key, mixed $value, bool $isEncrypted = false): static
    {
        $tenantId = static::resolveTenantId($tenant);
        $storeValue = $value;

        if ($isEncrypted && ! empty($value)) {
            $storeValue = Crypt::encryptString((string) $value);
        }

        return static::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'key' => $key,
            ],
            [
                'value' => $storeValue,
                'is_encrypted' => $isEncrypted,
            ]
        );
    }
}
