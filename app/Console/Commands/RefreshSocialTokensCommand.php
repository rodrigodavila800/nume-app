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
        $refreshed = 0;
        $needsReconnect = 0;
        $retryLater = 0;

        // Sem usuário autenticado o scope de tenant não filtra: cada conta é tratada com o próprio tenant_id.
        SocialConnection::withoutGlobalScopes()
            ->where('provider', 'google')
            ->where('status', SocialConnection::STATUS_ACTIVE)
            ->where(fn ($query) => $query->whereNull('last_refreshed_at')->orWhere('last_refreshed_at', '<=', $threshold))
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
