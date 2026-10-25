<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\SocialPublishing\OAuth\OAuthException;
use App\Services\SocialPublishing\YouTubeConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Conexão de contas pelo app próprio da NUME (OAuth oficial). Por enquanto só YouTube.
 */
class SocialConnectionController extends Controller
{
    public function __construct(private readonly YouTubeConnector $youtube) {}

    public function redirect(Request $request, string $platform): RedirectResponse
    {
        if (! $this->youtube->isConfigured()) {
            return redirect()->route('portal.social.index')
                ->with('error', __('A conexão direta com o YouTube ainda não está disponível. Fale com a sua produtora.'));
        }

        return redirect()->away($this->youtube->start(auth('cliente')->user(), $request->session()));
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $cliente = auth('cliente')->user();

        try {
            $connection = $this->youtube->finish(
                $cliente,
                $request->session(),
                $request->query('state'),
                $request->query('code'),
                $request->query('error'),
            );
        } catch (OAuthException $e) {
            Log::warning('social.youtube.connect_failed', [
                'tenant_id' => $cliente->tenant_id,
                'cliente_id' => $cliente->id,
                'reason' => $e->reason,
            ]);

            return redirect()->route('portal.social.index')->with('error', $this->failureMessage($e->reason));
        }

        return redirect()->route('portal.social.index')
            ->with('success', __('Canal do YouTube :canal conectado com sucesso!', ['canal' => $connection->handle ?? $connection->display_name ?? '']));
    }

    private function failureMessage(string $reason): string
    {
        return match ($reason) {
            'access_denied' => __('Você cancelou a autorização no Google. Nada foi conectado.'),
            'scope_not_granted' => __('Para publicar, autorize o envio de vídeos ao seu canal na tela do Google.'),
            'no_channel' => __('Esta conta Google não tem um canal do YouTube. Crie o canal e tente de novo.'),
            'invalid_state' => __('A conexão expirou ou não foi iniciada por aqui. Tente conectar novamente.'),
            default => __('Não foi possível conectar o YouTube agora. Tente novamente em alguns minutos.'),
        };
    }
}
