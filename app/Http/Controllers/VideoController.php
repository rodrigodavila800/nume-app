<?php

namespace App\Http\Controllers;

use App\Enums\PlataformaAlvo;
use App\Models\Production;
use App\Models\Video;
use App\Services\Notification\SofiVideoNotificationService;
use App\Services\SocialPublishing\SocialPublicationService;
use App\Services\SofiCopywriterService;
use App\Services\UploadPost\SocialDistributionService;
use App\Services\Video\VideoUploadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    public function __construct(
        protected SofiCopywriterService $sofiService,
        protected SocialDistributionService $socialService,
        protected SofiVideoNotificationService $notificationService,
        protected VideoUploadService $uploads,
        protected SocialPublicationService $publications
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
    public function create(Production $production): View|RedirectResponse
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
     * Regras de criação/edição. O vídeo é um arquivo enviado OU um link externo.
     *
     * @return array<string, mixed>
     */
    private function videoRules(Request $request, ?Video $video = null): array
    {
        $needsUrl = fn () => ! $request->hasFile('video_file')
            && ! ($video?->hasSourceFile() && ! $request->boolean('remove_video_file'));

        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_url' => ['nullable', 'url', 'max:2048', Rule::requiredIf($needsUrl)],
            'video_file' => [
                'nullable', 'file',
                'mimetypes:'.implode(',', config('social.video.allowed_mimes')),
                'extensions:'.implode(',', config('social.video.allowed_extensions')),
                'max:'.((int) config('social.video.max_upload_mb') * 1024),
            ],
            'remove_video_file' => 'nullable|boolean',
            'thumbnail_url' => 'nullable|url',
            'duration_seconds' => 'nullable|integer|min:1',
        ];
    }

    /**
     * Mensagens do upload: o PHP só informa "falha no envio" quando o arquivo passa do limite do servidor.
     *
     * @return array<string, string>
     */
    private function videoMessages(): array
    {
        $max = (int) config('social.video.max_upload_mb');

        return [
            'video_file.uploaded' => __('O arquivo não foi enviado. Ele pode passar do limite de upload do servidor (máx. :max MB); fale com o suporte se o problema continuar.', ['max' => $max]),
            'video_file.max' => __('O vídeo excede o tamanho máximo de :max MB.', ['max' => $max]),
            'video_file.mimetypes' => __('Envie um vídeo MP4 ou MOV.'),
            'video_file.extensions' => __('Envie um vídeo MP4 ou MOV.'),
            'video_url.required' => __('Informe o link do vídeo ou envie o arquivo.'),
        ];
    }

    /**
     * Armazenar novo vídeo
     */
    public function store(Request $request, Production $production): RedirectResponse
    {
        $this->authorize('create', Video::class);
        $this->authorize('view', $production);

        $validated = $request->validate($this->videoRules($request), $this->videoMessages());
        unset($validated['video_file'], $validated['remove_video_file']);

        if ($request->hasFile('video_file')) {
            $validated = [...$validated, ...$this->uploads->store($request->file('video_file'), (int) $production->tenant_id)];
        }

        try {
            $video = $production->videos()->create([
                ...$validated,
                'status' => 'processing',
            ]);
        } catch (\Throwable $e) {
            // Não deixa o arquivo órfão no disco se o registro não for criado.
            if (! empty($validated['source_path'])) {
                Storage::disk($validated['source_disk'])->delete($validated['source_path']);
            }

            throw $e;
        }

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

        $validated = $request->validate($this->videoRules($request, $video), $this->videoMessages());
        unset($validated['video_file'], $validated['remove_video_file']);

        $previous = $video->hasSourceFile() ? [$video->source_disk, $video->source_path] : null;

        if ($request->hasFile('video_file')) {
            $validated = [...$validated, ...$this->uploads->store($request->file('video_file'), (int) $video->tenant_id)];
        } elseif ($request->boolean('remove_video_file')) {
            $validated = [...$validated, ...VideoUploadService::clearedAttributes()];
        }

        $video->update($validated);

        // O arquivo anterior só é apagado depois de o registro apontar para o novo (ou para nenhum).
        if ($previous && $video->source_path !== $previous[1]) {
            Storage::disk($previous[0])->delete($previous[1]);
        }

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

        $title = $validated['titulo'] ?? ($video->sofi_seo_title ?: $video->title);
        $scheduledAt = !empty($validated['scheduled_at']) ? new \DateTimeImmutable($validated['scheduled_at']) : null;

        try {
            // Com arquivo próprio da NUME, a URL de busca é gerada no serviço (assinada, temporária);
            // a URL digitada no formulário só vale para vídeos que são apenas um link.
            $result = $this->publications->distribute(
                cliente: $cliente,
                video: $video,
                title: $title,
                platforms: $validated['plataformas'],
                user: auth()->user(),
                scheduledAt: $scheduledAt,
                linkUrl: $video->hasSourceFile() ? null : ($validated['video_url'] ?? null),
            );

            // TikTok direto exige que o dono da conta confirme as opções do post no portal.
            $sent = array_values(array_diff($validated['plataformas'], $result['composer']));
            $tiktokNote = in_array('tiktok', $result['composer'], true)
                ? ' '.__('O TikTok desta conta é conectado direto: o cliente precisa publicar pelo portal, em "Publicar no TikTok".')
                : '';

            if ($sent === []) {
                return redirect()->back()->with('warning', trim($tiktokNote));
            }

            $msg = $scheduledAt
                ? __('Postagem agendada com sucesso para :date nas plataformas: :platforms!', [
                    'date' => $scheduledAt->format('d/m/Y H:i'),
                    'platforms' => implode(', ', $sent)
                ])
                : __('Vídeo enviado com sucesso para a esteira de postagem nas redes sociais (:platforms)!', [
                    'platforms' => implode(', ', $sent)
                ]);

            return redirect()->back()->with('success', $msg.$tiktokNote);
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', __('O vídeo não possui uma URL válida para envio às redes sociais.'));
        } catch (\Throwable $e) {
            // A mensagem de exceções HTTP traz a URL chamada (com URL assinada): não vai para log nem para a tela.
            Log::error('Erro ao disparar distribuição social do vídeo.', ['video_id' => $video->id, 'exception' => $e::class]);

            return redirect()->back()->with('error', __('Falha ao conectar com o serviço de distribuição. Tente novamente em alguns minutos.'));
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
