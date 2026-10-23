<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Pack extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'showcase_video_id',
        'name',
        'slug',
        'description',
        'features',
        'addons',
        'video_quantity',
        'price',
        'vat_rate',
        'currency',
        'status',
        'billing_type',
        'billing_interval',
        'commitment_months',
        'billing_mode',
        'deposit_percentage',
        'pilot_discount_on_subscription',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'features' => 'array',
            'commitment_months' => 'integer',
            'deposit_percentage' => 'integer',
            'pilot_discount_on_subscription' => 'decimal:2',
        ];
    }

    public function showcaseVideo(): BelongsTo
    {
        return $this->belongsTo(ShowcaseVideo::class, 'showcase_video_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function getCategoryNameAttribute(): string
    {
        if ($this->category_id && $this->relationLoaded('category') && $this->getRelation('category')) {
            return $this->getRelation('category')->name;
        }
        if ($this->category_id) {
            $cat = $this->category()->first();
            if ($cat) {
                return $cat->name;
            }
        }

        return 'Pacote Audiovisual';
    }

    public function getTitleAttribute(): string
    {
        return $this->name;
    }

    protected static function booted(): void
    {
        static::saving(function (Pack $pack) {
            if (empty($pack->slug) && ! empty($pack->name)) {
                $baseSlug = Str::slug($pack->name);
                $pack->slug = $baseSlug ?: 'pack-'.Str::random(6);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PackPurchase::class);
    }

    public function addons(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'pack_addon')
            ->withPivot(['custom_price', 'is_default_selected', 'sort_order'])
            ->withTimestamps()
            ->orderBy('pack_addon.sort_order');
    }

    /**
     * Garante que o acesso à propriedade mágica $pack->addons retorne a Coleção da relação BelongsToMany,
     * evitando que a coluna legada 'addons' no banco sombreie a relação Eloquent.
     */
    public function getAddonsAttribute()
    {
        return $this->getRelationValue('addons');
    }

    /**
     * Mutator para salvar o JSON no campo legado da tabela packs
     */
    public function setAddonsAttribute($value): void
    {
        $this->attributes['addons'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Retorna o conteúdo bruto do campo legado 'addons' como array
     */
    public function getLegacyAddonsAttribute(): array
    {
        $raw = $this->getAttributeFromArray('addons');
        if (is_array($raw)) {
            return $raw;
        }
        return json_decode($raw ?? '[]', true) ?: [];
    }

    /**
     * Retorna a lista unificada de Add-ons (priorizando a relação M:N)
     */
    public function getEffectiveAddonsAttribute(): array
    {
        $collection = $this->addons;

        if ($collection instanceof \Illuminate\Support\Collection && $collection->isNotEmpty()) {
            return $collection->map(function ($addon) {
                $price = $addon->pivot?->custom_price !== null ? (float) $addon->pivot->custom_price : (float) $addon->default_price;
                return [
                    'id' => $addon->id,
                    'name' => __($addon->name),
                    'slug' => $addon->slug,
                    'description' => $addon->description ? __($addon->description) : null,
                    'price' => $price,
                    'is_default' => (bool) ($addon->pivot?->is_default_selected ?? false),
                ];
            })->toArray();
        }

        // Fallback para o JSON legado se não houver registros relacionais vinculados
        return $this->legacy_addons ?? [];
    }

    public function getTranslatedNameAttribute(): string
    {
        return __($this->name);
    }

    public function getTranslatedDescriptionAttribute(): ?string
    {
        return $this->description ? __($this->description) : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSubscription(): bool
    {
        return $this->billing_type === 'subscription';
    }

    public function isOneTime(): bool
    {
        return ! $this->isSubscription();
    }

    public function scopeRecurringPlans($query)
    {
        return $query->where('billing_type', 'subscription');
    }

    public function scopeOneOffPacks($query)
    {
        return $query->where(function ($q) {
            $q->where('billing_type', '!=', 'subscription')
              ->orWhereNull('billing_type');
        });
    }

    public function getBillingIntervalLabelAttribute(): string
    {
        if (! $this->isSubscription()) {
            return __('Pagamento Único');
        }

        return match ($this->billing_interval) {
            'year' => __('Anual'),
            'quarter' => __('Trimestral'),
            default => __('Mensal'),
        };
    }

    public function isSplit5050(): bool
    {
        return $this->billing_mode === 'split_50_50';
    }

    public function isPilotEntry(): bool
    {
        return $this->billing_mode === 'pilot_entry' || (float) $this->pilot_discount_on_subscription > 0;
    }

    public function isFullUpfront(): bool
    {
        return empty($this->billing_mode) || $this->billing_mode === 'full_upfront';
    }

    public function getEffectiveDepositPercentage(): int
    {
        if ($this->isSplit5050()) {
            return $this->deposit_percentage ?: 50;
        }

        return 100;
    }

    public function calculateDepositAmount(?float $total = null): float
    {
        $amount = $total !== null ? $total : (float) $this->price;
        $percentage = $this->getEffectiveDepositPercentage();
        return round(($amount * $percentage) / 100, 2);
    }

    public function calculateBalanceAmount(?float $total = null): float
    {
        if (! $this->isSplit5050()) {
            return 0.00;
        }

        $amount = $total !== null ? $total : (float) $this->price;
        $deposit = $this->calculateDepositAmount($amount);
        return round($amount - $deposit, 2);
    }
}
