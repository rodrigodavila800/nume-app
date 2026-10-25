<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use App\Traits\BelongsToTenant;
use App\Traits\HasEmbeddableVideo;

class Video extends Model
{
    use HasFactory, BelongsToTenant, HasEmbeddableVideo;

    protected $fillable = [
        'tenant_id',
        'production_id',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'source_disk',
        'source_path',
        'source_mime',
        'source_size',
        'source_width',
        'source_height',
        'source_sha256',
        'duration_seconds',
        'status',
        'platform',
        'platform_video_id',
        'published_at',
        // Sofi SEO Pack
        'sofi_seo_title',
        'sofi_seo_description',
        'sofi_hashtags',
        'sofi_seo_keywords',
        'sofi_seo_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at'          => 'datetime',
            'sofi_hashtags'         => 'array',
            'sofi_seo_generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // O arquivo só é apagado depois que o registro saiu do banco.
        static::deleted(function (Video $video) {
            $video->deleteSourceFile();
        });
    }

    /**
     * O vídeo tem arquivo próprio da NUME (enviado), e não só um link externo.
     */
    public function hasSourceFile(): bool
    {
        return filled($this->source_path) && filled($this->source_disk);
    }

    /**
     * Arquivo realmente presente no disco (hasSourceFile() só olha o registro).
     */
    public function sourceFileExists(): bool
    {
        return $this->hasSourceFile() && Storage::disk($this->source_disk)->exists($this->source_path);
    }

    /**
     * Remove o arquivo enviado e limpa os campos do registro (sem apagar o vídeo).
     */
    public function deleteSourceFile(): void
    {
        if ($this->hasSourceFile()) {
            Storage::disk($this->source_disk)->delete($this->source_path);
        }
    }

    /**
     * Relacionamento: Video pertence a uma Production
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    /**
     * Obtém o roteiro associado via production
     */
    public function roteiro()
    {
        return $this->production->roteiros()->where('status', 'approved')->first();
    }

    /**
     * Marca vídeo como pronto
     */
    public function markAsReady(): bool
    {
        return $this->update(['status' => 'ready']);
    }

    /**
     * Publica o vídeo
     */
    public function publish(): bool
    {
        if ($this->status !== 'ready') {
            return false;
        }

        return $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Verifica se vídeo foi publicado
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function publications(): HasMany
    {
        return $this->hasMany(VideoPublication::class)->orderBy('created_at', 'desc');
    }

    public function latestPublication(): HasOne
    {
        return $this->hasOne(VideoPublication::class)->latestOfMany();
    }

    /**
     * Gera Magic Link assinado para aprovação do vídeo final sem login/senha.
     */
    public function getMagicApprovalUrl(int $days = 14): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'magic.videos.show',
            now()->addDays($days),
            ['video' => $this->id]
        );
    }

    /**
     * Gera URL pronta de envio via WhatsApp com link mágico assinado do vídeo final.
     */
    public function getWhatsAppShareUrl(int $days = 14): string
    {
        $url = $this->getMagicApprovalUrl($days);
        $clienteName = $this->production?->cliente?->name ?? 'Cliente';
        $title = $this->title ?: ($this->production?->title ?? 'Vídeo Final');

        $text = "Olá {$clienteName}! O corte final do vídeo *{$title}* está disponível para visualização e aprovação com 1 clique:\n\n{$url}";

        return 'https://api.whatsapp.com/send?text=' . urlencode($text);
    }

    /**
     * Verifica se os ativos deste vídeo estão retidos em custódia (Asset Escrow),
     * ou seja, compra no modelo 50/50 aguardando quitação do saldo de entrega.
     */
    public function isAssetLocked(): bool
    {
        $purchase = $this->production?->packPurchase;

        if (! $purchase) {
            $purchase = $this->production?->cliente?->packPurchases()
                ->where('status', 'paid')
                ->latest()
                ->first();
        }

        if (! $purchase) {
            return false;
        }

        return $purchase->isAssetLocked();
    }
}
