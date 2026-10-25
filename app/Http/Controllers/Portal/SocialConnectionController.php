<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SocialConnection;
use App\Services\SocialPublishing\Connectors\ConnectorRegistry;
use App\Services\SocialPublishing\Connectors\SocialConnector;
use App\Services\SocialPublishing\OAuth\OAuthException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Conexão de contas pelo app próprio da NUME (OAuth oficial de cada rede).
 * A rede vem da rota e o conector de config('social.connectors').
 */
class SocialConnectionController extends Controller
{
    public function __construct(private readonly ConnectorRegistry $connectors) {}

    public function redirect(Request $request, string $platform): RedirectResponse
    {
        $connector = $this->connector($platform);

        if (! $connector->isConfigured()) {
            return redirect()->route('portal.social.index')
                ->with('error', __('A conexão direta com esta rede ainda não está disponível. Fale com a sua produtora.'));
        }

        return redirect()->away($connector->start(auth('cliente')->user(), $request->session()));
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $cliente = auth('cliente')->user();
        $connector = $this->connector($platform);

        try {
            $result = $connector->finish(
                $cliente,
                $request->session(),
                $request->query('state'),
                $request->query('code'),
                $request->query('error'),
            );
        } catch (OAuthException $e) {
            Log::warning('social.connect_failed', [
                'tenant_id' => $cliente->tenant_id,
                'cliente_id' => $cliente->id,
                'connector' => $platform,
                'reason' => $e->reason,
            ]);

            return redirect()->route('portal.social.index')
                ->with('error', $connector->failureMessage($e->reason) ?? $this->genericFailure($e->reason));
        }

        if ($result->needsSelection()) {
            return redirect()->route($result->selectionRoute);
        }

        return redirect()->route('portal.social.index')->with('success', $connector->successMessage($result->connections));
    }

    public function destroy(SocialConnection $socialConnection): RedirectResponse
    {
        $cliente = auth('cliente')->user();

        abort_unless(
            $socialConnection->cliente_id === $cliente->id && $socialConnection->tenant_id === $cliente->tenant_id,
            403
        );

        $connector = $this->connectors->forProvider($socialConnection->provider);

        if ($connector) {
            $connector->disconnect($socialConnection);
        } else {
            $socialConnection->cancelPendingPublications();
            $socialConnection->delete();
        }

        return redirect()->route('portal.social.index')
            ->with('success', __('Conta desconectada. A NUME não tem mais acesso a ela.'));
    }

    private function connector(string $platform): SocialConnector
    {
        $connector = $this->connectors->for($platform);
        abort_if($connector === null, 404);

        return $connector;
    }

    private function genericFailure(string $reason): string
    {
        return match ($reason) {
            'invalid_state' => __('A conexão expirou ou não foi iniciada por aqui. Tente conectar novamente.'),
            'access_denied' => __('Você cancelou a autorização. Nada foi conectado.'),
            default => __('Não foi possível conectar a rede agora. Tente novamente em alguns minutos.'),
        };
    }
}
