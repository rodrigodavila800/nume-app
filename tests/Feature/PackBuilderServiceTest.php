<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoType;
use App\Services\Quotes\PackBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo e preço do "Monte seu pacote". Dados fictícios.
 */
class PackBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private VideoType $reels;

    private VideoType $institucional;

    private Addon $drone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha', 'status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
        $this->reels = $this->type($this->tenant, 'Reels 30 s', 100);
        $this->institucional = $this->type($this->tenant, 'Institucional', 250);
        $this->type($this->tenant, 'Sem preço', null);
        $this->type($this->tenant, 'Inativo', 80)->forceFill(['is_active' => false])->save();
        $this->drone = $this->addon($this->tenant, 'Drone', 150, inBuilder: true);
        $this->addon($this->tenant, 'Só em pacote pronto', 90, inBuilder: false);
    }

    private function type(Tenant $tenant, string $name, ?float $price): VideoType
    {
        return VideoType::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => str($name)->slug(), 'duration_seconds' => 30, 'blocks' => [], 'builder_price' => $price, 'is_active' => true]);
    }

    private function addon(Tenant $tenant, string $name, float $price, bool $inBuilder): Addon
    {
        return Addon::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => str($name)->slug(), 'default_price' => $price, 'currency' => 'eur', 'is_active' => true, 'in_builder' => $inBuilder]);
    }

    private function service(): PackBuilderService
    {
        return app(PackBuilderService::class);
    }

    public function test_the_catalog_has_only_priced_active_types_and_builder_addons_of_the_tenant(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $this->type($other, 'Da outra', 999);
        $this->addon($other, 'Da outra', 999, inBuilder: true);

        $catalog = $this->service()->catalog($this->tenant);

        $this->assertSame(['Reels 30 s', 'Institucional'], array_column($catalog['video_types'], 'name'));
        $this->assertSame(['Drone'], array_column($catalog['addons'], 'name'));
        $this->assertEquals(21.0, $catalog['vat_rate']);
    }

    public function test_the_price_is_the_sum_of_quantities_and_extras_with_vat_and_deposit(): void
    {
        $priced = $this->service()->price($this->tenant, [
            'items' => [$this->reels->id => 3, $this->institucional->id => 2],
            'addons' => [$this->drone->id],
        ]);

        $this->assertSame(5, $priced['videos']);
        $this->assertEquals(950.00, $priced['subtotal']);
        $this->assertEquals(199.50, $priced['tax_amount']);
        $this->assertEquals(1149.50, $priced['total']);
        $this->assertEquals(574.75, $priced['deposit']);
        $this->assertEquals(574.75, $priced['balance']);
        $this->assertSame(['video', 'video', 'addon'], array_column($priced['lines'], 'kind'));
    }

    public function test_tampered_selections_never_change_the_price(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $foreign = $this->type($other, 'Da outra', 1);
        $hidden = Addon::where('name', 'Só em pacote pronto')->sole();

        $priced = $this->service()->price($this->tenant, [
            'items' => [$this->reels->id => 999, $foreign->id => 5, 99999 => 3, $this->institucional->id => -4],
            'addons' => [$hidden->id, $this->drone->id, $this->drone->id],
        ]);

        $this->assertSame(PackBuilderService::MAX_PER_TYPE, $priced['videos']);
        $this->assertEquals(PackBuilderService::MAX_PER_TYPE * 100 + 150, $priced['subtotal'], 'drone uma vez; adicional fora do montador ignorado');
    }

    public function test_without_videos_there_is_no_package(): void
    {
        $this->assertNull($this->service()->price($this->tenant, ['items' => [], 'addons' => [$this->drone->id]]));
        $this->assertNull($this->service()->price($this->tenant, ['items' => [$this->reels->id => 0]]));
    }

    public function test_the_admin_sets_the_builder_price_and_marks_addons_for_the_builder(): void
    {
        $admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);

        $this->actingAs($admin)->put(route('video_types.update', $this->reels), [
            'name' => 'Reels 30 s', 'duration_seconds' => 30, 'builder_price' => '120.50', 'is_active' => '1',
            'blocks' => [['key' => 'gancho', 'name' => 'Gancho', 'start' => 0, 'end' => 30, 'purpose' => 'Parar o scroll']],
        ])->assertRedirect();
        $this->assertEquals(120.50, $this->reels->fresh()->builder_price);

        $this->actingAs($admin)->post(route('addons.store'), ['name' => 'Legendas', 'default_price' => 40, 'is_active' => '1', 'in_builder' => '1'])->assertRedirect();
        $this->assertTrue(Addon::where('name', 'Legendas')->sole()->in_builder);
    }
}
