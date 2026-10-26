<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configurações > Tipos de vídeo (admin da produtora). Dados fictícios.
 */
class VideoTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);
    }

    public function test_index_shows_the_default_sales_type_with_its_blocks(): void
    {
        $this->actingAs($this->admin)->get(route('video_types.index'))
            ->assertOk()
            ->assertSee('Vendas (30 s)')
            ->assertSee('Gancho')
            ->assertSee('0–3 s')
            ->assertSee('CTA')
            ->assertSee(route('video_types.create'), false);
    }

    public function test_admin_creates_a_type_with_its_own_blocks(): void
    {
        $this->actingAs($this->admin)->post(route('video_types.store'), [
            'name' => 'Posicionamento',
            'description' => 'Autoridade da marca',
            'duration_seconds' => 45,
            'sofi_guidance' => 'Tom de especialista',
            'is_active' => '1',
            'blocks' => [
                ['name' => 'Gancho', 'start' => 0, 'end' => 3, 'purpose' => 'Parar o scroll'],
                ['name' => 'Tese', 'start' => 3, 'end' => 35, 'purpose' => 'Opinião forte do especialista'],
                ['name' => '', 'start' => '', 'end' => '', 'purpose' => ''],
                ['name' => 'Convite', 'start' => 35, 'end' => 45, 'purpose' => 'Seguir o perfil'],
            ],
        ])->assertRedirect(route('video_types.index'));

        $type = VideoType::forTenant($this->tenant->id)->where('slug', 'posicionamento')->sole();
        $this->assertSame(45, $type->duration_seconds);
        $this->assertSame(['gancho', 'tese', 'convite'], array_column($type->blocks, 'key'));
        $this->assertTrue($type->is_active);
    }

    public function test_invalid_blocks_are_refused_with_a_message(): void
    {
        $this->actingAs($this->admin)->from(route('video_types.create'))->post(route('video_types.store'), [
            'name' => 'Quebrado',
            'duration_seconds' => 20,
            'blocks' => [
                ['name' => 'A', 'start' => 0, 'end' => 10],
                ['name' => 'B', 'start' => 8, 'end' => 30],
            ],
        ])->assertRedirect(route('video_types.create'))->assertSessionHasErrors();

        $this->assertFalse(VideoType::forTenant($this->tenant->id)->where('name', 'Quebrado')->exists());
    }

    public function test_admin_edits_toggles_and_deletes(): void
    {
        $this->actingAs($this->admin)->get(route('video_types.index'));
        $type = VideoType::forTenant($this->tenant->id)->sole();

        $this->actingAs($this->admin)->put(route('video_types.update', $type), [
            'name' => 'Vendas (45 s)', 'duration_seconds' => 45, 'is_active' => '1',
            'blocks' => [['name' => 'Gancho', 'start' => 0, 'end' => 5, 'purpose' => 'x'], ['name' => 'CTA', 'start' => 5, 'end' => 45, 'purpose' => 'y']],
        ])->assertRedirect(route('video_types.index'));
        $this->assertSame('Vendas (45 s)', $type->fresh()->name);
        $this->assertSame('vendas-45-s', $type->fresh()->slug);

        $this->actingAs($this->admin)->patch(route('video_types.toggle', $type))->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);

        $this->actingAs($this->admin)->delete(route('video_types.destroy', $type))->assertRedirect();
        $this->assertNull(VideoType::find($type->id));
    }

    public function test_editor_can_see_but_not_change_types(): void
    {
        $this->actingAs($this->admin)->get(route('video_types.index'));
        $type = VideoType::forTenant($this->tenant->id)->sole();

        $this->actingAs($this->editor)->get(route('video_types.index'))->assertOk()->assertDontSee(route('video_types.create'), false);
        $this->actingAs($this->editor)->get(route('video_types.create'))->assertForbidden();
        $this->actingAs($this->editor)->post(route('video_types.store'), ['name' => 'X', 'duration_seconds' => 30, 'blocks' => [['name' => 'A', 'start' => 0, 'end' => 30]]])->assertForbidden();
        $this->actingAs($this->editor)->patch(route('video_types.toggle', $type))->assertForbidden();
        $this->actingAs($this->editor)->delete(route('video_types.destroy', $type))->assertForbidden();
    }

    public function test_admin_of_another_tenant_cannot_touch_the_types(): void
    {
        $this->actingAs($this->admin)->get(route('video_types.index'));
        $type = VideoType::forTenant($this->tenant->id)->sole();

        $outra = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $intruso = User::create(['tenant_id' => $outra->id, 'name' => 'Admin B', 'email' => 'admin-b@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);

        $this->actingAs($intruso)->get(route('video_types.edit', $type))->assertNotFound();
        $this->actingAs($intruso)->delete(route('video_types.destroy', $type))->assertNotFound();
        $this->assertNotNull($type->fresh());
    }

    public function test_screens_have_no_inline_css(): void
    {
        foreach (['index', 'form'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/settings/video_types/{$view}.blade.php")), $view);
        }
    }
}
