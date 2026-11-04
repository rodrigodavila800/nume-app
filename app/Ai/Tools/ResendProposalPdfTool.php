<?php

namespace App\Ai\Tools;

use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Quote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reenvia o PDF da proposta em aberto DESTA conversa (por e-mail e por este WhatsApp) quando o cliente
 * pede ou diz que não recebeu. Só vale proposta criada pela Sofi para este mesmo telefone
 * (request_details.phone), no tenant do construtor: buscar pela conta do cliente (cliente_id) mandou,
 * em produção, a proposta de outra empresa para quem pediu. No máximo um reenvio a cada 10 minutos.
 */
class ResendProposalPdfTool implements Tool
{
    public const COOLDOWN_MINUTES = 10;

    public function __construct(
        protected int $tenantId,
        protected ?string $phone = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Reenvia o PDF da proposta em aberto deste cliente, por e-mail e por este WhatsApp. Use quando ele pedir o PDF ou a proposta de novo, ou disser que não recebeu.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (blank($this->phone)) {
            return 'Não encontrei proposta para este contato. Se ele quiser contratar, siga o fluxo da proposta (CreateQuoteFromPackTool).';
        }

        $quote = Quote::forTenant($this->tenantId)
            ->where('status', 'sent')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->where('request_details->source', 'whatsapp')
            ->where('request_details->phone', $this->phone)
            ->latest('id')
            ->first();

        if (! $quote) {
            return 'Não há proposta em aberto criada nesta conversa. Não envie proposta de outra conversa ou cadastro; se ele quiser contratar, siga o fluxo da proposta (CreateQuoteFromPackTool).';
        }

        if (! Cache::add('whatsapp:proposal-pdf:'.$quote->id, true, now()->addMinutes(self::COOLDOWN_MINUTES))) {
            return "O PDF da proposta {$quote->number} acabou de ser enviado. Peça ao cliente para conferir o WhatsApp e a caixa de entrada (e o spam) de {$quote->client_email}.";
        }

        SendQuoteProposalDocuments::dispatch($quote->id, $this->tenantId, $this->phone);

        return "PDF da proposta {$quote->number} reenviado: chega em seguida por este WhatsApp e no e-mail {$quote->client_email}. Não chame a equipe para isso.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
