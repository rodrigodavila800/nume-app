<?php

namespace App\Services\UploadPost;

use App\Contracts\SocialPublisherContract;
use App\Models\Cliente;
use App\Models\SocialProfile;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Support\Str;

/**
 * Orquestra o fluxo de distribuição social dentro da NUME:
 * - Mantém o SocialProfile do Cliente isolado por Tenant
 * - Gera URLs seguras de conexão White-label
 * - Sincroniza status das plataformas conectadas server-to-server
 * - Dispara uploads integrados ao Video e ao Sofi SEO Pack
 */
class SocialDistributionService implements SocialPublisherContract
{
    public function __construct(private readonly UploadPostClient $client)
    {
    }

    public function getClient(): UploadPostClient
    {
        return $this->client;
    }

    /**
     * Garante a existência do perfil social para o cliente informado.
     */
    public function ensureProfile(Cliente $cliente): SocialProfile
    {
        $existing = $cliente->socialProfile ?: SocialProfile::withoutGlobalScopes()
            ->where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->createProfile($cliente);
    }

    private function createProfile(Cliente $cliente): SocialProfile
    {
        // UUID interno seguro — nunca envia e-mail ou documento do cliente
        $username = (string) Str::uuid();

        $this->client->createUserProfile($username);

        return SocialProfile::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'upload_post_username' => $username,
            'connected' => false,
            'platforms_connected' => [],
        ]);
    }

    /**
     * Gera a URL JWT segura para o cliente conectar as redes sociais dele.
     */
    public function generateConnectUrl(Cliente $cliente, ?string $redirectUrl = null): string
    {
        $profile = $this->ensureProfile($cliente);

        return $this->client->generateConnectUrl(
            username: $profile->upload_post_username,
            redirectUrl: $redirectUrl,
            logoImage: config('upload_post.logo_image'),
        );
    }

    public function connectUrlFor(Cliente $cliente, ?string $redirectUrl = null): string
    {
        return $this->generateConnectUrl($cliente, $redirectUrl);
    }

    /**
     * Revalida direto na API se o perfil está conectado e quais plataformas
     * têm conta vinculada. Chamado no retorno de conexão (redirect) e sob demanda.
     */
    public function syncConnectionStatus(Cliente $cliente): SocialProfile
    {
        $profile = $this->ensureProfile($cliente);

        $remote = $this->client->getUserProfile($profile->upload_post_username);

        $accounts = $remote['social_accounts'] ?? [];
        $connectedPlatforms = ! empty($accounts) ? array_keys($accounts) : [];

        $profile->update([
            'connected' => ! empty($connectedPlatforms),
            'platforms_connected' => $connectedPlatforms,
            'last_synced_at' => now(),
        ]);

        return $profile->fresh();
    }

    /**
     * Publica um vídeo nas redes sociais conectadas do cliente.
     *
     * @param  string[]  $platforms
     */
    public function publish(
        Cliente $cliente,
        string $videoUrl,
        string $title,
        array $platforms,
        ?Video $video = null,
        ?User $user = null,
        ?\DateTimeInterface $scheduledAt = null,
    ): VideoPublication {
        $profile = $this->ensureProfile($cliente);

        // Se houver data de agendamento no futuro, registra como 'agendado' para disparo posterior via Scheduler
        if ($scheduledAt && $scheduledAt > now()) {
            return VideoPublication::create([
                'tenant_id' => $cliente->tenant_id,
                'cliente_id' => $cliente->id,
                'video_id' => $video?->id,
                'user_id' => $user?->id,
                'upload_post_request_id' => null,
                'titulo' => $title,
                'plataformas' => $platforms,
                'status' => 'agendado',
                'scheduled_at' => $scheduledAt,
                'resultado' => [
                    'scheduled' => true,
                    // Arquivo próprio: a URL assinada expira, então não é guardada (gerada de novo no disparo).
                    'video_url' => $video?->hasSourceFile() ? null : $videoUrl,
                    'video_source' => $video?->hasSourceFile() ? 'file' : 'url',
                    'scheduled_for' => $scheduledAt->format('Y-m-d H:i:s'),
                ],
            ]);
        }

        $result = $this->client->uploadVideo(
            username: $profile->upload_post_username,
            videoUrl: $videoUrl,
            title: $title,
            platforms: $platforms,
        );

        $publication = VideoPublication::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'video_id' => $video?->id,
            'user_id' => $user?->id,
            'upload_post_request_id' => $result['request_id'] ?? null,
            'titulo' => $title,
            'plataformas' => $platforms,
            'status' => 'processando',
            'scheduled_at' => null,
            'resultado' => $result,
        ]);

        // Se o vídeo existir e ainda não foi marcado como publicado, sincroniza
        if ($video && $video->status === 'ready') {
            $video->update([
                'status' => 'published',
                'published_at' => now(),
                'platform' => implode(', ', $platforms),
            ]);
        }

        return $publication;
    }

    /**
     * Processa todas as publicações com horário agendado atingido.
     */
    public function processScheduled(): int
    {
        $pending = VideoPublication::withoutGlobalScopes()
            ->readyToPublish()
            ->with(['cliente', 'video'])
            ->get();

        $processed = 0;

        foreach ($pending as $publication) {
            $cliente = $publication->cliente;
            if (! $cliente) {
                continue;
            }

            $profile = $this->ensureProfile($cliente);
            // Arquivo próprio da NUME: URL assinada nova (a do agendamento já teria expirado).
            $videoUrl = $publication->video?->hasSourceFile()
                ? $publication->video->publicFetchUrl()
                : ($publication->resultado['video_url'] ?? $publication->video?->video_url);

            if (! $videoUrl) {
                $publication->update([
                    'status' => 'falhou',
                    'resultado' => array_merge($publication->resultado ?? [], ['error' => 'URL do vídeo não encontrada']),
                ]);
                continue;
            }

            try {
                $result = $this->client->uploadVideo(
                    username: $profile->upload_post_username,
                    videoUrl: $videoUrl,
                    title: $publication->titulo,
                    platforms: $publication->plataformas ?? ['instagram'],
                );

                $publication->update([
                    'status' => 'processando',
                    'upload_post_request_id' => $result['request_id'] ?? null,
                    'resultado' => $result,
                ]);

                if ($publication->video && $publication->video->status === 'ready') {
                    $publication->video->update([
                        'status' => 'published',
                        'published_at' => now(),
                        'platform' => implode(', ', $publication->plataformas ?? []),
                    ]);
                }

                $processed++;
            } catch (\Throwable $e) {
                $publication->update([
                    'status' => 'falhou',
                    'resultado' => array_merge($publication->resultado ?? [], ['exception' => $e->getMessage()]),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Atualiza o status assíncrono da publicação consultando a API.
     */
    public function refreshStatus(VideoPublication $publication): VideoPublication
    {
        if (! $publication->upload_post_request_id) {
            return $publication;
        }

        $status = $this->client->getUploadStatus($publication->upload_post_request_id);

        $mappedStatus = $this->mapStatus($status);

        $publication->update([
            'status' => $mappedStatus,
            'resultado' => $status,
            'publicado_em' => $mappedStatus === 'publicado' ? ($publication->publicado_em ?? now()) : null,
        ]);

        return $publication->fresh();
    }

    private function mapStatus(array $status): string
    {
        return match ($status['status'] ?? null) {
            'completed' => 'publicado',
            'failed' => 'falhou',
            default => 'processando',
        };
    }
}
