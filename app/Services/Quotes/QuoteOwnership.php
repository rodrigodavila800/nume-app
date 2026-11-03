<?php

namespace App\Services\Quotes;

use App\Models\Cliente;
use App\Models\Quote;

/**
 * Liga propostas feitas antes da conta (ex.: pela Sofi no WhatsApp) à conta do cliente, pelo e-mail
 * e sempre dentro da mesma produtora. O portal lista propostas só por cliente_id: sem isso, quem
 * recebe a proposta e se cadastra depois não a encontraria em Orçamentos.
 */
class QuoteOwnership
{
    /** Só propostas ainda em aberto mudam de dono; aceitas e recusadas ficam como estão. */
    public const OPEN_STATUSES = [Quote::STATUS_REQUESTED, 'draft', 'sent'];

    public static function findClienteByEmail(int $tenantId, string $email): ?Cliente
    {
        $email = mb_strtolower(trim($email));

        return $email === ''
            ? null
            : Cliente::forTenant($tenantId)->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    /**
     * Devolve quantas propostas passaram para o cliente. Atualização em massa: não dispara os
     * eventos do Quote (o status não muda, então o CRM não tem nada a mover).
     */
    public function claimOpenQuotes(Cliente $cliente): int
    {
        $email = mb_strtolower(trim((string) $cliente->email));

        if ($email === '' || ! $cliente->tenant_id) {
            return 0;
        }

        return Quote::forTenant((int) $cliente->tenant_id)
            ->whereNull('cliente_id')
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereRaw('LOWER(client_email) = ?', [$email])
            ->update(['cliente_id' => $cliente->id]);
    }
}
