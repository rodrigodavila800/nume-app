<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\Cliente;
use App\Models\SocialProfile;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\UploadPost\SocialDistributionService;

class UploadPostDriver implements SocialDriverInterface
{
    public function __construct(private readonly SocialDistributionService $service)
    {
    }

    public function getName(): string
    {
        return 'upload_post';
    }

    public function supportedPlatforms(): array
    {
        return [
            'instagram',
            'youtube',
            'tiktok',
            'facebook',
            'linkedin',
            'x',
            'threads',
            'pinterest',
        ];
    }

    public function ensureProfile(Cliente $cliente): SocialProfile
    {
        return $this->service->ensureProfile($cliente);
    }

    public function generateConnectUrl(Cliente $cliente, ?string $redirectUrl = null): string
    {
        return $this->service->generateConnectUrl($cliente, $redirectUrl);
    }

    public function syncConnectionStatus(Cliente $cliente): SocialProfile
    {
        return $this->service->syncConnectionStatus($cliente);
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
        return $this->service->publish($cliente, $videoUrl, $title, $platforms, $video, $user, $scheduledAt);
    }

    public function refreshStatus(VideoPublication $publication): VideoPublication
    {
        return $this->service->refreshStatus($publication);
    }

    public function processScheduled(): int
    {
        return $this->service->processScheduled();
    }
}
