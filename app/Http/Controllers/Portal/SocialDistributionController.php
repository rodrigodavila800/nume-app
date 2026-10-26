<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PlataformaAlvo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PublishVideoRequest;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\SocialPublicationService;
use App\Services\SocialPublishing\SocialPublisherRouter;
use App\Services\SocialPublishing\Connectors\ConnectorRegistry;
use App\Services\UploadPost\SocialDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialDistributionController extends Controller
{
    public function __construct(
        private readonly SocialDistributionService $service,
        private readonly SocialPublicationService $publications,
        private readonly ConnectorRegistry $connectors,
        private readonly SocialPublisherRouter $router,
    ) {
    }

    /**
     * Tela principal de Redes Sociais no Portal do Cliente.
     */
    public function index(): View
    {
        $cliente = auth('cliente')->user();
        $profile = $this->service->ensureProfile($cliente);
        $publications = $cliente->videoPublications()
            ->with(['video.production'])
            ->latest()
            ->paginate(10);

        $platforms = PlataformaAlvo::cases();

        // Contas conectadas pelo app próprio (a mais recente por rede), sem tokens na view.
        $connections = $cliente->socialConnections()->latest('id')->get()->unique('platform')->keyBy('platform');
        $connectedPlatforms = collect($platforms)
            ->filter(fn (PlataformaAlvo $platform) => $profile->isPlatformConnected($platform->value)
                || $connections->get($platform->value)?->isActive())
            ->map(fn (PlataformaAlvo $platform) => $platform->value)
            ->values();
        // Rede => chave do conector que a conecta direto (só quando configurado e liberado).
        $nativeAvailable = [];
        foreach (config('social.platform_connectors', []) as $platform => $key) {
            $connector = $this->connectors->for($key);
            if ($connector?->isConfigured() && $this->router->routeFor($platform) === SocialPublisherRouter::NATIVE) {
                $nativeAvailable[$platform] = $key;
            }
        }

        $limits = app(\App\Services\SocialPublishing\PublishingLimits::class)->summary($cliente);

        return view('portal.social.index', compact('cliente', 'profile', 'publications', 'platforms', 'connections', 'connectedPlatforms', 'nativeAvailable', 'limits'));
    }

    /**
     * Gera URL JWT segura e redireciona (ou retorna JSON) para conectar contas.
     */
    public function connectUrl(Request $request): JsonResponse|RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $url = $this->service->connectUrlFor($cliente);

        if ($request->wantsJson()) {
            return response()->json(['access_url' => $url]);
        }

        return redirect()->away($url);
    }

    /**
     * Callback / Redirecionamento após o cliente conectar as contas no Upload-Post.
     */
    public function connectedCallback(Request $request): RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $this->service->syncConnectionStatus($cliente);

        return redirect()
            ->route('portal.social.index')
            ->with('success', __('Suas redes sociais foram sincronizadas e validadas com sucesso!'));
    }

    /**
     * Força a sincronização server-to-server do status das plataformas.
     */
    public function sync(Request $request): JsonResponse|RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $profile = $this->service->syncConnectionStatus($cliente);

        if ($request->wantsJson()) {
            return response()->json($profile);
        }

        return redirect()
            ->route('portal.social.index')
            ->with('success', __('Status das conexões atualizado em tempo real.'));
    }

    /**
     * Dispara a publicação de um vídeo pronto nas redes sociais selecionadas.
     */
    public function distribute(PublishVideoRequest $request, Video $video): JsonResponse|RedirectResponse
    {
        $cliente = auth('cliente')->user();

        // Verificação de isolamento: vídeo deve pertencer à produção deste cliente
        abort_unless($video->production?->cliente_id === $cliente->id, 403, 'Acesso não autorizado a este vídeo.');

        $scheduledAt = $request->validated('scheduled_at') ? new \DateTimeImmutable($request->validated('scheduled_at')) : null;

        // Arquivo próprio da NUME só sai (nativo ou por URL assinada) com o saldo quitado.
        // A URL digitada pelo cliente só vale para vídeos que são apenas um link.
        abort_if($video->hasSourceFile() && $video->isAssetLocked(), 403, __('Envio bloqueado: o saldo do pacote ainda não foi quitado.'));

        $result = $this->publications->distribute(
            cliente: $cliente,
            video: $video,
            title: $request->validated('titulo'),
            platforms: $request->validated('plataformas'),
            user: null,
            scheduledAt: $scheduledAt,
            linkUrl: $video->hasSourceFile() ? null : $request->validated('video_url'),
        );

        // TikTok direto exige a tela própria (escolhas do cliente a cada post).
        $composer = collect($result['composer'])->mapWithKeys(fn (string $platform) => [$platform => route('portal.videos.'.$platform, $video)]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $scheduledAt ? __('Publicação agendada com sucesso.') : __('Publicação enviada com sucesso.'),
                'publication' => $result['publications'][0] ?? null,
                'publications' => $result['publications'],
                'composer' => $composer,
            ], 202);
        }

        if ($result['publications'] === [] && $composer->isNotEmpty()) {
            return redirect()->to($composer->first());
        }

        $message = $scheduledAt
            ? __('Seu vídeo foi agendado com sucesso e será publicado automaticamente na data programada!')
            : __('Seu vídeo foi enviado para publicação nas redes sociais selecionadas! Acompanhe o processamento no painel.');

        if ($composer->has('tiktok')) {
            $message .= ' '.__('Para o TikTok, use o botão "Publicar no TikTok" e confirme as opções do post.');
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Consulta o status atual de uma publicação assíncrona.
     */
    public function status(VideoPublication $videoPublication): JsonResponse
    {
        $cliente = auth('cliente')->user();

        abort_unless($videoPublication->cliente_id === $cliente->id, 403);

        $refreshed = $this->service->refreshStatus($videoPublication);

        return response()->json($refreshed);
    }
}
