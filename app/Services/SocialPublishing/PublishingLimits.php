<?php

namespace App\Services\SocialPublishing;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\SocialConnection;
use App\Models\VideoPublication;
use Illuminate\Support\Collection;

/**
 * Limites de publicação por cliente, liberados pelo admin da NUME:
 * - redes: quantas contas de rede o cliente pode ter conectadas (ele escolhe quais);
 * - envios por mês: várias redes no mesmo envio contam 1 (mesma regra da cota de vídeos).
 *
 * Ordem: ajuste manual na ficha do cliente → maior valor entre as compras ativas (do orçamento ou
 * do plano/pacote). Null = sem limite. Tenant sempre explícito (roda também em fila e API).
 */
class PublishingLimits
{
    public function networksLimit(Cliente $cliente): ?int
    {
        return $cliente->social_networks_limit_override ?? $this->fromPurchases($cliente, 'social_networks_limit');
    }

    public function monthlyLimit(Cliente $cliente): ?int
    {
        return $cliente->monthly_post_limit_override ?? $this->fromPurchases($cliente, 'monthly_post_limit');
    }

    /**
     * Redes com conta ativa pelo app da NUME.
     *
     * @return list<string>
     */
    public function connectedNetworks(Cliente $cliente): array
    {
        return SocialConnection::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->active()
            ->pluck('platform')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pode conectar estas redes? Reconectar uma rede já conectada sempre pode.
     *
     * @param  list<string>  $platforms
     */
    public function canConnect(Cliente $cliente, array $platforms): bool
    {
        $limit = $this->networksLimit($cliente);
        if ($limit === null) {
            return true;
        }

        $connected = $this->connectedNetworks($cliente);
        $new = array_diff(array_unique($platforms), $connected);

        return count($connected) + count($new) <= $limit;
    }

    /**
     * Envios do mês corrente (várias redes do mesmo vídeo em até 60 s = 1 envio). Falhas não contam.
     */
    public function sendsThisMonth(Cliente $cliente): int
    {
        $rows = VideoPublication::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('status', '!=', 'falhou')
            ->orderBy('created_at')
            ->get(['id', 'video_id', 'created_at']);

        $count = 0;
        $batchStart = [];
        foreach ($rows as $row) {
            $key = $row->video_id ?? 'sem-video-'.$row->id;
            $start = $batchStart[$key] ?? null;

            if ($start === null || $start->diffInSeconds($row->created_at) > 60) {
                $batchStart[$key] = $row->created_at;
                $count++;
            }
        }

        return $count;
    }

    /**
     * Confere os dois limites antes de criar as publicações de um envio.
     *
     * @param  list<string>  $platforms
     *
     * @throws PublishingLimitException
     */
    public function assertCanSend(Cliente $cliente, array $platforms): void
    {
        $networks = $this->networksLimit($cliente);
        if ($networks !== null && count(array_unique($platforms)) > $networks) {
            throw new PublishingLimitException('networks_limit', trans_choice(
                'Seu plano libera publicar em :count rede por envio.|Seu plano libera publicar em até :count redes por envio.',
                $networks,
                ['count' => $networks]
            ));
        }

        $monthly = $this->monthlyLimit($cliente);
        if ($monthly !== null && $this->sendsThisMonth($cliente) >= $monthly) {
            throw new PublishingLimitException('monthly_limit', __('Você já usou os :count envios do mês do seu plano. Fale com a NUME para ampliar.', ['count' => $monthly]));
        }
    }

    /**
     * Resumo para telas e API.
     *
     * @return array{networks_limit: ?int, networks_connected: int, monthly_limit: ?int, sends_this_month: int}
     */
    public function summary(Cliente $cliente): array
    {
        return [
            'networks_limit' => $this->networksLimit($cliente),
            'networks_connected' => count($this->connectedNetworks($cliente)),
            'monthly_limit' => $this->monthlyLimit($cliente),
            'sends_this_month' => $this->sendsThisMonth($cliente),
        ];
    }

    private function fromPurchases(Cliente $cliente, string $column): ?int
    {
        /** @var Collection<int, PackPurchase> $purchases */
        $purchases = PackPurchase::withoutGlobalScope('tenant')
            ->where('tenant_id', $cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->whereIn('status', ['paid', 'active'])
            ->with(['pack' => fn ($q) => $q->withoutGlobalScope('tenant')])
            ->get();

        $values = $purchases
            ->map(fn (PackPurchase $p) => $p->{$column} ?? $p->pack?->{$column})
            ->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : (int) $values->max();
    }
}
