<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoPublication extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'video_id',
        'user_id',
        'upload_post_request_id',
        'titulo',
        'plataformas',
        'status',
        'scheduled_at',
        'resultado',
        'publicado_em',
    ];

    protected $casts = [
        'plataformas' => 'array',
        'resultado' => 'array',
        'scheduled_at' => 'datetime',
        'publicado_em' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'publicado';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'agendado';
    }

    public function isPendingOrProcessing(): bool
    {
        return in_array($this->status, ['pendente', 'processando', 'agendado']);
    }

    /**
     * Scope para recuperar publicações agendadas prontas para envio imediato.
     */
    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'agendado')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }
}
