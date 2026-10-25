<?php

namespace App\Models;

use App\Enums\PlataformaAlvo;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Cliente extends Authenticatable
{
    use BelongsToTenant, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'company_name',
        'phone',
        'stripe_customer_id',
        'website',
        'instagram_handle',
        'linkedin_url',
        'youtube_channel',
        'tiktok_handle',
        'facebook_url',
        'x_handle',
        'threads_handle',
        'pinterest_url',
        'brand_niche',
        'target_audience',
        'key_differentials',
        'brand_study',
        'brand_study_generated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'brand_study' => 'array',
            'brand_study_generated_at' => 'datetime',
        ];
    }

    /**
     * Verifica se o cliente já preencheu os dados fundamentais do perfil da sua marca.
     */
    public function hasCompletedBrandProfile(): bool
    {
        $hasNiche = ! empty(trim($this->brand_niche ?? ''));
        $hasPresenceOrAudience = ! empty(trim($this->website ?? ''))
            || ! empty(trim($this->target_audience ?? ''))
            || $this->activeSocialChannels() !== [];

        return $hasNiche && $hasPresenceOrAudience;
    }

    /**
     * Perfil da marca numa rede: primeiro a conta conectada no ecossistema (Upload-Post),
     * depois o que foi digitado no DNA. O DNA digitado nunca é sobrescrito pela conexão.
     */
    public function getEffectiveSocialHandle(PlataformaAlvo $plataforma): ?string
    {
        $connected = $this->socialProfile?->connectedHandle($plataforma->value);
        if ($connected !== null) {
            return $connected;
        }

        $manual = trim((string) $this->getAttribute($plataforma->clienteField()));

        return $manual === '' ? null : $manual;
    }

    /**
     * Redes em que a marca está presente (conectada no ecossistema ou informada no DNA).
     *
     * @return array<string, ?string> slug da rede => handle efetivo (null se conectada sem handle conhecido)
     */
    public function activeSocialChannels(): array
    {
        $channels = [];

        foreach (PlataformaAlvo::cases() as $plataforma) {
            $handle = $this->getEffectiveSocialHandle($plataforma);

            if ($handle !== null || $this->socialProfile?->isPlatformConnected($plataforma->value)) {
                $channels[$plataforma->value] = $handle;
            }
        }

        return $channels;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function packPurchases(): HasMany
    {
        return $this->hasMany(PackPurchase::class);
    }

    public function sofiConversations(): HasMany
    {
        return $this->hasMany(SofiConversation::class)->orderBy('updated_at', 'desc');
    }

    public function marketingPlans(): HasMany
    {
        return $this->hasMany(MarketingPlan::class)->orderBy('month_year', 'desc');
    }

    public function socialProfile(): HasOne
    {
        return $this->hasOne(SocialProfile::class);
    }

    public function videoPublications(): HasMany
    {
        return $this->hasMany(VideoPublication::class)->orderBy('created_at', 'desc');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Retorna query builder de todas as Quotes do cliente (por cliente_id OU client_email no mesmo tenant).
     */
    public function allQuotes()
    {
        return Quote::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant_id)
            ->where(function ($query) {
                $query->where('cliente_id', $this->id)
                    ->orWhere(function ($q) {
                        $q->whereNull('cliente_id')
                            ->where('client_email', $this->email);
                    });
            });
    }

    /**
     * Verifica se o cliente possui o Add-on de distribuição comprado em algum pacote ativo.
     */
    public function hasDistributionAddon(): bool
    {
        return $this->packPurchases()
            ->whereIn('status', ['paid', 'active'])
            ->get()
            ->contains(function ($purchase) {
                return $purchase->hasAddon('distribuicao') || $purchase->hasAddon('multi-rede');
            });
    }

    /**
     * Verifica se o cliente tem direito e saldo para realizar publicação social automatizada.
     */
    public function canDistributeSocial(): bool
    {
        // Se possui o Addon contratado, distribuição é liberada
        if ($this->hasDistributionAddon()) {
            return true;
        }

        // Se possui plano recorrente ativo
        $hasActiveSubscription = $this->packPurchases()
            ->where('subscription_status', 'active')
            ->exists();

        if ($hasActiveSubscription) {
            return true;
        }

        // Caso padrão: possui saldo dentro dos vídeos do pacote contratado
        return $this->getRemainingDistributionQuota() > 0;
    }

    /**
     * Cota total de vídeos contratados disponíveis para distribuição.
     */
    public function getDistributionQuota(): int
    {
        return (int) $this->packPurchases()
            ->whereIn('status', ['paid', 'active'])
            ->sum('video_quantity');
    }

    /**
     * Quantidade de publicações sociais já disparadas.
     */
    public function getDistributionsUsedCount(): int
    {
        return (int) $this->videoPublications()
            ->whereIn('status', ['processando', 'publicado'])
            ->count();
    }

    /**
     * Saldo restante de cota de publicações.
     */
    public function getRemainingDistributionQuota(): int
    {
        if ($this->hasDistributionAddon()) {
            return 9999; // Ilimitado para assinantes do Add-on
        }

        $totalQuota = $this->getDistributionQuota();
        $used = $this->getDistributionsUsedCount();

        return max(0, $totalQuota - $used);
    }
}
