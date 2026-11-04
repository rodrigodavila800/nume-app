<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\BuildCustomPackageTool;
use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Addon;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\VideoType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * A Sofi monta o pacote na conversa (BuildCustomPackageTool). Dados fictícios; PDF na fila simulada.
 */
class WhatsAppCustomPackageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendQuoteProposalDocuments::class]);
        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        VideoType::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels 30 s', 'slug' => 'reels', 'duration_seconds' => 30, 'blocks' => [], 'builder_price' => 100, 'is_active' => true]);
        VideoType::create(['tenant_id' => $this->tenant->id, 'name' => 'Institucional', 'slug' => 'institucional', 'duration_seconds' => 90, 'blocks' => [], 'builder_price' => 250, 'is_active' => true]);
        Addon::create(['tenant_id' => $this->tenant->id, 'name' => 'Drone', 'slug' => 'drone', 'default_price' => 150, 'currency' => 'eur', 'is_active' => true, 'in_builder' => true]);
    }

    private function build(array $arguments, ?int $leadId = null, ?int $tenantId = null): string
    {
        return (string) (new BuildCustomPackageTool($tenantId ?? $this->tenant->id, null, '34600000001', $leadId))->handle(new Request($arguments));
    }

    private function closing(): array
    {
        return ['confirm' => true, 'client_email' => 'ana@ficticio.test', 'responsible_name' => 'Ana', 'company_name' => 'Padaria Ficticia', 'niche' => 'Padaria'];
    }

    public function test_without_confirm_it_only_previews_and_creates_nothing(): void
    {
        $catalog = $this->build([]);
        $preview = $this->build(['items' => [['video_type' => 'reels 30 s', 'quantity' => 3], ['video_type' => 'Institucional', 'quantity' => 1]], 'addon_names' => ['Drone']]);

        $this->assertStringContainsString('Reels 30 s (30 s, 100,00 € por vídeo)', $catalog);
        $this->assertStringContainsString('Prévia do pacote montado', $preview);
        $this->assertStringContainsString('3× Reels 30 s = 300,00 €', $preview);
        $this->assertStringContainsString('total 847,00 €', $preview);
        $this->assertSame(0, Quote::count());
        Queue::assertNothingPushed();
    }

    public function test_unknown_types_or_extras_are_refused_with_the_catalog(): void
    {
        $result = $this->build(['items' => [['video_type' => 'Longa-metragem', 'quantity' => 1]], 'addon_names' => ['Helicóptero']]);

        $this->assertStringContainsString('Não existe no montador: Longa-metragem, Helicóptero', $result);
        $this->assertStringContainsString('Tipos de vídeo do montador', $result);
    }

    public function test_confirming_needs_the_closing_data(): void
    {
        $result = $this->build(['items' => [['video_type' => 'Reels 30 s', 'quantity' => 2]], 'confirm' => true, 'company_name' => 'Não informada']);

        $this->assertStringContainsString('e-mail válido, nome do responsável, nome da empresa', $result);
        $this->assertSame(0, Quote::count());
    }

    public function test_confirming_creates_the_proposal_sends_the_pdf_and_updates_the_crm(): void
    {
        $lead = Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'Contato WhatsApp 34600000001', 'phone' => '34600000001', 'source' => 'whatsapp']);

        $result = $this->build(['items' => [['video_type' => 'Reels 30 s', 'quantity' => 3]], 'addon_names' => ['Drone']] + $this->closing(), $lead->id);

        $quote = Quote::forTenant($this->tenant->id)->sole();
        $this->assertSame('sent', $quote->status);
        $this->assertSame('whatsapp', $quote->request_details['source']);
        $this->assertSame('34600000001', $quote->request_details['phone']);
        $this->assertEquals(450.00, $quote->subtotal);
        $this->assertEquals(544.50, $quote->total_amount);
        $this->assertStringContainsString(route('portal.register'), $result);
        $this->assertStringContainsString('PDF formal da proposta chega em seguida', $result);
        Queue::assertPushed(SendQuoteProposalDocuments::class, fn ($job) => $job->quoteId === $quote->id && $job->whatsAppTo === '34600000001');

        $lead->refresh();
        $this->assertSame('Ana', $lead->name);
        $this->assertSame('proposta', $lead->stage);
    }

    public function test_another_tenant_never_sees_this_catalog(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);

        $this->assertStringContainsString('ainda não liberou o montador', $this->build(['items' => [['video_type' => 'Reels 30 s', 'quantity' => 1]]], tenantId: $other->id));
    }

    public function test_the_whatsapp_agent_has_the_builder_tool(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);
        SofiWhatsAppAgent::fake([
            new ToolCall('call_1', 'BuildCustomPackageTool', ['items' => [['video_type' => 'Reels 30 s', 'quantity' => 2]]]),
            'Montei: 2 Reels por 242,00 € com IVA.',
        ])->preventStrayPrompts();

        $agent = new SofiWhatsAppAgent($this->tenant->id, null, '', false, [], null, '34600000001', '');

        $this->assertSame('Montei: 2 Reels por 242,00 € com IVA.', (string) $agent->prompt('Quero só 2 reels'));
        $this->assertStringContainsString('BuildCustomPackageTool', (string) $agent->instructions());
        $this->assertSame(0, Quote::count(), 'prévia não cria proposta');
    }
}
