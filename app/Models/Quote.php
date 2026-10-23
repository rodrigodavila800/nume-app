<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Quote extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'cliente_id',
        'user_id',
        'production_id',
        'number',
        'status',
        'client_name',
        'client_contact',
        'client_email',
        'project_title',
        'language',
        'request_text',
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
    ];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
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

        return $this->valid_until && $this->valid_until->isPast() && ! $this->isAccepted();
    }
}
