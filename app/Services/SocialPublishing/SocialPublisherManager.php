<?php

namespace App\Services\SocialPublishing;

use App\Contracts\SocialPublisherContract;
use App\Models\Cliente;
use App\Models\SocialProfile;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\Drivers\MetaGraphDriver;
use App\Services\SocialPublishing\Drivers\SocialDriverInterface;
use App\Services\SocialPublishing\Drivers\TikTokDriver;
use App\Services\SocialPublishing\Drivers\UploadPostDriver;
use App\Services\SocialPublishing\Drivers\YouTubeDataDriver;
use App\Services\UploadPost\SocialDistributionService;
use InvalidArgumentException;

/**
 * Gerenciador Multi-driver de Publicação Social da NUME.
 * Implementa o padrão Driver/Manager do Laravel garantindo Zero Vendor Lock-in:
 * - Driver 'upload_post': Provedor unificado multi-rede (Upload-Post / Ayrshare)
 * - Driver 'meta_graph': Driver proprietário direto da Meta (Instagram / Facebook)
 * - Driver 'youtube_data': Driver proprietário direto do Google YouTube
 * - Driver 'tiktok_native': Driver proprietário direto do TikTok Open API
 */
class SocialPublisherManager implements SocialPublisherContract
{
    /** @var array<string, SocialDriverInterface> */
    protected array $drivers = [];

    public function __construct(
        protected readonly SocialDistributionService $uploadPostService
    ) {
    }

    /**
     * Obtém uma instância do driver especificado ou o padrão do sistema.
     */
    public function driver(?string $name = null): SocialDriverInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Retorna o nome do driver padrão configurado.
     */
    public function getDefaultDriver(): string
    {
        return config('upload_post.driver', 'upload_post');
    }

    /**
     * Instancia o driver correspondente.
     */
    protected function createDriver(string $name): SocialDriverInterface
    {
        return match ($name) {
            'upload_post' => new UploadPostDriver($this->uploadPostService),
            'meta', 'meta_graph' => new MetaGraphDriver(),
            'youtube', 'youtube_data' => new YouTubeDataDriver(),
            'tiktok', 'tiktok_native' => new TikTokDriver(),
            default => throw new InvalidArgumentException("Driver social [{$name}] não suportado pelo Nume Social Engine."),
        };
    }

    public function ensureProfile(Cliente $cliente): SocialProfile
    {
        return $this->driver()->ensureProfile($cliente);
    }

    public function generateConnectUrl(Cliente $cliente, ?string $redirectUrl = null): string
    {
        return $this->driver()->generateConnectUrl($cliente, $redirectUrl);
    }

    public function syncConnectionStatus(Cliente $cliente): SocialProfile
    {
        return $this->driver()->syncConnectionStatus($cliente);
    }

    public function publish(
        Cliente $cliente,
        string $videoUrl,
        string $title,
        array $platforms,
        ?Video $video = null,
        ?User $user = null,
        ?\DateTimeInterface $scheduledAt = null
    ): VideoPublication {
        return $this->driver()->publish($cliente, $videoUrl, $title, $platforms, $video, $user, $scheduledAt);
    }

    public function refreshStatus(VideoPublication $publication): VideoPublication
    {
        // Se a publicação gravou nos metadados qual driver foi usado, utiliza-o
        $driverName = $publication->resultado['driver'] ?? $this->getDefaultDriver();
        try {
            return $this->driver($driverName)->refreshStatus($publication);
        } catch (\Throwable) {
            return $this->driver()->refreshStatus($publication);
        }
    }

    public function processScheduled(): int
    {
        return $this->driver()->processScheduled();
    }
}
