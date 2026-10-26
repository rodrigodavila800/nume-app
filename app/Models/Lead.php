<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lead do CRM da produtora. Dados pessoais: exportáveis e anonimizáveis (RGPD).
 * Mudança de etapa sempre por LeadService::moveTo (registra no histórico).
 */
class Lead extends Model
{
    use BelongsToTenant;

    public const STAGES = ['novo', 'contato', 'qualificado', 'proposta', 'negociacao', 'ganho', 'perdido'];

    public const OPEN_STAGES = ['novo', 'contato', 'qualificado', 'proposta', 'negociacao'];

    public const SOURCES = ['manual', 'portal_register', 'storefront', 'quote_request', 'sales_chat', 'referral', 'api', 'social'];

    public const LEGAL_BASES = ['consent', 'contract', 'legitimate_interest'];

    /** Dias sem contato para o lead aparecer sinalizado no funil. */
    public const STALE_DAYS = 7;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'owner_id',
        'name',
        'company',
        'email',
        'phone',
        'source',
        'estimated_value',
        'notes',
        'legal_basis',
        'consent_at',
        'last_contact_at',
        'next_action_at',
    ];

    protected $attributes = [
        'stage' => 'novo',
        'source' => 'manual',
        'legal_basis' => 'legitimate_interest',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'float',
            'consent_at' => 'datetime',
            'last_contact_at' => 'datetime',
            'next_action_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public static function stageLabels(): array
    {
        return [
            'novo' => __('Novo'),
            'contato' => __('Em contato'),
            'qualificado' => __('Qualificado'),
            'proposta' => __('Proposta enviada'),
            'negociacao' => __('Negociação'),
            'ganho' => __('Ganho'),
            'perdido' => __('Perdido'),
        ];
    }

    public static function sourceLabels(): array
    {
        return [
            'manual' => __('Cadastro manual'),
            'portal_register' => __('Cadastro no portal'),
            'storefront' => __('Vitrine'),
            'quote_request' => __('Pedido de orçamento'),
            'sales_chat' => __('Chat de vendas'),
            'referral' => __('Indicação'),
            'api' => __('API'),
            'social' => __('Redes sociais'),
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN_STAGES, true);
    }

    /**
     * Aberto e sem contato há STALE_DAYS dias (ou sem nenhum contato desde a criação).
     */
    public function isStale(): bool
    {
        return $this->isOpen() && ($this->last_contact_at ?? $this->created_at)?->lt(now()->subDays(self::STALE_DAYS));
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }
}
