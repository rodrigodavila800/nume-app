<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Quote extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** Pedido feito pelo cliente no portal, ainda sem proposta da produtora. */
    public const STATUS_REQUESTED = 'requested';

    public const KIND_PRODUCTION = 'production';

    public const KIND_ADS = 'ads';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'cliente_id',
        'user_id',
        'production_id',
        'marketing_plan_id',
        'number',
        'status',
        'kind',
        'client_name',
        'client_contact',
        'client_email',
        'project_title',
        'language',
        'request_text',
        'request_details',
        'inputs',
        'calculation',
        'ai_reading',
        'subtotal',
        'margin_amount',
        'adjustment_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'deposit_amount',
        'balance_amount',
        'valid_until',
        'sent_at',
        'accepted_at',
        'approval_token',
        'approval_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'request_details' => 'array',
            'calculation' => 'array',
            'ai_reading' => 'array',
            'subtotal' => 'float',
            'margin_amount' => 'float',
            'adjustment_amount' => 'float',
            'tax_rate' => 'float',
            'tax_amount' => 'float',
            'total_amount' => 'float',
            'deposit_amount' => 'float',
            'balance_amount' => 'float',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'approval_token_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quote $quote) {
            if (empty($quote->uuid)) {
                $quote->uuid = (string) Str::uuid();
            }
            if (empty($quote->number)) {
                $now = now();
                $quote->number = sprintf(
                    'NUME-%s-%s',
                    $now->format('Ymd'),
                    $now->format('Hi')
                );
            }
        });

        // Funil do CRM acompanha o orçamento (pedido → proposta → ganho/perdido).
        static::saved(function (Quote $quote) {
            if ($quote->wasRecentlyCreated || $quote->wasChanged('status')) {
                app(\App\Services\Crm\LeadAutomation::class)->quoteChanged($quote);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function packPurchases(): HasMany
    {
        return $this->hasMany(PackPurchase::class);
    }

    public function marketingPlan(): BelongsTo
    {
        return $this->belongsTo(MarketingPlan::class);
    }

    public function isRequested(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    public function isAds(): bool
    {
        return $this->kind === self::KIND_ADS;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->isAccepted()) {
            return false;
        }

        if ($this->approval_token_expires_at && $this->approval_token_expires_at->isPast()) {
            return true;
        }

        return (bool) ($this->valid_until && $this->valid_until->isPast());
    }
}
