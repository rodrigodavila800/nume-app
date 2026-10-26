<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico do lead: notas, contatos e mudanças de etapa (automáticas ou manuais).
 */
class LeadActivity extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const TYPES = ['note', 'call', 'email', 'meeting', 'whatsapp', 'stage_change', 'system'];

    /** Tipos que contam como contato com o lead. */
    public const CONTACT_TYPES = ['call', 'email', 'meeting', 'whatsapp'];

    protected $fillable = ['tenant_id', 'lead_id', 'user_id', 'type', 'body', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
