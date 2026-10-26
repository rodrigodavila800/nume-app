<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SocialPublisherContract;
use App\Enums\PlataformaAlvo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\PublishingLimitException;
use App\Services\SocialPublishing\PublishingLimits;
use App\Services\SocialPublishing\SocialPublicationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SocialApiController extends Controller
{
    public function __construct(
        protected readonly SocialPublisherContract $publisher
    ) {}

    /**
     * Gera URL de autorização White-label para o cliente final conectar suas redes sociais.
     */
    public function connectUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'redirect_url' => ['nullable', 'url'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? app('current_tenant');

        // Localiza ou provisiona o cliente de forma transparente dentro do Tenant
        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->first();

        if (! $cliente) {
            $cliente = Cliente::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'] ?? explode('@', $validated['email'])[0],
                'email' => $validated['email'],
                'password' => bcrypt(Str::random(32)),
                'status' => 'active',
            ]);
        }

        $profile = $this->publisher->ensureProfile($cliente);
        $connectUrl = $this->publisher->generateConnectUrl($cliente, $validated['redirect_url'] ?? null);

        return response()->json([
            'status' => 'success',
            'data' => [
                'client_id' => $cliente->id,
                'email' => $cliente->email,
                'connected' => (bool) $profile->connected,
                'platforms_connected' => $profile->platforms_connected ?? [],
                'connect_url' => $connectUrl,
            ],
        ]);
    }

    /**
     * Consulta o perfil social e as redes conectadas de um cliente.
     */
    public function profile(Request $request, int|string $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? app('current_tenant');

        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', $id);
                } else {
                    $q->where('email', $id);
                }
            })
            ->first();

        if (! $cliente) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Cliente não encontrado neste Tenant.',
            ], 404);
        }

        $profile = $this->publisher->ensureProfile($cliente);

        return response()->json([
            'status' => 'success',
            'data' => [
                'client_id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'connected' => (bool) $profile->connected,
                'platforms_connected' => $profile->platforms_connected ?? [],
                'last_synced_at' => $profile->last_synced_at?->toIso8601String(),
                'quota' => [
                    'has_unlimited_addon' => $cliente->hasDistributionAddon(),
                    'remaining_quota' => $cliente->getRemainingDistributionQuota(),
                    'used_count' => $cliente->getDistributionsUsedCount(),
                ],
                // Liberados pelo admin da NUME (null = sem limite)
                'limits' => app(PublishingLimits::class)->summary($cliente),
            ],
        ]);
    }

    /**
     * Dispara a publicação de um vídeo do cliente nas redes pedidas.
     *
     * Passa pelo motor único (SocialPublicationService::distribute): rede conectada pelo app da NUME
     * envia o arquivo hospedado pela fila social-uploads; URL digitada só vale para vídeo que é só link.
     */
    public function publish(Request $request, SocialPublicationService $publications): JsonResponse
    {
        $platformValues = array_column(PlataformaAlvo::cases(), 'value');

        $validated = $request->validate([
            'client_id' => ['required_without:email', 'nullable', 'integer'],
            'email' => ['required_without:client_id', 'nullable', 'email'],
            'video_id' => ['required', 'integer'],
            'video_url' => ['nullable', 'url:https'],
            'title' => ['required', 'string', 'max:255'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string', Rule::in($platformValues)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? app('current_tenant');

        $cliente = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->when(! empty($validated['client_id']), fn ($q) => $q->where('id', $validated['client_id']))
            ->when(empty($validated['client_id']), fn ($q) => $q->where('email', $validated['email']))
            ->first();

        if (! $cliente) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Cliente informado não existe no Tenant ativo.',
            ], 404);
        }

        // Valida governança de quota ou add-on
        if (! $cliente->canDistributeSocial()) {
            return response()->json([
                'error' => 'Quota Exceeded',
                'message' => 'O cliente atingiu o limite de publicações do pacote contratado. Ative o Add-on de Distribuição Ilimitada para continuar.',
                'quota' => [
                    'remaining' => 0,
                    'used' => $cliente->getDistributionsUsedCount(),
                ],
            ], 403);
        }

        // Só vídeo de produção deste cliente, no tenant da chave.
        $video = Video::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('production', fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('cliente_id', $cliente->id))
            ->find($validated['video_id']);

        if (! $video) {
            return response()->json(['error' => 'Not Found', 'message' => 'Vídeo não encontrado para este cliente.'], 404);
        }

        // Arquivo hospedado pela NUME só sai com o saldo do pacote quitado (mesma regra do portal).
        if ($video->hasSourceFile() && $video->isAssetLocked()) {
            return response()->json(['error' => 'Payment Required', 'message' => 'O saldo do pacote ainda não foi quitado.'], 402);
        }

        $scheduledAt = ! empty($validated['scheduled_at']) ? new \DateTimeImmutable($validated['scheduled_at']) : null;

        try {
            $result = $publications->distribute(
                cliente: $cliente,
                video: $video,
                title: $validated['title'],
                platforms: $validated['platforms'],
                user: null,
                scheduledAt: $scheduledAt,
                linkUrl: $video->hasSourceFile() ? null : ($validated['video_url'] ?? null),
            );
        } catch (PublishingLimitException $e) {
            return response()->json(['error' => 'Limit Reached', 'reason' => $e->reason, 'message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return response()->json(['error' => 'Unprocessable', 'message' => 'Vídeo sem arquivo hospedado: envie video_url (https) para este vídeo.', 'reason' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'publications' => array_map(fn (VideoPublication $p) => [
                    'publication_id' => $p->id,
                    'platforms' => $p->plataformas,
                    'status' => $p->status,
                    'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                ], $result['publications']),
                // Redes que exigem a tela própria do portal (escolhas do cliente a cada post).
                'requires_portal' => $result['composer'],
                'title' => $validated['title'],
                'video_id' => $video->id,
            ],
        ], 201);
    }

    /**
     * Consulta o status de uma publicação em andamento.
     */
    public function showPublication(Request $request, int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? app('current_tenant');

        $publication = VideoPublication::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->first();

        if (! $publication) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Publicação não encontrada neste Tenant.',
            ], 404);
        }

        // Atualiza status server-to-server
        $publication = $this->publisher->refreshStatus($publication);

        return response()->json([
            'status' => 'success',
            'data' => [
                'publication_id' => $publication->id,
                'title' => $publication->titulo,
                'status' => $publication->status,
                'platforms' => $publication->plataformas,
                'request_id' => $publication->upload_post_request_id,
                'published_at' => $publication->publicado_em?->toIso8601String(),
                'result' => $publication->resultado,
                'created_at' => $publication->created_at->toIso8601String(),
            ],
        ]);
    }
}
