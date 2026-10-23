<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'primary_contact_email',
        'company_cnpj',
        'subscription_plan',
        'subscribed_at',
        'subscription_expires_at',
        'stripe_account_id',
        'stripe_account_status',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        // Redes Sociais
        'social_instagram',
        'social_youtube',
        'social_linkedin',
        'social_tiktok',
        'social_whatsapp',
        'social_facebook',
        // SEO da Plataforma (preenchido pela Sofi ou manualmente)
        'seo_meta_title',
        'seo_meta_description',
        'seo_og_image',
        'seo_og_description',
        'seo_schema_keywords',
        'seo_canonical_url',
        'seo_last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at'           => 'datetime',
            'subscription_expires_at' => 'datetime',
            'charges_enabled'         => 'boolean',
            'payouts_enabled'         => 'boolean',
            'details_submitted'       => 'boolean',
            'seo_last_generated_at'   => 'datetime',
        ];
    }

    // ──────────────────────────────────────────
    // Relacionamentos
    // ──────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function stripeCustomer()
    {
        return $this->hasOne(StripeCustomer::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(Pack::class);
    }

    public function packPurchases(): HasMany
    {
        return $this->hasMany(PackPurchase::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class)->orderBy('sort_order');
    }

    public function showcaseVideos(): HasMany
    {
        return $this->hasMany(ShowcaseVideo::class)->orderBy('sort_order');
    }

    // ──────────────────────────────────────────
    // Helpers de negócio
    // ──────────────────────────────────────────

    /**
     * Retorna redes sociais preenchidas como [rede => url]
     */
    public function socialLinks(): array
    {
        return array_filter([
            'instagram' => $this->social_instagram,
            'youtube'   => $this->social_youtube,
            'linkedin'  => $this->social_linkedin,
            'tiktok'    => $this->social_tiktok,
            'whatsapp'  => $this->social_whatsapp,
            'facebook'  => $this->social_facebook,
        ]);
    }

    /**
     * Verifica se a Sofi já gerou o SEO da plataforma
     */
    public function hasSeoGenerated(): bool
    {
        return ! empty($this->seo_meta_title) && $this->seo_last_generated_at !== null;
    }

    /**
     * Verifica se o tenant está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se a subscrição está válida
     */
    public function isSubscriptionValid(): bool
    {
        if ($this->subscription_expires_at === null) {
            return false;
        }

        return $this->subscription_expires_at->isFuture();
    }

    /**
     * Verifica se a produtora concluiu o onboarding do Stripe Connect e pode receber pagamentos
     */
    public function canReceivePayments(): bool
    {
        return $this->stripe_account_id !== null && $this->charges_enabled;
    }
}
