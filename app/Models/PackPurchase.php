<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackPurchase extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'pack_id',
        'quote_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_subscription_id',
        'payment_gateway',
        'gateway_order_reference',
        'gateway_metadata',
        'amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'irpf_rate',
        'irpf_amount',
        'total_amount',
        'billing_type',
        'billing_tax_id',
        'billing_legal_name',
        'billing_address',
        'billing_postal_code',
        'billing_city',
        'billing_country',
        'invoice_number',
        'invoice_series',
        'invoice_generated_at',
        'invoice_path',
        'invoice_snapshot',
        'application_fee_amount',
        'platform_fee_status',
        'status',
        'subscription_status',
        'subscription_interval',
        'commitment_months',
        'subscription_cycle_current',
        'subscription_cycles_total',
        'subscription_renews_at',
        'subscription_ends_at',
        'video_quantity',
        'social_networks_limit',
        'monthly_post_limit',
        'videos_used',
        'addons_purchased',
        'addons_snapshot',
        'paid_at',
        'expires_at',
        'bank_transfer_reference',
        'confirmed_by_user_id',
        'confirmed_at',
        'billing_mode',
        'deposit_amount',
        'deposit_paid_at',
        'balance_amount',
        'balance_paid_at',
        'balance_status',
        'stripe_payment_method_id',
        'stripe_balance_payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'irpf_rate' => 'decimal:2',
            'irpf_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'application_fee_amount' => 'decimal:2',
            'commitment_months' => 'integer',
            'subscription_cycle_current' => 'integer',
            'subscription_cycles_total' => 'integer',
            'addons_purchased' => 'array',
            'addons_snapshot' => 'array',
            'gateway_metadata' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'deposit_paid_at' => 'datetime',
            'balance_paid_at' => 'datetime',
            'invoice_generated_at' => 'datetime',
            'invoice_snapshot' => 'array',
            'subscription_renews_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
        ];
    }

    public function isSubscription(): bool
    {
        return ! empty($this->subscription_status) || ($this->pack && $this->pack->billing_type === 'subscription');
    }

    public function renewSubscriptionCycle(?int $newVideosQuota = null): void
    {
        if ($this->status !== 'paid') {
            throw new \DomainException(__('Não é possível renovar ciclo de uma assinatura com status pendente ou inválido.'));
        }

        $newCycle = $this->subscription_cycle_current + 1;
        $isCompleted = $newCycle > $this->subscription_cycles_total;
        $intervalMonths = ($this->subscription_interval === 'quarter') ? 3 : 1;
        $quotaToAdd = $newVideosQuota ?? ($this->pack?->video_quantity ?? $this->video_quantity);

        $this->update([
            'subscription_cycle_current' => min($newCycle, $this->subscription_cycles_total),
            'subscription_status' => $isCompleted ? 'completed' : 'active',
            'video_quantity' => $this->video_quantity + $quotaToAdd,
            'subscription_renews_at' => $isCompleted ? null : now()->addMonths($intervalMonths),
        ]);
    }

    public function cancelSubscription(): void
    {
        $this->update([
            'subscription_status' => 'canceled',
            'subscription_renews_at' => null,
        ]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->status === 'pending' && $this->expires_at && $this->expires_at->isPast());
    }

    public function isBankTransfer(): bool
    {
        return $this->payment_gateway === 'bank_transfer';
    }

    public function scopeBankTransfers($query)
    {
        return $query->where('payment_gateway', 'bank_transfer');
    }

    public function scopePendingBankTransfers($query)
    {
        return $query->where('payment_gateway', 'bank_transfer')->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function videosRemaining(): int
    {
        return max(0, $this->video_quantity - $this->videos_used);
    }

    public function markAsPaid(string $paymentIntentId): void
    {
        $this->update([
            'status' => 'paid',
            'payment_gateway' => 'stripe',
            'stripe_payment_intent_id' => $paymentIntentId,
            'platform_fee_status' => 'collected_at_gateway',
            'paid_at' => now(),
        ]);
    }

    public function markAsPaidViaGateway(string $gateway, string $reference, ?array $metadata = null): void
    {
        $isNume = ($this->tenant?->slug === 'nume');
        $feeStatus = (! $isNume && (float) $this->application_fee_amount > 0)
            ? 'pending_billing'
            : 'not_applicable';

        $this->update([
            'status' => 'paid',
            'payment_gateway' => $gateway,
            'gateway_order_reference' => $reference,
            'gateway_metadata' => $metadata,
            'platform_fee_status' => $feeStatus,
            'paid_at' => now(),
        ]);
    }

    public function isPlatformFeeCollected(): bool
    {
        return $this->platform_fee_status === 'collected_at_gateway';
    }

    public function isPlatformFeePending(): bool
    {
        return $this->platform_fee_status === 'pending_billing';
    }

    public function scopePendingPlatformFee($query)
    {
        return $query->where('platform_fee_status', 'pending_billing');
    }

    public function scopeCollectedPlatformFee($query)
    {
        return $query->where('platform_fee_status', 'collected_at_gateway');
    }

    /**
     * Verifica se este pacote comprado inclui determinado add-on.
     */
    public function hasAddon(string $addonNameOrSlug): bool
    {
        if (empty($this->addons_purchased)) {
            return false;
        }

        $needle = strtolower($addonNameOrSlug);

        foreach ($this->addons_purchased as $addon) {
            $addonStr = is_array($addon) ? ($addon['name'] ?? $addon['slug'] ?? '') : (string) $addon;
            if (str_contains(strtolower($addonStr), $needle)) {
                return true;
            }
        }

        return false;
    }

    public function isSplit5050(): bool
    {
        return $this->billing_mode === 'split_50_50';
    }

    public function isBalancePending(): bool
    {
        return $this->isSplit5050() && $this->balance_status === 'pending';
    }

    public function isBalancePaid(): bool
    {
        return $this->balance_status === 'paid' || $this->balance_status === 'waived';
    }

    public function isAssetLocked(): bool
    {
        if (! $this->isSplit5050()) {
            return false;
        }

        return ! $this->isBalancePaid();
    }

    public function isPilotEntry(): bool
    {
        return $this->billing_mode === 'pilot_entry' || ($this->pack?->isPilotEntry() ?? false);
    }

    public function markBalancePaid(string $paymentIntentId): void
    {
        $this->update([
            'balance_status' => 'paid',
            'balance_paid_at' => now(),
            'stripe_balance_payment_intent_id' => $paymentIntentId,
        ]);
    }
}
