<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PlataformaAlvo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PublishVideoRequest;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\UploadPost\SocialDistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialDistributionController extends Controller
{
    public function __construct(private readonly SocialDistributionService $service)
    {
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

        return view('portal.social.index', compact('cliente', 'profile', 'publications', 'platforms'));
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

        $publication = $this->service->publish(
            cliente: $cliente,
            videoUrl: $request->validated('video_url'),
            title: $request->validated('titulo'),
            platforms: $request->validated('plataformas'),
            video: $video,
            user: null,
            scheduledAt: $scheduledAt,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $scheduledAt ? __('Publicação agendada com sucesso.') : __('Publicação enviada com sucesso.'),
                'publication' => $publication,
            ], 202);
        }

        $message = $scheduledAt
            ? __('Seu vídeo foi agendado com sucesso e será publicado automaticamente na data programada!')
            : __('Seu vídeo foi enviado para publicação nas redes sociais selecionadas! Acompanhe o processamento no painel.');

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
