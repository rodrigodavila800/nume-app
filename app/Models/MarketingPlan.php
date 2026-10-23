<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPlan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'pack_purchase_id',
        'month_year',
        'title',
        'strategic_goal',
        'status',
        'metrics_summary',
    ];

    protected function casts(): array
    {
        return [
            'metrics_summary' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function packPurchase(): BelongsTo
    {
        return $this->belongsTo(PackPurchase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketingPlanItem::class)->orderBy('week_number', 'asc')->orderBy('id', 'asc');
    }
}
