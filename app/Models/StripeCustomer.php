<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'monthly_amount',
        'current_period_start',
        'current_period_end',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * Relacionamento: StripeCustomer pertence a um Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verifica se subscrição está ativa
     */
    public function isActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * Verifica se há débito pendente
     */
    public function isPastDue(): bool
    {
        return $this->subscription_status === 'past_due';
    }

    /**
     * Verifica se subscrição foi cancelada
     */
    public function isCanceled(): bool
    {
        return $this->subscription_status === 'canceled';
    }

    /**
     * Verifica se período de cobrança está vigente
     */
    public function isPeriodActive(): bool
    {
        if ($this->current_period_start === null || $this->current_period_end === null) {
            return false;
        }

        return now()->between($this->current_period_start, $this->current_period_end);
    }
}
