<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoteiroEditingAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $editor;
    private Production $production;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Audiovisual Zaragoza',
            'slug' => 'produtora-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->editor = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Produtor',
            'email' => 'carlos@zaragoza.es',
            'password' => bcrypt('secret123'),
            'role' => 'editor',
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alejandro Martínez',
            'email' => 'alejandro@empresa.es',
            'password' => bcrypt('secret123'),
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'created_by' => $this->editor->id,
            'title' => 'Campanha Zaragoza — Vídeo 01',
            'status' => 'pending_review',
        ]);
    }

    public function test_editor_can_edit_draft_and_submitted_roteiro(): void
    {
        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->editor->id,
            'content' => 'Gancho 0-3s de alto impacto para prender a atenção do público em Zaragoza.',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->editor)
            ->get(route('roteiros.edit', ['production' => $this->production, 'roteiro' => $roteiro]));

        $response->assertOk();
        $response->assertViewIs('roteiros.edit');
        $response->assertSee('Roteiro Aguardando Aprovação do Cliente');
    }

    public function test_editor_can_update_submitted_roteiro(): void
    {
        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->editor->id,
            'content' => 'Conteúdo inicial do roteiro que precisa de mais de 50 caracteres para ser validado com sucesso.',
            'status' => 'submitted',
        ]);

        $newContent = 'Conteúdo novo devidamente revisado pela equipe técnica da produtora e pronto para validação.';

        $response = $this->actingAs($this->editor)
            ->patch(route('roteiros.update', ['production' => $this->production, 'roteiro' => $roteiro]), [
                'content' => $newContent,
            ]);

        $response->assertRedirect(route('roteiros.show', ['production' => $this->production, 'roteiro' => $roteiro]));
        $this->assertDatabaseHas('roteiros', [
            'id' => $roteiro->id,
            'content' => $newContent,
        ]);
    }

    public function test_approved_roteiro_cannot_be_edited_and_redirects_without_type_error(): void
    {
        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->editor->id,
            'content' => 'Conteúdo que já foi aprovado e congelado para o set de gravação e captação 4K.',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->editor)
            ->get(route('roteiros.edit', ['production' => $this->production, 'roteiro' => $roteiro]));

        $response->assertRedirect(route('roteiros.show', ['production' => $this->production, 'roteiro' => $roteiro]));
        $response->assertSessionHas('error');
    }
}
