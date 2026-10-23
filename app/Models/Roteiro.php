<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Str;

class Roteiro extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'production_id',
        'created_by',
        'approved_by',
        'approved_by_cliente_id',
        'content',
        'status',
        'rejection_reason',
        'submitted_at',
        'last_reminded_at',
        'reminder_count',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Roteiro $roteiro) {
            if (empty($roteiro->uuid)) {
                $roteiro->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Relacionamento: Roteiro pertence a uma Production
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    /**
     * Relacionamento: Roteiro foi criado por um User
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relacionamento: Roteiro foi aprovado por um User
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relacionamento: Roteiro foi aprovado por um Cliente
     */
    public function approverCliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'approved_by_cliente_id');
    }

    /**
     * Submete o roteiro para aprovação
     */
    public function submit(): bool
    {
        if ($this->status !== 'draft') {
            return false;
        }

        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return true;
    }

    /**
     * Aprova o roteiro
     */
    public function approve(User $approver): bool
    {
        if ($this->status !== 'submitted') {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return true;
    }

    /**
     * Rejeita o roteiro
     */
    public function reject(User $approver, string $reason): bool
    {
        if ($this->status !== 'submitted') {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'rejection_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Aprova o roteiro em nome do Cliente (comprador do pacote)
     */
    public function approveByCliente(Cliente $cliente): bool
    {
        if ($this->status !== 'submitted') {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by_cliente_id' => $cliente->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        return true;
    }

    /**
     * Rejeita o roteiro em nome do Cliente (comprador do pacote)
     */
    public function rejectByCliente(Cliente $cliente, string $reason): bool
    {
        if ($this->status !== 'submitted') {
            return false;
        }

        $this->update([
            'status' => 'rejected',
            'approved_by_cliente_id' => $cliente->id,
            'rejection_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Marca como em produção
     */
    public function markAsInProduction(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        return $this->update(['status' => 'in_production']);
    }

    /**
     * Gera Magic Link assinado para aprovação do cliente sem login/senha.
     */
    public function getMagicApprovalUrl(int $days = 14): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'magic.roteiros.show',
            now()->addDays($days),
            ['roteiro' => $this->uuid ?: $this->id]
        );
    }

    /**
     * Gera URL pronta de envio via WhatsApp com link mágico assinado.
     */
    public function getWhatsAppShareUrl(int $days = 14): string
    {
        $url = $this->getMagicApprovalUrl($days);
        $clienteName = $this->production?->cliente?->name ?? 'Cliente';
        $title = $this->production?->title ?? 'Vídeo';

        $text = "Olá {$clienteName}! O roteiro da produção *{$title}* está pronto para sua revisão. Clique no link para aprovar em 1 toque sem precisar de senha:\n\n{$url}";

        return 'https://api.whatsapp.com/send?text=' . urlencode($text);
    }
}
