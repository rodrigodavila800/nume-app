<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensagem do chamado. author_type: user (equipe), cliente ou system. Nota interna nunca vai ao portal.
 */
class SupportTicketMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'support_ticket_id', 'author_type', 'author_id', 'body', 'internal'];

    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function authorName(): string
    {
        return match ($this->author_type) {
            'user' => User::query()->whereKey($this->author_id)->value('name') ?? __('Equipe'),
            'cliente' => __('Cliente'),
            default => __('Sistema'),
        };
    }
}
