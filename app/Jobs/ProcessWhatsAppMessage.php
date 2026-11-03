<?php

namespace App\Jobs;

use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mensagem do WhatsApp processada fora do webhook: a Meta recebe o 200 na hora e a Sofi tem tempo
 * para as chamadas de IA e ferramentas (até 4 passos de sofi.whatsapp_timeout segundos).
 *
 * O payload traz telefone e texto do cliente, por isso vai criptografado para a tabela jobs. O tenant
 * sai do phone_number_id do próprio payload (MetaWhatsAppService::resolveTenant), nunca de usuário
 * logado. Sem retentativa: a trava por wamid já evita resposta dupla, e um job morto por timeout não
 * deve responder de novo depois. O prazo cabe no retry_after da conexão (config/queue.php).
 */
class ProcessWhatsAppMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    /**
     * @param  array<string, mixed>  $payload  Corpo do webhook já com a assinatura validada
     */
    public function __construct(public readonly array $payload) {}

    public function handle(MetaWhatsAppService $whatsApp): void
    {
        $whatsApp->processWebhookPayload($this->payload);
    }

    public function failed(?Throwable $exception): void
    {
        // Só a classe: a mensagem pode trazer URL da chamada ou dados do cliente.
        Log::warning('[MetaWhatsApp] Processamento da mensagem falhou na fila: '.($exception ? get_class($exception) : 'desconhecido'));
    }
}
