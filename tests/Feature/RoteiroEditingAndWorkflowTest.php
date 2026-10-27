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

    public function test_producer_can_edit_draft_and_rejected_roteiro_manually_or_with_sofi(): void
    {
        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->editor->id,
            'content' => 'Gancho 0-3s de alto impacto para prender a atenção do público em Zaragoza.',
            'status' => 'rejected',
            'rejection_reason' => 'Por favor, destacar a garantia de 2 anos no bloco final.',
        ]);

        $response = $this->actingAs($this->editor)
            ->get(route('roteiros.edit', ['production' => $this->production, 'roteiro' => $roteiro]));

        $response->assertOk();
        $response->assertViewIs('roteiros.edit');
        $response->assertSee('Por favor, destacar a garantia de 2 anos no bloco final.');

        $newContent = 'Conteúdo ajustado pela produtora destacando a garantia de 2 anos no bloco final.';

        $updateResponse = $this->actingAs($this->editor)
            ->patch(route('roteiros.update', ['production' => $this->production, 'roteiro' => $roteiro]), [
                'content' => $newContent,
            ]);

        $updateResponse->assertRedirect(route('roteiros.show', ['production' => $this->production, 'roteiro' => $roteiro]));
        $this->assertDatabaseHas('roteiros', [
            'id' => $roteiro->id,
            'content' => $newContent,
            'status' => 'draft',
            'rejection_reason' => null,
        ]);
    }

    public function test_submitted_roteiro_in_client_review_cannot_be_edited_manually(): void
    {
        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->editor->id,
            'content' => 'Conteúdo enviado para revisão do cliente no portal e bloqueado contra edição manual.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->editor)
            ->get(route('roteiros.show', ['production' => $this->production, 'roteiro' => $roteiro]))
            ->assertOk()
            ->assertSee('Em Revisão pelo Cliente (Edição Bloqueada)')
            ->assertDontSee(route('roteiros.edit', ['production' => $this->production, 'roteiro' => $roteiro]), false);

        $this->actingAs($this->editor)
            ->get(route('roteiros.edit', ['production' => $this->production, 'roteiro' => $roteiro]))
            ->assertRedirect(route('roteiros.show', ['production' => $this->production, 'roteiro' => $roteiro]))
            ->assertSessionHas('error');

        $this->actingAs($this->editor)
            ->patch(route('roteiros.update', ['production' => $this->production, 'roteiro' => $roteiro]), [
                'content' => 'Tentativa de alterar roteiro enquanto está em revisão pelo cliente.',
            ])
            ->assertRedirect(route('roteiros.show', ['production' => $this->production, 'roteiro' => $roteiro]))
            ->assertSessionHas('error');

        $this->assertSame(
            'Conteúdo enviado para revisão do cliente no portal e bloqueado contra edição manual.',
            $roteiro->fresh()->content
        );
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
