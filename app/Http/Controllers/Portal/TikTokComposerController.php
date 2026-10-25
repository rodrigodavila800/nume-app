<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\CaptionBuilder;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialPublicationService;
use App\Services\SocialPublishing\SocialPublisherRouter;
use App\Services\SocialPublishing\SocialTokenManager;
use App\Services\SocialPublishing\TikTok\TikTokContentApi;
use App\Services\SocialPublishing\TikTok\TikTokPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tela "Publicar no TikTok", nas regras de UX obrigatórias do Direct Post: creator info buscado a
 * cada abertura, privacidade sem padrão (só as opções devolvidas), interações desmarcadas e travadas
 * quando o criador as desativou, divulgação de conteúdo comercial e declaração de consentimento.
 * Tudo é conferido de novo no servidor antes de criar a publicação.
 */
class TikTokComposerController extends Controller
{
    public function __construct(
        private readonly TikTokContentApi $content,
        private readonly SocialTokenManager $tokens,
        private readonly SocialPublisherRouter $router,
        private readonly SocialPublicationService $publications,
        private readonly CaptionBuilder $captions,
    ) {}

    public function show(Request $request, Video $video): View|RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $connection = $this->guard($cliente, $video);

        try {
            $creator = $this->content->creatorInfo($this->tokens->accessToken($connection));
        } catch (PublishException $e) {
            return $this->unavailable($video, $e->reason);
        }

        $draft = new VideoPublication(['titulo' => $video->sofi_seo_title ?: $video->title]);

        return view('portal.social.tiktok-composer', [
            'video' => $video,
            'creator' => $creator,
            'title' => $this->captions->build($draft, $video, 2200, 30),
            'tooLong' => $creator['max_video_post_duration_sec'] !== null
                && (int) $video->duration_seconds > $creator['max_video_post_duration_sec'],
        ]);
    }

    public function store(Request $request, Video $video): RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $connection = $this->guard($cliente, $video);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:2200'],
            'privacy_level' => ['required', Rule::in(TikTokPublisher::PRIVACY_LEVELS)],
            'allow_comment' => ['boolean'],
            'allow_duet' => ['boolean'],
            'allow_stitch' => ['boolean'],
            'commercial' => ['boolean'],
            'brand_organic' => ['boolean'],
            'brand_content' => ['boolean'],
        ], [
            'privacy_level.required' => __('Escolha quem pode ver o vídeo.'),
        ]);

        $commercial = (bool) ($validated['commercial'] ?? false);
        $options = [
            'title' => (string) ($validated['title'] ?? ''),
            'privacy_level' => $validated['privacy_level'],
            'allow_comment' => (bool) ($validated['allow_comment'] ?? false),
            'allow_duet' => (bool) ($validated['allow_duet'] ?? false),
            'allow_stitch' => (bool) ($validated['allow_stitch'] ?? false),
            'brand_organic' => $commercial && (bool) ($validated['brand_organic'] ?? false),
            'brand_content' => $commercial && (bool) ($validated['brand_content'] ?? false),
        ];

        if ($commercial && ! $options['brand_organic'] && ! $options['brand_content']) {
            throw ValidationException::withMessages(['commercial' => __('Você precisa indicar se o conteúdo promove você, um terceiro ou ambos.')]);
        }

        try {
            $creator = $this->content->creatorInfo($this->tokens->accessToken($connection));
            app(TikTokPublisher::class)->assertAllowed($options, $creator, (int) $video->duration_seconds);
        } catch (PublishException $e) {
            if (in_array($e->reason, ['privacy_level_option_mismatch', 'branded_content_cannot_be_private', 'tiktok_duration_invalid'], true)) {
                throw ValidationException::withMessages(['privacy_level' => $this->reasonMessage($e->reason)]);
            }

            return $this->unavailable($video, $e->reason);
        }

        // O que o criador desativou no app nunca é ligado por aqui.
        $options['allow_comment'] = $options['allow_comment'] && ! $creator['comment_disabled'];
        $options['allow_duet'] = $options['allow_duet'] && ! $creator['duet_disabled'];
        $options['allow_stitch'] = $options['allow_stitch'] && ! $creator['stitch_disabled'];

        $this->publications->publishComposed(
            $cliente,
            $video,
            $connection,
            $options['title'] !== '' ? $options['title'] : ($video->sofi_seo_title ?: $video->title),
            $options,
        );

        return redirect()->route('portal.productions.show', $video->production->uuid)
            ->with('success', __('Vídeo enviado ao TikTok. O processamento pode levar alguns minutos até aparecer no seu perfil.'));
    }

    /**
     * Mesmas regras do envio pelo portal: vídeo do cliente, arquivo hospedado, saldo quitado,
     * cota disponível e conta do TikTok conectada direto.
     */
    private function guard(Cliente $cliente, Video $video): SocialConnection
    {
        abort_unless($video->production?->cliente_id === $cliente->id, 403);
        abort_unless($video->hasSourceFile(), 404);
        abort_if($video->isAssetLocked(), 403, __('Envio bloqueado: o saldo do pacote ainda não foi quitado.'));
        abort_unless($cliente->canDistributeSocial(), 403, __('Você atingiu o limite de publicações do seu plano.'));
        abort_unless($this->router->routeFor('tiktok') === SocialPublisherRouter::NATIVE, 404);

        $connection = $cliente->activeSocialConnection('tiktok');
        abort_if($connection === null, 404);

        return $connection;
    }

    private function unavailable(Video $video, string $reason): RedirectResponse
    {
        return redirect()->route('portal.productions.show', $video->production->uuid)
            ->with('error', $this->reasonMessage($reason));
    }

    private function reasonMessage(string $reason): string
    {
        return match ($reason) {
            'spam_risk_too_many_posts', 'publish_limit_reached' => __('O TikTok não permite mais publicações nesta conta hoje. Tente amanhã.'),
            'spam_risk_user_banned_from_posting' => __('O TikTok bloqueou publicações desta conta. Verifique a conta no aplicativo do TikTok.'),
            'reached_active_user_cap' => __('O TikTok limitou novas contas no app hoje. Tente amanhã.'),
            'reconnect_required' => __('Reconecte o TikTok em Redes Sociais para publicar.'),
            'privacy_level_option_mismatch' => __('Esta opção de privacidade não está disponível para a sua conta. Escolha outra.'),
            'branded_content_cannot_be_private' => __('Conteúdo de marca não pode ser privado. Escolha outra visibilidade.'),
            'tiktok_duration_invalid' => __('O vídeo é mais longo do que o TikTok permite para esta conta.'),
            default => __('Não foi possível falar com o TikTok agora. Tente novamente em alguns minutos.'),
        };
    }
}
