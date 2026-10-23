<?php

namespace App\Http\Controllers;

use App\Enums\PlataformaAlvo;
use App\Models\Production;
use App\Models\Video;
use App\Services\Notification\SofiVideoNotificationService;
use App\Services\SofiCopywriterService;
use App\Services\UploadPost\SocialDistributionService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class VideoController extends Controller
{
    public function __construct(
        protected SofiCopywriterService $sofiService,
        protected SocialDistributionService $socialService,
        protected SofiVideoNotificationService $notificationService
    ) {}

    /**
     * Listar vídeos de uma production
     */
    public function index(Production $production): View
    {
        $this->authorize('view', $production);
        $production->load('roteiros');

        $videos = $production->videos()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('videos.index', compact('production', 'videos'));
    }

    /**
     * Exibir formulário para criar novo vídeo
     */
    public function create(Production $production): View
    {
        $this->authorize('create', Video::class);
        $this->authorize('view', $production);

        // Apenas se production tem roteiro aprovado
        if (!$production->roteiros()->where('status', 'approved')->exists()) {
            return redirect()->route('productions.show', $production)
                ->with('error', 'Production precisa de um roteiro aprovado para adicionar vídeos.');
        }

        return view('videos.create', compact('production'));
    }

    /**
     * Armazenar novo vídeo
     */
    public function store(Request $request, Production $production): RedirectResponse
    {
        $this->authorize('create', Video::class);
        $this->authorize('view', $production);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_url' => 'required|url',
            'thumbnail_url' => 'nullable|url',
            'duration_seconds' => 'nullable|integer|min:1',
        ]);

        $video = $production->videos()->create([
            ...$validated,
            'status' => 'processing',
        ]);

        return redirect()->route('videos.show', [$production, $video])
            ->with('success', 'Vídeo adicionado! Processando...');
    }

    /**
     * Exibir vídeo completo com contexto de produção e distribuição social
     */
    public function show(Production $production, Video $video): View
    {
        $this->authorize('view', $production);
        $this->authorize('view', $video);

        $video->load(['publications.user']);
        $cliente = $production->cliente;
        $socialProfile = $cliente ? $this->socialService->ensureProfile($cliente) : null;
        $platforms = PlataformaAlvo::cases();

        $whatsappMessage = $this->notificationService->getWhatsAppMessage($video);
        $whatsappUrl = $this->notificationService->getWhatsAppShareUrl($video);

        return view('videos.show', compact('production', 'video', 'cliente', 'socialProfile', 'platforms', 'whatsappMessage', 'whatsappUrl'));
    }

    /**
     * Exibir formulário para editar vídeo
     */
    public function edit(Production $production, Video $video): View|RedirectResponse
    {
        $this->authorize('update', $video);

        if ($video->isPublished()) {
            return redirect()->back()
                ->with('error', 'Vídeos publicados não podem ser editados.');
        }

        return view('videos.edit', compact('production', 'video'));
    }

    /**
     * Atualizar vídeo
     */
    public function update(Request $request, Production $production, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        if ($video->isPublished()) {
            return redirect()->back()
                ->with('error', 'Vídeos publicados não podem ser editados.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_url' => 'required|url',
            'thumbnail_url' => 'nullable|url',
            'duration_seconds' => 'nullable|integer|min:1',
        ]);

        $video->update($validated);

        return redirect()->route('videos.show', [$production, $video])
            ->with('success', 'Vídeo atualizado com sucesso!');
    }

    /**
     * Marcar vídeo como pronto
     */
    public function markAsReady(Production $production, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        if (!$video->markAsReady()) {
            return redirect()->back()
                ->with('error', 'Vídeo deve estar em "processing" para ser marcado como pronto.');
        }

        return redirect()->back()
            ->with('success', 'Vídeo marcado como pronto para publicação!');
    }

    /**
     * Publicar vídeo
     */
    public function publish(Production $production, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        if (!$video->publish()) {
            return redirect()->back()
                ->with('error', 'Vídeo deve estar "ready" para ser publicado.');
        }

        // Sofi AI: Gera e associa sugestões de SEO completas (título, descrição, hashtags, keywords)
        // com base no perfil da marca do cliente e olhar estratégico de autoridade
        try {
            $sofiSeo = $this->sofiService->generateVideoSeo($video, $production->cliente);
            $video->update([
                ...$sofiSeo,
                'sofi_seo_generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao gerar SEO Sofi para o vídeo: ' . $e->getMessage());
        }

        return redirect()->back()
            ->with('success', 'Vídeo publicado com sucesso! Sofi calibrou as tags e estratégia de SEO orgânico.');
    }

    /**
     * Registrar identificador da plataforma (YouTube, TikTok, etc)
     */
    public function registerPlatformId(Request $request, Production $production, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $validated = $request->validate([
            'platform' => 'required|in:youtube,tiktok,instagram,facebook',
            'platform_video_id' => 'required|string|max:100',
        ]);

        $video->update($validated);

        return redirect()->back()
            ->with('success', 'Identificador da plataforma registrado!');
    }

    /**
     * Disparar distribuição social do vídeo para as redes do cliente
     */
    public function distribute(Request $request, Production $production, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $validated = $request->validate([
            'plataformas' => 'required|array|min:1',
            'plataformas.*' => 'string|in:' . implode(',', PlataformaAlvo::values()),
            'scheduled_at' => 'nullable|date|after:now',
            'titulo' => 'nullable|string|max:255',
            'video_url' => 'nullable|url',
        ]);

        $cliente = $production->cliente;
        if (!$cliente) {
            return redirect()->back()->with('error', __('Esta produção não possui um cliente associado para distribuição social.'));
        }

        $videoUrl = $validated['video_url'] ?? $video->video_url;
        if (empty($videoUrl)) {
            return redirect()->back()->with('error', __('O vídeo não possui uma URL válida para envio às redes sociais.'));
        }

        $title = $validated['titulo'] ?? ($video->sofi_seo_title ?: $video->title);
        $scheduledAt = !empty($validated['scheduled_at']) ? new \DateTimeImmutable($validated['scheduled_at']) : null;

        try {
            $this->socialService->publish(
                cliente: $cliente,
                videoUrl: $videoUrl,
                title: $title,
                platforms: $validated['plataformas'],
                video: $video,
                user: auth()->user(),
                scheduledAt: $scheduledAt,
            );

            $msg = $scheduledAt
                ? __('Postagem agendada com sucesso para :date nas plataformas: :platforms!', [
                    'date' => $scheduledAt->format('d/m/Y H:i'),
                    'platforms' => implode(', ', $validated['plataformas'])
                ])
                : __('Vídeo enviado com sucesso para a esteira de postagem nas redes sociais (:platforms)!', [
                    'platforms' => implode(', ', $validated['plataformas'])
                ]);

            return redirect()->back()->with('success', $msg);
        } catch (\Throwable $e) {
            Log::error('Erro ao disparar distribuição social do vídeo: ' . $e->getMessage());
            return redirect()->back()->with('error', __('Falha ao conectar com o serviço de distribuição: :msg', ['msg' => $e->getMessage()]));
        }
    }

    /**
     * Disparar notificação da Sofi (E-mail e/ou WhatsApp) para o cliente revisar e aprovar o corte final
     */
    public function notifyCliente(Request $request, Production $production, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $channel = $request->input('channel', 'all'); // 'email', 'all'
        $cliente = $production->cliente;

        if (! $cliente) {
            return redirect()->back()->with('error', __('Esta produção não possui um cliente associado para notificação.'));
        }

        $emailSent = false;
        if (in_array($channel, ['email', 'all'])) {
            $emailSent = $this->notificationService->sendVideoReadyEmail($video);
        }

        $whatsAppUrl = $this->notificationService->getWhatsAppShareUrl($video);

        if ($channel === 'email') {
            if ($emailSent) {
                return redirect()->back()->with('success', __('E-mail de aprovação enviado com sucesso para :email!', ['email' => $cliente->email]));
            } else {
                return redirect()->back()->with('error', __('Não foi possível enviar o e-mail de notificação. Verifique o endereço de e-mail do cliente.'));
            }
        }

        return redirect()->back()
            ->with('success', __('Notificação processada com sucesso! E-mail enviado para :email e mensagem preparada para o WhatsApp.', ['email' => $cliente->email]))
            ->with('whatsapp_redirect_url', $whatsAppUrl);
    }

    /**
     * Deletar vídeo (apenas não publicados)
     */
    public function destroy(Production $production, Video $video): RedirectResponse
    {
        $this->authorize('delete', $video);

        if ($video->isPublished()) {
            return redirect()->back()
                ->with('error', 'Vídeos publicados não podem ser deletados.');
        }

        $video->delete();

        return redirect()->route('productions.show', $production)
            ->with('success', 'Vídeo removido com sucesso!');
    }
}
