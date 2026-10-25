<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cofre de tokens das contas conectadas pelo app próprio. Dados e tokens fictícios, sem rede.
 */
class SocialConnectionModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Cliente $clienteA;

    private Cliente $clienteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active']);
        $this->clienteA = Cliente::create(['tenant_id' => $this->tenantA->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->clienteB = Cliente::create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_tokens_are_encrypted_at_rest_and_decrypted_on_read(): void
    {
        $connection = $this->connection($this->clienteA);

        $raw = DB::table('social_connections')->where('id', $connection->id)->first();
        $this->assertNotSame('ya29.token-ficticio', $raw->access_token);
        $this->assertStringNotContainsString('token-ficticio', $raw->access_token);
        $this->assertStringNotContainsString('refresh-ficticio', $raw->refresh_token);

        $fresh = SocialConnection::forTenant($this->tenantA->id)->findOrFail($connection->id);
        $this->assertSame('ya29.token-ficticio', $fresh->access_token);
        $this->assertSame('1//refresh-ficticio', $fresh->refresh_token);
    }

    public function test_tokens_never_appear_in_array_or_json(): void
    {
        $connection = $this->connection($this->clienteA);

        $this->assertArrayNotHasKey('access_token', $connection->toArray());
        $this->assertArrayNotHasKey('refresh_token', $connection->toArray());
        $this->assertStringNotContainsString('token-ficticio', $connection->toJson());
        $this->assertStringNotContainsString('refresh-ficticio', $connection->toJson());
    }

    public function test_global_scope_isolates_tenants(): void
    {
        $this->connection($this->clienteA);
        $this->connection($this->clienteB, 'UC-canal-b');

        $staffA = User::create(['tenant_id' => $this->tenantA->id, 'name' => 'Staff A', 'email' => 'staff-a@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->actingAs($staffA);

        $this->assertSame(['UC-canal-a'], SocialConnection::pluck('external_account_id')->all());
        $this->assertSame(['UC-canal-b'], SocialConnection::forTenant($this->tenantB->id)->pluck('external_account_id')->all());
    }

    public function test_same_account_cannot_be_connected_twice_for_the_same_cliente(): void
    {
        $this->connection($this->clienteA);

        $this->expectException(QueryException::class);
        $this->connection($this->clienteA);
    }

    public function test_expiry_helpers_and_failure_marking(): void
    {
        $connection = $this->connection($this->clienteA);
        $this->assertTrue($connection->isActive());
        $this->assertFalse($connection->expiresWithin(5));

        $connection->update(['expires_at' => now()->addMinutes(2)]);
        $this->assertTrue($connection->expiresWithin(5));

        $connection->markFailed(str_repeat('x', 300), SocialConnection::STATUS_EXPIRED);
        $connection->refresh();
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $connection->status);
        $this->assertSame(100, mb_strlen($connection->last_error));
        $this->assertSame(0, SocialConnection::forTenant($this->tenantA->id)->active()->count());
    }

    public function test_publication_links_to_the_connection_and_defaults_to_upload_post(): void
    {
        $connection = $this->connection($this->clienteA);
        $staff = User::create(['tenant_id' => $this->tenantA->id, 'name' => 'Staff A', 'email' => 'staff-a@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $production = Production::create(['tenant_id' => $this->tenantA->id, 'cliente_id' => $this->clienteA->id, 'title' => 'Producao Ficticia', 'status' => 'draft', 'created_by' => $staff->id]);
        $video = Video::create(['tenant_id' => $this->tenantA->id, 'production_id' => $production->id, 'title' => 'Video ficticio', 'status' => 'ready', 'video_url' => 'https://exemplo.test/v.mp4']);

        $legacy = VideoPublication::create(['tenant_id' => $this->tenantA->id, 'cliente_id' => $this->clienteA->id, 'video_id' => $video->id, 'titulo' => 'Legado', 'plataformas' => ['youtube'], 'status' => 'pendente']);
        $native = VideoPublication::create([
            'tenant_id' => $this->tenantA->id, 'cliente_id' => $this->clienteA->id, 'video_id' => $video->id, 'titulo' => 'Nativo',
            'plataformas' => ['youtube'], 'status' => 'pendente', 'driver' => 'youtube', 'platform' => 'youtube', 'social_connection_id' => $connection->id,
        ]);

        $this->assertSame('upload_post', $legacy->fresh()->driver);
        $this->assertSame(0, $legacy->fresh()->attempts);
        $this->assertTrue($native->socialConnection->is($connection));

        $connection->delete();
        $this->assertNull($native->fresh()->social_connection_id);
    }

    private function connection(Cliente $cliente, string $accountId = 'UC-canal-a'): SocialConnection
    {
        return SocialConnection::create([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'platform' => 'youtube',
            'provider' => 'google',
            'external_account_id' => $accountId,
            'handle' => '@canalficticio',
            'access_token' => 'ya29.token-ficticio',
            'refresh_token' => '1//refresh-ficticio',
            'expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/youtube.upload'],
        ]);
    }
}
