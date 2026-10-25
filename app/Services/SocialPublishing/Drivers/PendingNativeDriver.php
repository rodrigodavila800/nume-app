<?php

namespace App\Services\SocialPublishing\Drivers;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\SocialProfile;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Support\Str;

/**
 * Base dos drivers nativos que ainda não publicam por esta interface (o app da rede ainda não foi
 * aprovado ou a publicação nativa passa pelo job PublishVideoToPlatform).
 *
 * Regra: nunca fingir. Não marca conta como conectada sem uma SocialConnection ativa, não
 * transforma "processando" em "publicado" sem confirmação da rede e não usa token global.
 */
abstract class PendingNativeDriver implements SocialDriverInterface
{
    abstract protected function profilePrefix(): string;

    public function ensureProfile(Cliente $cliente): SocialProfile
    {
        return SocialProfile::forTenant($cliente->tenant_id)->firstOrCreate(
            ['cliente_id' => $cliente->id],
            [
                'tenant_id' => $cliente->tenant_id,
                'upload_post_username' => $this->profilePrefix().'_'.Str::uuid(),
                'connected' => false,
                'platforms_connected' => [],
            ]
        );
    }

    public function generateConnectUrl(Cliente $cliente, ?string $redirectUrl = null): string
    {
        return route('portal.social.index', ['driver' => $this->getName(), 'status' => 'pending_config']);
    }

    /**
     * Só as redes deste driver com conta conectada de verdade (social_connections ativas).
     */
    public function syncConnectionStatus(Cliente $cliente): SocialProfile
    {
        $profile = $this->ensureProfile($cliente);

        $native = SocialConnection::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->whereIn('platform', $this->supportedPlatforms())
            ->active()
            ->pluck('platform')
            ->all();

        $others = array_diff($profile->platforms_connected ?? [], $this->supportedPlatforms());
        $platforms = array_values(array_unique(array_merge($others, $native)));

        $profile->update([
            'connected' => $platforms !== [],
            'platforms_connected' => $platforms,
            'last_synced_at' => now(),
        ]);

        return $profile->fresh();
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
        return VideoPublication::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'video_id' => $video?->id,
            'user_id' => $user?->id,
            'driver' => $this->getName(),
            'titulo' => $title,
            'plataformas' => array_values(array_intersect($platforms, $this->supportedPlatforms())) ?: $this->supportedPlatforms(),
            'status' => 'falhou',
            'last_error' => 'driver_unavailable',
            'resultado' => ['driver' => $this->getName(), 'error' => 'driver_unavailable'],
        ]);
    }

    public function refreshStatus(VideoPublication $publication): VideoPublication
    {
        return $publication;
    }

    public function processScheduled(): int
    {
        return 0;
    }
}
