<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\ResendProposalPdfTool;
use App\Jobs\SendQuoteProposalDocuments;
use App\Mail\QuoteProposalMail;
use App\Models\Cliente;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Quotes\QuotePdfService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * A Sofi reenvia o PDF da proposta quando o cliente pede, sem chamar a equipe. Dados fictícios.
 */
class WhatsAppResendPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        $this->quote = $this->quoteFor($this->tenant, '34600000001');
    }

    private function quoteFor(Tenant $tenant, string $phone, string $status = 'sent'): Quote
    {
        return Quote::create([
            'tenant_id' => $tenant->id, 'status' => $status, 'client_name' => 'Padaria Ficticia', 'client_email' => 'ana@ficticio.test',
            'project_title' => 'Reels Starter', 'inputs' => ['videos' => 4], 'calculation' => [], 'subtotal' => 500, 'tax_rate' => 21, 'tax_amount' => 105,
            'total_amount' => 605, 'deposit_amount' => 605, 'balance_amount' => 0, 'valid_until' => now()->addDays(10),
            'approval_token' => Str::random(48), 'approval_token_expires_at' => now()->addDays(10),
            'request_details' => ['source' => 'whatsapp', 'phone' => $phone],
        ]);
    }

    private function resend(?string $phone = '34600000001', ?int $tenantId = null): string
    {
        return (string) (new ResendProposalPdfTool($tenantId ?? $this->tenant->id, $phone))->handle(new Request([]));
    }

    public function test_the_open_proposal_of_this_phone_is_resent_and_then_cools_down(): void
    {
        Queue::fake([SendQuoteProposalDocuments::class]);

        $first = $this->resend();
        $second = $this->resend();

        $this->assertStringContainsString('reenviado', $first);
        $this->assertStringContainsString('acabou de ser enviado', $second);
        Queue::assertPushed(SendQuoteProposalDocuments::class, 1);
        Queue::assertPushed(SendQuoteProposalDocuments::class, fn (SendQuoteProposalDocuments $job) => $job->quoteId === $this->quote->id && $job->whatsAppTo === '34600000001');
    }

    public function test_no_open_proposal_or_another_tenant_or_another_phone_resends_nothing(): void
    {
        Queue::fake([SendQuoteProposalDocuments::class]);
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $this->quoteFor($other, '34600000002');
        $this->quoteFor($this->tenant, '34600000003', 'accepted');

        $this->assertStringContainsString('Não há proposta', $this->resend('34600000002'));
        $this->assertStringContainsString('Não há proposta', $this->resend('34600000003'));
        $this->assertStringContainsString('Não encontrei', $this->resend(null));

        Queue::assertNothingPushed();
    }

    public function test_the_job_records_that_the_documents_were_sent(): void
    {
        Mail::fake();
        config(['services.whatsapp.access_token' => null]);

        (new SendQuoteProposalDocuments($this->quote->id, $this->tenant->id, '34600000001'))
            ->handle(app(QuotePdfService::class), app(MetaWhatsAppService::class));

        $this->assertNotEmpty($this->quote->fresh()->request_details['documents_sent_at']);
        $this->assertSame('34600000001', $this->quote->fresh()->request_details['phone'], 'mantém o resto dos detalhes');
    }

    public function test_the_whatsapp_agent_resends_instead_of_calling_the_team(): void
    {
        Queue::fake([SendQuoteProposalDocuments::class]);
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);
        SofiWhatsAppAgent::fake([
            new ToolCall('call_1', 'ResendProposalPdfTool', []),
            'Pronto! O PDF está a caminho.',
        ])->preventStrayPrompts();

        $agent = new SofiWhatsAppAgent($this->tenant->id, null, '', false, [], null, '34600000001', '');

        $this->assertSame('Pronto! O PDF está a caminho.', (string) $agent->prompt('Não recebi o PDF'));
        Queue::assertPushed(SendQuoteProposalDocuments::class, 1);
        $this->assertStringContainsString('nunca chame a equipe para reenviar documento', (string) $agent->instructions());
    }

    public function test_a_proposal_of_the_recognized_account_but_from_another_conversation_is_never_resent(): void
    {
        Queue::fake([SendQuoteProposalDocuments::class]);
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Rk', 'email' => 'rk@ficticio.test', 'password' => 'secret12345', 'phone' => '559100000000']);
        $this->quote->delete();
        $teamQuote = $this->quoteFor($this->tenant, '34699999999');
        $teamQuote->forceFill(['cliente_id' => $cliente->id, 'client_name' => 'Outra Empresa S.L.'])->save();
        $noPhone = $this->quoteFor($this->tenant, '');
        $noPhone->forceFill(['cliente_id' => $cliente->id, 'request_details' => ['source' => 'portal']])->save();

        $result = (string) (new ResendProposalPdfTool($this->tenant->id, '559100000000'))->handle(new Request([]));

        $this->assertStringContainsString('Não há proposta em aberto criada nesta conversa', $result);
        Queue::assertNothingPushed();
    }

    public function test_the_job_never_sends_the_pdf_by_whatsapp_to_a_phone_other_than_the_proposal_one(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.whatsapp.access_token' => 'token-ficticio', 'services.whatsapp.phone_number_id' => '1234567890']);

        (new SendQuoteProposalDocuments($this->quote->id, $this->tenant->id, '34611111111'))
            ->handle(app(QuotePdfService::class), app(MetaWhatsAppService::class));

        Http::assertNothingSent();
        Mail::assertSent(QuoteProposalMail::class, fn ($mail) => $mail->hasTo('ana@ficticio.test'));
    }
}
