<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingPlan extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ADJUSTMENT = 'adjustment_requested';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'pack_purchase_id',
        'month_year',
        'title',
        'strategic_goal',
        'status',
        'metrics_summary',
        'anamnesis_snapshot',
        'adjustment_request',
    ];

    protected function casts(): array
    {
        return [
            'metrics_summary' => 'array',
            'anamnesis_snapshot' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Aprovação do cliente: só pelo próprio dono do plano (o controller confere).
     */
    public function approveBy(Cliente $cliente): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_cliente_id' => $cliente->id,
            'adjustment_request' => null,
        ])->save();
    }

    public function backToDraft(): void
    {
        $this->forceFill(['status' => self::STATUS_DRAFT, 'approved_at' => null, 'approved_by_cliente_id' => null])->save();
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

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
