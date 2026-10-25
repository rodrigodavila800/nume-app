<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialProfile extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'upload_post_username',
        'connected',
        'platforms_connected',
        'account_details',
        'last_synced_at',
    ];

    protected $casts = [
        'connected' => 'boolean',
        'platforms_connected' => 'array',
        'account_details' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function videoPublications(): HasMany
    {
        return $this->hasMany(VideoPublication::class, 'cliente_id', 'cliente_id');
    }

    /**
     * Verifica se uma plataforma específica está conectada.
     */
    public function isPlatformConnected(string $platform): bool
    {
        if (! $this->connected || empty($this->platforms_connected)) {
            return false;
        }

        return in_array(strtolower($platform), array_map('strtolower', $this->platforms_connected));
    }

    /**
     * Handle público da conta conectada (só existe com o Upload-Post real configurado).
     */
    public function connectedHandle(string $platform): ?string
    {
        if (! $this->isPlatformConnected($platform)) {
            return null;
        }

        $handle = $this->account_details[strtolower($platform)]['handle'] ?? null;

        return is_string($handle) && $handle !== '' ? $handle : null;
    }
}
