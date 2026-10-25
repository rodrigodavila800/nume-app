<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\GuardsComposerPublishing;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\Pinterest\PinterestApi;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialPublicationService;
use App\Services\SocialPublishing\SocialPublisherRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tela "Publicar no Pinterest": o pin precisa de uma pasta, escolhida pelo cliente entre as
 * pastas da conta (buscadas na hora e conferidas de novo no envio).
 */
class PinterestComposerController extends Controller
{
    use GuardsComposerPublishing;

    public function __construct(
        private readonly PinterestApi $api,
        private readonly SocialPublisherRouter $router,
        private readonly SocialPublicationService $publications,
        private readonly CaptionBuilder $captions,
    ) {}

    public function show(Request $request, Video $video): View|RedirectResponse
    {
        $connection = $this->guardComposer(auth('cliente')->user(), $video, 'pinterest', $this->router);

        try {
            $boards = $this->api->boards($connection);
        } catch (PublishException $e) {
            return $this->unavailable($video, $e->reason);
        }

        if ($boards === []) {
            return redirect()->route('portal.productions.show', $video->production->uuid)
                ->with('error', __('Sua conta do Pinterest não tem nenhuma pasta. Crie uma pasta no Pinterest e tente de novo.'));
        }

        $draft = new VideoPublication(['titulo' => '']);

        return view('portal.social.pinterest-composer', [
            'video' => $video,
            'connection' => $connection,
            'boards' => $boards,
            'title' => mb_substr((string) ($video->sofi_seo_title ?: $video->title), 0, 100),
            'description' => $this->captions->build($draft, $video, 800, 20),
            'link' => is_string(auth('cliente')->user()->website) && str_starts_with(auth('cliente')->user()->website, 'https://') ? auth('cliente')->user()->website : '',
        ]);
    }

    public function store(Request $request, Video $video): RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $connection = $this->guardComposer($cliente, $video, 'pinterest', $this->router);

        try {
            $boardIds = array_column($this->api->boards($connection), 'id');
        } catch (PublishException $e) {
            return $this->unavailable($video, $e->reason);
        }

        $validated = $request->validate([
            'board_id' => ['required', 'string', Rule::in($boardIds)],
            'title' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:800'],
            'link' => ['nullable', 'url:https', 'max:2048'],
        ], [
            'board_id.required' => __('Escolha a pasta do pin.'),
            'board_id.in' => __('Esta pasta não está mais disponível na sua conta. Escolha outra.'),
        ]);

        $this->publications->publishComposed($cliente, $video, $connection, (string) ($validated['title'] ?? '') ?: ($video->sofi_seo_title ?: $video->title), [
            'board_id' => $validated['board_id'],
            'title' => (string) ($validated['title'] ?? ''),
            'description' => (string) ($validated['description'] ?? ''),
            'link' => $validated['link'] ?? null,
        ]);

        return redirect()->route('portal.productions.show', $video->production->uuid)
            ->with('success', __('Vídeo enviado ao Pinterest. O pin aparece na pasta escolhida assim que o processamento terminar.'));
    }

    private function unavailable(Video $video, string $reason): RedirectResponse
    {
        return redirect()->route('portal.productions.show', $video->production->uuid)->with('error', $reason === 'reconnect_required'
            ? __('Reconecte o Pinterest em Redes Sociais para publicar.')
            : __('Não foi possível falar com o Pinterest agora. Tente novamente em alguns minutos.'));
    }
}
