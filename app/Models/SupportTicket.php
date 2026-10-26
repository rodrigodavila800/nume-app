<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chamado de suporte. Status e respostas sempre pelo SupportService (registra a conversa).
 */
class SupportTicket extends Model
{
    use BelongsToTenant;

    public const CATEGORIES = ['duvida', 'problema', 'producao', 'financeiro', 'redes', 'outro'];

    public const PRIORITIES = ['baixa', 'normal', 'alta', 'urgente'];

    public const STATUSES = ['aberto', 'em_andamento', 'aguardando_cliente', 'resolvido', 'fechado'];

    public const OPEN_STATUSES = ['aberto', 'em_andamento', 'aguardando_cliente'];

    protected $fillable = ['tenant_id', 'cliente_id', 'subject', 'category', 'priority', 'source'];

    protected $attributes = [
        'category' => 'duvida',
        'priority' => 'normal',
        'status' => 'aberto',
        'source' => 'portal',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function categoryLabels(): array
    {
        return [
            'duvida' => __('Dúvida'),
            'problema' => __('Problema na plataforma'),
            'producao' => __('Produção e vídeos'),
            'financeiro' => __('Financeiro e faturas'),
            'redes' => __('Redes sociais e publicação'),
            'outro' => __('Outro assunto'),
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'aberto' => __('Aberto'),
            'em_andamento' => __('Em andamento'),
            'aguardando_cliente' => __('Aguardando você'),
            'resolvido' => __('Resolvido'),
            'fechado' => __('Fechado'),
        ];
    }

    public static function priorityLabels(): array
    {
        return ['baixa' => __('Baixa'), 'normal' => __('Normal'), 'alta' => __('Alta'), 'urgente' => __('Urgente')];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Só o que o cliente pode ver (sem notas internas).
     */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('internal', false);
    }
}
