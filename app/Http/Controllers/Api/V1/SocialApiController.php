<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SocialPublisherContract;
use App\Enums\PlataformaAlvo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SocialApiController extends Controller
{
    public function __construct(
        protected readonly SocialPublisherContract $publisher
    ) {
    }

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
            ],
        ]);
    }

    /**
     * Dispara a publicação imediata de um vídeo nas redes sociais especificadas.
     */
    public function publish(Request $request): JsonResponse
    {
        $platformValues = array_column(PlataformaAlvo::cases(), 'value');

        $validated = $request->validate([
            'client_id' => ['required_without:email', 'nullable', 'integer'],
            'email' => ['required_without:client_id', 'nullable', 'email'],
            'video_url' => ['required', 'url'],
            'title' => ['required', 'string', 'max:255'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string', Rule::in($platformValues)],
            'video_id' => ['nullable', 'integer'],
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

        $video = null;
        if (! empty($validated['video_id'])) {
            $video = Video::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('id', $validated['video_id'])
                ->first();
        }

        $scheduledAt = ! empty($validated['scheduled_at']) ? new \DateTimeImmutable($validated['scheduled_at']) : null;

        $publication = $this->publisher->publish(
            cliente: $cliente,
            videoUrl: $validated['video_url'],
            title: $validated['title'],
            platforms: $validated['platforms'],
            video: $video,
            user: null,
            scheduledAt: $scheduledAt
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'publication_id' => $publication->id,
                'request_id' => $publication->upload_post_request_id,
                'title' => $publication->titulo,
                'platforms' => $publication->plataformas,
                'status' => $publication->status,
                'scheduled_at' => $publication->scheduled_at?->toIso8601String(),
                'created_at' => $publication->created_at->toIso8601String(),
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
