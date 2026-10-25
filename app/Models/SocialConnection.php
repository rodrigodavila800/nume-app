<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta de rede social conectada pelo app próprio da NUME (OAuth oficial da rede).
 *
 * Tokens: cast "encrypted" (APP_KEY; trocar a chave exige APP_PREVIOUS_KEYS, senão os tokens
 * se perdem) e $hidden (nunca saem em JSON/array). Nunca registrar tokens em log.
 */
class SocialConnection extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'platform',
        'provider',
        'external_account_id',
        'handle',
        'display_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'expires_at',
        'scopes',
        'status',
        'last_refreshed_at',
        'last_error',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(VideoPublication::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * O token de acesso vence em até $minutes (ou já venceu): renovar antes de usar.
     * Sem expires_at o token não tem validade fixa (ex.: token de Página do Facebook).
     */
    public function expiresWithin(int $minutes = 5): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now()->addMinutes($minutes));
    }

    /**
     * Envios nativos ainda não feitos por esta conta são cancelados (ao desconectar).
     */
    public function cancelPendingPublications(string $reason = 'disconnected'): int
    {
        return $this->publications()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->whereIn('status', ['pendente', 'agendado'])
            ->update(['status' => 'falhou', 'last_error' => $reason]);
    }

    /**
     * Registra falha sem expor detalhes: só o tipo do erro (classe ou código da rede).
     */
    public function markFailed(string $error, string $status = self::STATUS_ERROR): void
    {
        $this->forceFill(['status' => $status, 'last_error' => mb_substr($error, 0, 100)])->save();
    }
}
