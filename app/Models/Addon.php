<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Addon extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'default_price',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Addon $addon) {
            if (empty($addon->slug) && !empty($addon->name)) {
                $addon->slug = Str::slug($addon->name);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function packs(): BelongsToMany
    {
        return $this->belongsToMany(Pack::class, 'pack_addon')
            ->withPivot(['custom_price', 'is_default_selected', 'sort_order'])
            ->withTimestamps();
    }

    public function getPriceAttribute(): float
    {
        if (isset($this->pivot) && $this->pivot && $this->pivot->custom_price !== null) {
            return (float) $this->pivot->custom_price;
        }

        return (float) $this->default_price;
    }

    public function getPriceForPack(?Pack $pack = null): float
    {
        if ($pack && $pack->relationLoaded('addons')) {
            $pivot = $pack->addons->firstWhere('id', $this->id)?->pivot;
            if ($pivot && $pivot->custom_price !== null) {
                return (float) $pivot->custom_price;
            }
        }

        return (float) $this->default_price;
    }
}
