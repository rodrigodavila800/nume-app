<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\CreateQuoteFromPackTool;
use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Pack;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Payment\PaymentMethodAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * Proposta de pacote criada pela Sofi no WhatsApp. Pacotes, e-mails e telefones fictícios; sem rede.
 */
class WhatsAppQuoteToolTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendQuoteProposalDocuments::class]);
        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Reels Starter', 'slug' => 'reels-starter', 'price' => 500,
            'vat_rate' => 21, 'video_quantity' => 4, 'status' => 'active', 'billing_type' => 'one_time', 'billing_mode' => 'full_upfront',
        ]);
    }

    private function tool(?int $leadId = null, string $phone = '34600000001'): CreateQuoteFromPackTool
    {
        return new CreateQuoteFromPackTool($this->tenant->id, null, $phone, $leadId);
    }

    private function callTool(CreateQuoteFromPackTool $tool, array $arguments): string
    {
        $closing = ['responsible_name' => 'Carlos Ficticio', 'company_name' => 'Padaria Ficticia', 'niche' => 'Padaria'];

        return (string) $tool->handle(new Request($arguments + $closing));
    }

    public function test_creates_a_sent_quote_with_the_catalog_price_and_points_to_the_portal_registration(): void
    {
        $lead = Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos', 'phone' => '34600000001', 'source' => 'whatsapp']);

        $result = $this->callTool($this->tool($lead->id), ['pack_id' => $this->pack->id, 'client_email' => 'Carlos@Ficticio.test']);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('carlos@ficticio.test', $quote->client_email);
        $this->assertEquals(500.00, $quote->subtotal);
        $this->assertEquals(605.00, $quote->total_amount);
        $this->assertEquals(605.00, $quote->deposit_amount);
        $this->assertEquals(0.00, $quote->balance_amount);
        $this->assertSame(4, $quote->inputs['videos']);
        $this->assertNotEmpty($quote->approval_token);
        $this->assertStringNotContainsString('/orcamento/', $result, 'o link com token vai só no e-mail');
        $this->assertStringContainsString('AINDA NÃO TEM CONTA', $result);
        $this->assertStringContainsString(route('portal.register'), $result);
        $this->assertStringContainsString('605,00 €', $result);
        $this->assertStringContainsString('Pagamento integral', $result);

        $this->assertSame(1, Lead::withoutGlobalScopes()->count(), 'o CRM reaproveita o lead do WhatsApp');
        $lead->refresh();
        $this->assertSame('proposta', $lead->stage);
        $this->assertSame('carlos@ficticio.test', $lead->email);
    }

    public function test_split_pack_uses_its_own_deposit_percentage_and_social_limits(): void
    {
        $this->pack->update(['billing_mode' => 'split_50_50', 'deposit_percentage' => 30, 'social_networks_limit' => 3, 'monthly_post_limit' => 20]);

        $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertEquals(181.50, $quote->deposit_amount);
        $this->assertEquals(423.50, $quote->balance_amount);
        $this->assertSame(3, $quote->inputs['redes']);
        $this->assertSame(20, $quote->inputs['envios_mes']);
    }

    public function test_addons_are_added_by_exact_name_with_the_catalog_price(): void
    {
        $this->pack->update(['addons' => [['name' => 'Drone', 'price' => 100]]]);

        $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test', 'addon_names' => ['drone']]);

        $this->assertEquals(600.00, Quote::forTenant($this->tenant->id)->sole()->subtotal);
    }

    public function test_unknown_addon_is_refused_and_nothing_is_created(): void
    {
        $result = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test', 'addon_names' => ['Helicóptero']]);

        $this->assertStringContainsString('Adicional não existe', $result);
        $this->assertSame(0, Quote::forTenant($this->tenant->id)->count());
    }

    public function test_missing_or_invalid_email_asks_for_it_and_creates_nothing(): void
    {
        $this->assertStringContainsString('e-mail válido', $this->callTool($this->tool(), ['pack_id' => $this->pack->id]));
        $this->assertStringContainsString('e-mail válido', $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'nao-e-email']));
        $this->assertSame(0, Quote::forTenant($this->tenant->id)->count());
    }

    public function test_pack_from_another_tenant_inactive_or_pilot_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $foreign = Pack::create(['tenant_id' => $other->id, 'name' => 'Pack Beta', 'slug' => 'pack-beta', 'price' => 900, 'video_quantity' => 8, 'status' => 'active']);
        $inactive = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Antigo', 'slug' => 'antigo', 'price' => 100, 'video_quantity' => 1, 'status' => 'inactive']);
        $pilot = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Piloto', 'slug' => 'piloto', 'price' => 300, 'video_quantity' => 4, 'status' => 'active', 'billing_mode' => 'pilot_entry']);

        foreach ([$foreign, $inactive] as $pack) {
            $this->assertStringContainsString('não encontrado', $this->callTool($this->tool(), ['pack_id' => $pack->id, 'client_email' => 'ana@ficticio.test']));
        }
        $this->assertStringContainsString('EscalateToTeamTool', $this->callTool($this->tool(), ['pack_id' => $pilot->id, 'client_email' => 'ana@ficticio.test']));
        $this->assertSame(0, Quote::withoutGlobalScopes()->count());
    }

    public function test_the_same_proposal_is_reused_instead_of_duplicated(): void
    {
        $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);
        $this->markDocumentsSent();
        $second = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        $this->assertSame(1, Quote::forTenant($this->tenant->id)->count());
        $this->assertStringContainsString('já foi enviado antes', $second);
    }

    public function test_an_existing_account_is_sent_to_the_portal_login(): void
    {
        Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $result = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        $this->assertStringContainsString('JÁ TEM CONTA', $result);
        $this->assertStringContainsString(route('portal.login'), $result);
        $this->assertStringNotContainsString(route('portal.register'), $result);
    }

    public function test_only_the_active_payment_methods_are_offered(): void
    {
        TenantSetting::set($this->tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant, 'bank_transfer_enabled', '1');

        $result = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        $availability = PaymentMethodAvailability::forTenant($this->tenant);
        $active = $availability->activeMethodLabels($this->pack);
        $this->assertContains('transferência bancária', $active);
        $this->assertStringContainsString('Formas de pagamento disponíveis: '.implode(', ', $active).'.', $result);
        if (! $availability->stripeEnabled($this->pack)) {
            $this->assertStringNotContainsString('Stripe', $result);
        }
    }

    public function test_daily_limit_per_phone_stops_proposal_spam(): void
    {
        foreach (range(1, CreateQuoteFromPackTool::DAILY_LIMIT) as $n) {
            $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => "pessoa{$n}@ficticio.test"]);
        }

        $result = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'outra@ficticio.test']);

        $this->assertStringContainsString('Limite de propostas', $result);
        $this->assertSame(CreateQuoteFromPackTool::DAILY_LIMIT, Quote::forTenant($this->tenant->id)->count());
        $this->assertSame(1, Quote::forTenant($this->tenant->id)->where('request_details->phone', '34600000001')->where('client_email', 'pessoa1@ficticio.test')->count());

        $this->callTool($this->tool(phone: '34600000002'), ['pack_id' => $this->pack->id, 'client_email' => 'outra@ficticio.test']);
        $this->assertSame(CreateQuoteFromPackTool::DAILY_LIMIT + 1, Quote::forTenant($this->tenant->id)->count(), 'o limite é por telefone');
    }

    public function test_the_whatsapp_agent_has_the_tool_registered(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);
        SofiWhatsAppAgent::fake([
            new ToolCall('call_1', 'CreateQuoteFromPackTool', ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test', 'responsible_name' => 'Ana', 'company_name' => 'Padaria Ficticia']),
            'Aqui está a sua proposta.',
        ])->preventStrayPrompts();

        $agent = new SofiWhatsAppAgent($this->tenant->id, null, '', false, [], null, '34600000001', '');
        $reply = (string) $agent->prompt('Pode fechar o Reels Starter, meu e-mail é ana@ficticio.test');

        $this->assertSame('Aqui está a sua proposta.', $reply);
        $this->assertSame('sent', Quote::forTenant($this->tenant->id)->sole()->status);
    }

    private function tokenFrom(string $result): string
    {
        preg_match('#/orcamento/([A-Za-z0-9]+)#', $result, $match);

        return $match[1] ?? '';
    }

    public function test_the_closing_data_is_required_and_lands_on_the_quote_and_the_crm(): void
    {
        $lead = Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'Contato WhatsApp 34600000001', 'phone' => '34600000001', 'source' => 'whatsapp']);

        $missing = (string) $this->tool($lead->id)->handle(new Request(['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']));
        $this->assertStringContainsString('nome do responsável, nome da empresa', $missing);
        $this->assertSame(0, Quote::forTenant($this->tenant->id)->count());

        $this->callTool($this->tool($lead->id), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test', 'responsible_name' => 'Ana <b>Souza</b>', 'company_name' => 'Clínica Sorriso', 'niche' => 'Odontologia']);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertSame('Clínica Sorriso', $quote->client_name);
        $this->assertSame('Ana Souza', $quote->client_contact, 'sem HTML');
        $this->assertSame('Odontologia', $quote->request_details['niche']);

        $lead->refresh();
        $this->assertSame('Ana Souza', $lead->name);
        $this->assertSame('Clínica Sorriso', $lead->company);
        $this->assertStringContainsString('Nicho: Odontologia', $lead->activities()->where('type', 'whatsapp')->latest('id')->value('body'));
    }

    public function test_a_subscription_plan_becomes_a_first_period_proposal(): void
    {
        $plan = Pack::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Nume Ads Starter', 'slug' => 'ads-starter', 'price' => 450, 'vat_rate' => 21,
            'video_quantity' => 3, 'status' => 'active', 'billing_type' => 'subscription', 'billing_interval' => 'month', 'commitment_months' => 6,
            'billing_mode' => 'split_50_50', 'deposit_percentage' => 50,
        ]);

        $result = $this->callTool($this->tool(), ['pack_id' => $plan->id, 'client_email' => 'ana@ficticio.test']);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertEquals(450.00, $quote->subtotal);
        $this->assertEquals(544.50, $quote->total_amount);
        $this->assertEquals(544.50, $quote->deposit_amount, 'cobra o primeiro mês inteiro, sem sinal');
        $this->assertEquals(0.00, $quote->balance_amount);
        $this->assertSame('Nume Ads Starter (mensal, contrato de 6 meses)', $quote->project_title);
        $this->assertSame(['interval' => 'month', 'commitment_months' => 6], $quote->request_details['subscription']);
        $this->assertStringContainsString('450,00 € + IVA por mês, contrato de 6 meses', $result);
        $this->assertStringContainsString('faturados pela equipe', $result);
    }

    public function test_a_new_proposal_queues_the_pdf_by_email_and_whatsapp_once(): void
    {
        $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);
        $this->markDocumentsSent();
        $second = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        Queue::assertPushed(SendQuoteProposalDocuments::class, 1);
        Queue::assertPushed(SendQuoteProposalDocuments::class, fn (SendQuoteProposalDocuments $job) => $job->quoteId === $quote->id
            && $job->tenantId === $this->tenant->id
            && $job->whatsAppTo === '34600000001');
        $this->assertStringNotContainsString('PDF formal', $second, 'proposta reaproveitada não promete novo envio');
    }

    private function markDocumentsSent(): void
    {
        $quote = Quote::forTenant($this->tenant->id)->latest('id')->first();
        $quote->forceFill(['request_details' => ($quote->request_details ?? []) + ['documents_sent_at' => now()->toIso8601String()]])->save();
    }

    public function test_a_reused_proposal_that_never_had_its_pdf_sent_sends_it_now(): void
    {
        $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);
        $second = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        Queue::assertPushed(SendQuoteProposalDocuments::class, 2);
        $this->assertStringContainsString('chega em seguida', $second);
    }
}
