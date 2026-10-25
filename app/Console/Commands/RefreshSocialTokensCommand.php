<?php

namespace App\Console\Commands;

use App\Models\SocialConnection;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use Illuminate\Console\Command;

/**
 * Checagem diária das contas conectadas pelo app próprio: renova o token de quem não foi usado
 * nas últimas horas e marca para reconexão as contas que o Google revogou, antes que um envio
 * agendado descubra isso na hora de publicar.
 */
class RefreshSocialTokensCommand extends Command
{
    protected $signature = 'social:refresh-tokens {--hours=20 : Renova contas sem renovação há pelo menos este número de horas}';

    protected $description = 'Renova os tokens das contas sociais conectadas e marca as revogadas para reconexão.';

    public function handle(SocialTokenManager $tokens): int
    {
        $threshold = now()->subHours(max(1, (int) $this->option('hours')));
        // Threads: token de 60 dias que só renova depois de 24 h; basta renovar a cada poucos dias.
        $threadsThreshold = now()->subDays(max(1, (int) config('social.threads.refresh_after_days', 7)));
        $refreshed = 0;
        $needsReconnect = 0;
        $retryLater = 0;

        // Sem usuário autenticado o scope de tenant não filtra: cada conta é tratada com o próprio tenant_id.
        // Contas da Meta (Página/Instagram) usam token sem validade fixa e não entram aqui.
        SocialConnection::withoutGlobalScopes()
            ->where('status', SocialConnection::STATUS_ACTIVE)
            ->where(fn ($query) => $query
                ->where(fn ($google) => $google->whereIn('provider', ['google', 'tiktok'])
                    ->where(fn ($q) => $q->whereNull('last_refreshed_at')->orWhere('last_refreshed_at', '<=', $threshold)))
                ->orWhere(fn ($threads) => $threads->where('provider', 'threads')
                    ->where(fn ($q) => $q->whereNull('last_refreshed_at')->orWhere('last_refreshed_at', '<=', $threadsThreshold))))
            ->chunkById(50, function ($connections) use ($tokens, &$refreshed, &$needsReconnect, &$retryLater) {
                foreach ($connections as $connection) {
                    try {
                        $tokens->accessToken($connection, forceRefresh: true);
                        $refreshed++;
                    } catch (PublishException $e) {
                        $e->retryable ? $retryLater++ : $needsReconnect++;
                    }
                }
            });

        $this->info("Renovadas: {$refreshed}. Precisam reconectar: {$needsReconnect}. Tentar de novo depois: {$retryLater}.");

        return self::SUCCESS;
    }
}
