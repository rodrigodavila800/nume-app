<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'prefix',
        'key_hash',
        'rate_limit_per_minute',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Gera uma nova chave de API segura e retorna o model persistido e a chave pura em texto.
     * A chave pura só é exibida uma única vez ao usuário.
     *
     * @return array{apiKey: ApiKey, plainTextToken: string}
     */
    public static function createToken(Tenant $tenant, string $name, int $rateLimit = 60, ?\DateTimeInterface $expiresAt = null): array
    {
        $prefix = 'nume_live_';
        $entropy = Str::random(40);
        $plainTextToken = $prefix . $entropy;

        $keyHash = hash('sha256', $plainTextToken);

        $apiKey = static::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => $keyHash,
            'rate_limit_per_minute' => $rateLimit,
            'expires_at' => $expiresAt,
        ]);

        return [
            'apiKey' => $apiKey,
            'plainTextToken' => $plainTextToken,
        ];
    }

    /**
     * Localiza a chave de API pelo token puro fornecido.
     */
    public static function findToken(string $token): ?static
    {
        $hash = hash('sha256', trim($token));

        return static::withoutGlobalScopes()
            ->where('key_hash', $hash)
            ->first();
    }

    /**
     * Verifica se a chave é válida e não revogada nem expirada.
     */
    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Revoga a chave imediatamente.
     */
    public function revoke(): bool
    {
        return $this->update(['revoked_at' => now()]);
    }

    /**
     * Registra o uso da chave.
     */
    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
