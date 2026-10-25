<?php

namespace App\Services\SocialPublishing;

use App\Jobs\PublishVideoToPlatform;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\UploadPost\SocialDistributionService;
use DomainException;

/**
 * Ponto único de disparo de publicação (portal, painel da produtora e agendador).
 *
 * Redes com conta conectada no app próprio viram uma VideoPublication por rede e vão para a fila
 * (PublishVideoToPlatform); o restante segue numa publicação do Upload-Post, como antes.
 * Publicação nativa só existe para vídeo hospedado na NUME: vídeo que é só link vai pelo Upload-Post.
 */
class SocialPublicationService
{
    public function __construct(
        private readonly SocialPublisherRouter $router,
        private readonly SocialDistributionService $uploadPost,
    ) {}

    /**
     * @param  list<string>  $platforms
     * @return array{publications: list<VideoPublication>, native: list<string>, upload_post: list<string>}
     *
     * @throws DomainException missing_video_url quando o Upload-Post precisaria de uma URL e não há
     */
    public function distribute(
        Cliente $cliente,
        Video $video,
        string $title,
        array $platforms,
        ?User $user = null,
        ?\DateTimeInterface $scheduledAt = null,
        ?string $linkUrl = null,
    ): array {
        $plan = $this->router->route($cliente, $platforms);

        if (! $video->hasSourceFile()) {
            $plan['upload_post'] = array_values(array_unique(array_merge(array_keys($plan['native']), $plan['upload_post'])));
            $plan['native'] = [];
        }

        $videoUrl = null;
        if ($plan['upload_post'] !== []) {
            // Arquivo próprio: URL assinada gerada aqui. A URL digitada só vale para vídeo que é apenas um link.
            $videoUrl = $video->hasSourceFile() ? $video->publicFetchUrl() : ($linkUrl ?: $video->video_url);
            if (! $videoUrl) {
                throw new DomainException('missing_video_url');
            }
        }

        $scheduled = $scheduledAt !== null && $scheduledAt > now();
        $publications = [];

        foreach ($plan['native'] as $platform => $connection) {
            $publication = VideoPublication::create([
                'tenant_id' => $cliente->tenant_id,
                'cliente_id' => $cliente->id,
                'video_id' => $video->id,
                'user_id' => $user?->id,
                'driver' => $platform,
                'platform' => $platform,
                'social_connection_id' => $connection->id,
                'titulo' => $title,
                'plataformas' => [$platform],
                'status' => $scheduled ? 'agendado' : 'pendente',
                'scheduled_at' => $scheduled ? $scheduledAt : null,
                'resultado' => ['driver' => $platform.'_native'],
            ]);

            if (! $scheduled) {
                PublishVideoToPlatform::dispatch($publication->id, $publication->tenant_id);
            }

            $publications[] = $publication;
        }

        if ($plan['upload_post'] !== []) {
            $publications[] = $this->uploadPost->publish(
                cliente: $cliente,
                videoUrl: $videoUrl,
                title: $title,
                platforms: $plan['upload_post'],
                video: $video,
                user: $user,
                scheduledAt: $scheduledAt,
            );
        }

        return [
            'publications' => $publications,
            'native' => array_keys($plan['native']),
            'upload_post' => $plan['upload_post'],
        ];
    }

    /**
     * Agendamentos nativos que chegaram na hora: cada um é reservado (agendado -> pendente) uma única
     * vez antes de ir para a fila, então duas execuções do agendador não duplicam o envio.
     */
    public function dispatchDueNative(): int
    {
        $due = VideoPublication::withoutGlobalScopes()
            ->readyToPublish()
            ->where('driver', '!=', 'upload_post')
            ->get(['id', 'tenant_id']);

        $dispatched = 0;
        foreach ($due as $publication) {
            $claimed = VideoPublication::withoutGlobalScopes()
                ->whereKey($publication->id)
                ->where('status', 'agendado')
                ->update(['status' => 'pendente']);

            if ($claimed === 1) {
                PublishVideoToPlatform::dispatch($publication->id, $publication->tenant_id);
                $dispatched++;
            }
        }

        return $dispatched;
    }
}
