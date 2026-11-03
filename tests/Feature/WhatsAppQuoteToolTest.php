<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\CreateQuoteFromPackTool;
use App\Models\Lead;
use App\Models\Pack;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return (string) $tool->handle(new Request($arguments));
    }

    public function test_creates_a_sent_quote_with_the_catalog_price_and_returns_the_public_link(): void
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
        $this->assertStringContainsString(route('quotes.public.show', $quote->approval_token), $result);
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
        $this->assertStringContainsString('Falta um e-mail', $this->callTool($this->tool(), ['pack_id' => $this->pack->id]));
        $this->assertStringContainsString('Falta um e-mail', $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'nao-e-email']));
        $this->assertSame(0, Quote::forTenant($this->tenant->id)->count());
    }

    public function test_pack_from_another_tenant_inactive_or_subscription_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $foreign = Pack::create(['tenant_id' => $other->id, 'name' => 'Pack Beta', 'slug' => 'pack-beta', 'price' => 900, 'video_quantity' => 8, 'status' => 'active']);
        $inactive = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Antigo', 'slug' => 'antigo', 'price' => 100, 'video_quantity' => 1, 'status' => 'inactive']);
        $subscription = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Plano Mensal', 'slug' => 'plano-mensal', 'price' => 300, 'video_quantity' => 4, 'status' => 'active', 'billing_type' => 'subscription']);

        foreach ([$foreign, $inactive] as $pack) {
            $this->assertStringContainsString('não encontrado', $this->callTool($this->tool(), ['pack_id' => $pack->id, 'client_email' => 'ana@ficticio.test']));
        }
        $this->assertStringContainsString('equipe retorna', $this->callTool($this->tool(), ['pack_id' => $subscription->id, 'client_email' => 'ana@ficticio.test']));
        $this->assertSame(0, Quote::withoutGlobalScopes()->count());
    }

    public function test_the_same_proposal_is_reused_instead_of_duplicated(): void
    {
        $first = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);
        $second = $this->callTool($this->tool(), ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']);

        $this->assertSame(1, Quote::forTenant($this->tenant->id)->count());
        $this->assertSame(Quote::forTenant($this->tenant->id)->value('approval_token'), $this->tokenFrom($first));
        $this->assertSame($this->tokenFrom($first), $this->tokenFrom($second));
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
            new ToolCall('call_1', 'CreateQuoteFromPackTool', ['pack_id' => $this->pack->id, 'client_email' => 'ana@ficticio.test']),
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
}
