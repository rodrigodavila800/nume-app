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
 * Reenvia o PDF da proposta em aberto deste contato (por e-mail e por este WhatsApp) quando o cliente
 * pede ou diz que não recebeu. A proposta vem do telefone da conversa ou do cliente identificado,
 * nunca de um número dito pelo modelo, e sempre do tenant do construtor. No máximo um reenvio a cada
 * 10 minutos por proposta, para o cliente não receber o mesmo PDF repetidas vezes.
 */
class ResendProposalPdfTool implements Tool
{
    public const COOLDOWN_MINUTES = 10;

    public function __construct(
        protected int $tenantId,
        protected ?string $phone = null,
        protected ?int $clienteId = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Reenvia o PDF da proposta em aberto deste cliente, por e-mail e por este WhatsApp. Use quando ele pedir o PDF ou a proposta de novo, ou disser que não recebeu.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (blank($this->phone) && ! $this->clienteId) {
            return 'Não encontrei proposta para este contato. Se ele quiser contratar, siga o fluxo da proposta (CreateQuoteFromPackTool).';
        }

        $quote = Quote::forTenant($this->tenantId)
            ->where('status', 'sent')
            ->whereDate('valid_until', '>=', now()->toDateString())
            ->where(function ($query) {
                $query->when(filled($this->phone), fn ($q) => $q->orWhere('request_details->phone', $this->phone))
                    ->when($this->clienteId, fn ($q) => $q->orWhere('cliente_id', $this->clienteId));
            })
            ->latest('id')
            ->first();

        if (! $quote) {
            return 'Não há proposta em aberto para este contato. Se ele quiser contratar, siga o fluxo da proposta (CreateQuoteFromPackTool).';
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
