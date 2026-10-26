<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoType;
use App\Services\VideoTypes\VideoTypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Catálogo de tipos de vídeo por produtora. Dados fictícios.
 */
class VideoTypeCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private VideoTypeCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->catalog = app(VideoTypeCatalog::class);
    }

    public function test_tenant_without_types_gets_the_sales_type_with_the_six_blocks(): void
    {
        $types = $this->catalog->active($this->tenant->id);

        $this->assertCount(1, $types);
        $sales = $types->first();
        $this->assertSame('Vendas (30 s)', $sales->name);
        $this->assertSame(30, $sales->duration_seconds);
        $this->assertSame(['hook', 'problem', 'turn', 'proof', 'offer', 'cta'], array_column($sales->blocks, 'key'));
        $this->assertSame([0, 3], [$sales->blocks[0]['start'], $sales->blocks[0]['end']]);
        $this->assertSame([26, 30], [$sales->blocks[5]['start'], $sales->blocks[5]['end']]);

        // Chamar de novo não duplica.
        $this->catalog->active($this->tenant->id);
        $this->assertSame(1, VideoType::forTenant($this->tenant->id)->count());
    }

    public function test_defaults_are_not_recreated_once_the_admin_has_types(): void
    {
        $this->catalog->ensureDefaults($this->tenant->id);
        VideoType::forTenant($this->tenant->id)->update(['is_active' => false]);

        $this->assertCount(0, $this->catalog->active($this->tenant->id));
        $this->assertCount(1, $this->catalog->all($this->tenant->id));
    }

    public function test_types_are_isolated_per_tenant(): void
    {
        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active']);
        $this->catalog->ensureDefaults($this->tenant->id);
        VideoType::create(['tenant_id' => $outra->id, 'name' => 'Podcast', 'slug' => 'podcast', 'duration_seconds' => 60, 'blocks' => VideoType::salesBlocks()]);

        $this->assertSame(['Vendas (30 s)'], $this->catalog->active($this->tenant->id)->pluck('name')->all());
        $this->assertSame(['Podcast'], $this->catalog->active($outra->id)->pluck('name')->all());
    }

    public function test_blocks_are_normalized_and_validated(): void
    {
        $blocks = $this->catalog->normalizeBlocks([
            ['name' => 'Abertura', 'start' => 0, 'end' => 5, 'purpose' => 'Chamar atenção'],
            ['name' => '', 'start' => '', 'end' => '', 'purpose' => ''],
            ['name' => 'Abertura', 'start' => 5, 'end' => 20, 'purpose' => 'Repetido de propósito'],
        ], 20);

        $this->assertSame(['abertura', 'abertura_2'], array_column($blocks, 'key'));
        $this->assertCount(2, $blocks);

        foreach ([
            [['name' => 'A', 'start' => 0, 'end' => 10], ['name' => 'B', 'start' => 5, 'end' => 12]], // sobrepõe
            [['name' => 'A', 'start' => 0, 'end' => 40]], // passa da duração
            [['name' => 'A', 'start' => 3, 'end' => 3]], // fim igual ao início
            [], // nenhum bloco
        ] as $invalid) {
            try {
                $this->catalog->normalizeBlocks($invalid, 30);
                $this->fail('blocos inválidos deveriam ser recusados');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_slug_is_unique_per_tenant(): void
    {
        $this->catalog->ensureDefaults($this->tenant->id);

        $this->assertSame('vendas-30s-2', $this->catalog->uniqueSlug($this->tenant->id, 'Vendas 30s'));
        $this->assertSame('podcast', $this->catalog->uniqueSlug($this->tenant->id, 'Podcast'));
    }

    public function test_roteiro_keeps_a_snapshot_even_if_the_type_changes_later(): void
    {
        $this->catalog->ensureDefaults($this->tenant->id);
        $type = VideoType::forTenant($this->tenant->id)->sole();
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'title' => 'Campanha', 'status' => 'draft', 'created_by' => $staff->id, 'video_type_id' => $type->id]);

        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'created_by' => $staff->id,
            'video_type_id' => $type->id, 'structure' => $type->snapshot(), 'content' => str_repeat('Roteiro ficticio. ', 5), 'status' => 'draft',
        ]);

        $type->update(['name' => 'Vendas (45 s)', 'duration_seconds' => 45]);

        $this->assertSame('Vendas (30 s)', $roteiro->fresh()->structure['name']);
        $this->assertSame(30, $roteiro->fresh()->structure['duration_seconds']);
        $this->assertTrue($production->fresh()->videoType->is($type));

        $type->delete();
        $this->assertNull($roteiro->fresh()->video_type_id);
        $this->assertSame('Vendas (30 s)', $roteiro->fresh()->structure['name']);
    }
}
