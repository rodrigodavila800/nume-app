<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoPublication extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'video_id',
        'user_id',
        'upload_post_request_id',
        'driver',
        'platform',
        'social_connection_id',
        'external_id',
        'titulo',
        'plataformas',
        'status',
        'attempts',
        'last_error',
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

    public function socialConnection(): BelongsTo
    {
        return $this->belongsTo(SocialConnection::class);
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
     * Explicação para o cliente do motivo de uma falha do envio direto (null quando não há).
     */
    public function failureHint(): ?string
    {
        return match ($this->last_error) {
            'reconnect_required' => __('Reconecte a conta para publicar.'),
            'disconnected' => __('Cancelado: a conta foi desconectada.'),
            'reel_format_invalid' => __('O vídeo precisa ser vertical (9:16) e ter pelo menos 540x960 para virar Reel.'),
            'reel_duration_invalid' => __('Reels do Facebook precisam ter entre 3 e 90 segundos.'),
            'publish_limit_reached', 'quotaExceeded' => __('A rede atingiu o limite diário de publicações. Tente amanhã.'),
            'media_processing_failed' => __('A rede não conseguiu processar o vídeo. Confira o formato do arquivo.'),
            'source_missing' => __('O arquivo do vídeo não está mais disponível.'),
            'unaudited_client_can_only_post_to_private_accounts' => __('Enquanto o app da NUME não for auditado pelo TikTok, só dá para publicar em conta privada, como "Somente eu".'),
            'tiktok_duration_invalid' => __('O vídeo é mais longo do que o TikTok permite para esta conta.'),
            'privacy_level_option_mismatch' => __('A privacidade escolhida não está mais disponível nesta conta do TikTok. Publique de novo.'),
            'spam_risk_user_banned_from_posting' => __('O TikTok bloqueou publicações desta conta. Verifique a conta no aplicativo do TikTok.'),
            'linkedin_file_size_invalid' => __('O LinkedIn aceita vídeos de 75 KB a 500 MB.'),
            'linkedin_duration_invalid' => __('O LinkedIn aceita vídeos de 3 segundos a 30 minutos.'),
            default => null,
        };
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
