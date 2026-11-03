<?php

namespace Tests\Feature;

use App\Ai\Tools\CreateQuoteFromPackTool;
use App\Jobs\SendQuoteProposalDocuments;
use App\Mail\QuoteProposalMail;
use App\Models\Pack;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Quotes\QuotePdfService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * PDF formal da proposta por e-mail e por documento do WhatsApp. Dados fictícios; Graph API e e-mail simulados.
 */
class SendQuoteProposalDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPH = 'https://graph.facebook.com/v21.0/1234567890';

    private Tenant $tenant;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::preventStrayRequests();
        Queue::fake([SendQuoteProposalDocuments::class]);
        config([
            'services.whatsapp.access_token' => 'token-whatsapp-ficticio',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.api_version' => 'v21.0',
        ]);

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        $pack = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels Starter', 'slug' => 'reels-starter', 'price' => 500, 'vat_rate' => 21, 'video_quantity' => 4, 'status' => 'active']);

        (new CreateQuoteFromPackTool($this->tenant->id, null, '34600000001'))->handle(new Request([
            'pack_id' => $pack->id, 'client_email' => 'ana@ficticio.test', 'responsible_name' => 'Ana', 'company_name' => 'Padaria Ficticia',
        ]));
        $this->quote = Quote::forTenant($this->tenant->id)->sole();
    }

    private function runJob(?int $tenantId = null): void
    {
        (new SendQuoteProposalDocuments($this->quote->id, $tenantId ?? $this->tenant->id, '34600000001'))
            ->handle(app(QuotePdfService::class), app(MetaWhatsAppService::class));
    }

    private function fakeGraph(int $uploadStatus = 200): void
    {
        Http::fake([
            self::GRAPH.'/media' => Http::response($uploadStatus === 200 ? ['id' => 'media-ficticia-1'] : ['error' => ['code' => 100]], $uploadStatus),
            self::GRAPH.'/messages' => Http::response(['messages' => [['id' => 'wamid.doc']]]),
        ]);
    }

    public function test_the_pdf_goes_by_email_and_as_a_whatsapp_document(): void
    {
        $this->fakeGraph();

        $this->runJob();

        Mail::assertSent(QuoteProposalMail::class, fn (QuoteProposalMail $mail) => $mail->hasTo('ana@ficticio.test')
            && $mail->quote->is($this->quote)
            && str_starts_with((string) $mail->pdfBinary, '%PDF'));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::GRAPH.'/media'
            && $request->isMultipart()
            && $request->hasHeader('Authorization', 'Bearer token-whatsapp-ficticio'));
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::GRAPH.'/messages'
            && $request['type'] === 'document'
            && $request['to'] === '34600000001'
            && $request['document']['id'] === 'media-ficticia-1'
            && $request['document']['filename'] === 'Proposta_'.str_replace('-', '_', $this->quote->number).'.pdf'
            && str_contains($request['document']['caption'], 'Produtora Alpha'));
    }

    public function test_a_whatsapp_failure_keeps_the_email_and_logs_only_the_meta_code(): void
    {
        $lines = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines) {
            $lines[] = $event->message;
        });
        $this->fakeGraph(uploadStatus: 500);

        $this->runJob();

        Mail::assertSent(QuoteProposalMail::class, 1);
        Http::assertNotSent(fn (HttpRequest $request) => $request->url() === self::GRAPH.'/messages');
        $this->assertContains('[MetaWhatsApp] Falha no upload do documento: HTTP 500 código 100', $lines);
        $this->assertStringNotContainsString('token-whatsapp-ficticio', implode("\n", $lines));
    }

    public function test_a_quote_of_another_tenant_is_never_sent(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);

        $this->runJob($other->id);

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_without_whatsapp_credentials_only_the_email_goes(): void
    {
        config(['services.whatsapp.access_token' => null]);

        $this->runJob();

        Mail::assertSent(QuoteProposalMail::class, 1);
        Http::assertNothingSent();
    }
}
