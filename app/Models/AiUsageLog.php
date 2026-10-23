<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uso de tokens dos agentes da Sofi.
 *
 * Exceção à convenção de multi-tenancy: NÃO usa BelongsToTenant. É gravado pelo
 * sistema (inclusive em fila) com tenant_id explícito vindo do agente; o trait
 * preencheria tenant_id com o usuário logado. Toda consulta deve usar forTenant().
 */
class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'agent',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'cache_read_input_tokens',
        'cache_write_input_tokens',
        'invocation_id',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'cache_write_input_tokens' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
