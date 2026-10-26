<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoType;
use App\Services\VideoTypes\VideoTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Roteiro com tipo de vídeo: a Sofi escreve bloco a bloco e o roteiro guarda a cópia da estrutura.
 * Gemini sempre falso (Http::fake); dados fictícios.
 */
class RoteiroVideoTypeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Production $production;

    private VideoType $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente', 'email' => 'cliente@ficticio.test', 'company_name' => 'Padaria Ficticia', 'password' => 'secret12345']);
        $this->production = Production::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'created_by' => $this->admin->id,
            'title' => 'Pão de fermentação natural', 'description' => 'Mostrar o pão saindo do forno', 'status' => 'draft',
        ]);
        $this->sales = app(VideoTypeCatalog::class)->active($this->tenant->id)->first();

        config(['services.gemini.key' => 'chave-ficticia', 'services.gemini.model' => 'modelo-ficticio', 'services.gemini.fallback_models' => []]);
    }

    private function geminiReturns(array $payload): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
        ])]);
    }

    public function test_sofi_writes_each_block_of_the_type_with_ai(): void
    {
        $this->geminiReturns([
            'blocks' => ['hook' => 'Gancho IA', 'problem' => 'Dor IA', 'turn' => 'Virada IA', 'proof' => 'Prova IA', 'offer' => 'Oferta IA', 'cta' => 'CTA IA'],
            'seo_title' => 'Pão artesanal',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('roteiros.generate-sofi', $this->production), [
            'video_type_id' => $this->sales->id, 'language' => 'pt_BR',
        ])->assertOk();

        $data = $response->json('data');
        $this->assertSame('ai', $data['source']);
        $this->assertSame(['hook', 'problem', 'turn', 'proof', 'offer', 'cta'], array_column($data['blocks'], 'key'));
        $this->assertStringContainsString('🎬 [BLOCO 1 - GANCHO (00:00 - 00:03)]', $data['full_script']);
        $this->assertStringContainsString('CTA IA', $data['full_script']);
        $this->assertSame('30s', $data['estimated_time']);
        $this->assertSame('Pão artesanal', $data['seo_title']);

        Http::assertSent(fn ($request) => str_contains($request['contents'][0]['parts'][0]['text'], 'Vendas (30 s)')
            && $request->hasHeader('x-goog-api-key') && ! str_contains($request->url(), 'key='));
    }

    public function test_incomplete_ai_answer_falls_back_to_heuristic_blocks(): void
    {
        $this->geminiReturns(['blocks' => ['hook' => 'Só o gancho']]);

        $data = $this->actingAs($this->admin)->postJson(route('roteiros.generate-sofi', $this->production), [
            'video_type_id' => $this->sales->id, 'language' => 'es',
        ])->assertOk()->json('data');

        $this->assertSame('heuristic', $data['source']);
        $this->assertCount(6, $data['blocks']);
        $this->assertStringContainsString('🎬 [BLOQUE 6 - CTA (00:26 - 00:30)]', $data['full_script']);
        $this->assertStringNotContainsString('Só o gancho', $data['full_script']);
    }

    public function test_without_type_the_classic_framework_still_works(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);

        $data = $this->actingAs($this->admin)->postJson(route('roteiros.generate-sofi', $this->production), [
            'framework' => 'direct_offer', 'video_type_id' => null,
        ])->assertOk()->json('data');

        $this->assertArrayNotHasKey('blocks', $data);
        $this->assertNotEmpty($data['full_script']);
    }

    public function test_type_from_another_tenant_or_inactive_is_refused(): void
    {
        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $alheio = app(VideoTypeCatalog::class)->active($outra->id)->first();

        $this->actingAs($this->admin)->postJson(route('roteiros.generate-sofi', $this->production), ['video_type_id' => $alheio->id])
            ->assertUnprocessable()->assertJsonValidationErrors('video_type_id');

        $this->sales->update(['is_active' => false]);
        $this->actingAs($this->admin)->postJson(route('roteiros.generate-sofi', $this->production), ['video_type_id' => $this->sales->id])
            ->assertUnprocessable();
    }

    public function test_saving_keeps_a_copy_of_the_structure_and_sets_the_production_type(): void
    {
        $this->actingAs($this->admin)->post(route('roteiros.store', $this->production), [
            'content' => str_repeat('Cena do pão saindo do forno. ', 3),
            'video_type_id' => $this->sales->id,
        ])->assertRedirect();

        $roteiro = Roteiro::forTenant($this->tenant->id)->sole();
        $this->assertSame($this->sales->id, $roteiro->video_type_id);
        $this->assertSame('Vendas (30 s)', $roteiro->structure['name']);
        $this->assertCount(6, $roteiro->structure['blocks']);
        $this->assertSame($this->sales->id, $this->production->fresh()->video_type_id);

        // O admin muda o tipo depois: o roteiro continua com a estrutura que foi usada.
        $this->sales->update(['name' => 'Vendas renomeado', 'is_active' => false]);
        $this->actingAs($this->admin)->patch(route('roteiros.update', [$this->production, $roteiro]), [
            'content' => str_repeat('Cena revisada do pão. ', 4),
            'video_type_id' => $this->sales->id,
        ])->assertRedirect();

        $this->assertSame('Vendas (30 s)', $roteiro->fresh()->structure['name']);

        $this->actingAs($this->admin)->get(route('roteiros.show', [$this->production, $roteiro]))
            ->assertOk()->assertSee('Vendas (30 s)')->assertSee('Gancho');
        $this->actingAs($this->admin)->get(route('roteiros.edit', [$this->production, $roteiro]))
            ->assertOk()->assertSee('Vendas renomeado');
    }

    public function test_clearing_the_type_removes_the_structure(): void
    {
        $roteiro = $this->production->roteiros()->create([
            'tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id,
            'content' => str_repeat('x', 60), 'video_type_id' => $this->sales->id, 'structure' => $this->sales->snapshot(),
        ]);

        $this->actingAs($this->admin)->patch(route('roteiros.update', [$this->production, $roteiro]), [
            'content' => str_repeat('y', 60), 'video_type_id' => '',
        ])->assertRedirect();

        $this->assertNull($roteiro->fresh()->video_type_id);
        $this->assertNull($roteiro->fresh()->structure);
    }

    public function test_create_screen_offers_the_active_types(): void
    {
        $this->actingAs($this->admin)->get(route('roteiros.create', $this->production))
            ->assertOk()
            ->assertSee('Vendas (30 s)')
            ->assertSee('name="video_type_id"', false);
    }

    public function test_partials_have_no_inline_css(): void
    {
        foreach (['_video_type_select', '_structure'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/roteiros/{$view}.blade.php")), $view);
        }
    }
}
