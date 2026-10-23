<?php

namespace App\Ai\Tools;

use App\Models\Pack;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Catálogo público da vitrine de uma produtora (somente leitura).
 *
 * O tenant vem do construtor e é filtrado explicitamente: o chat da vitrine é público,
 * e com um usuário logado de outra produtora o scope BelongsToTenant filtraria pelo
 * tenant errado. Devolve só o que a vitrine já exibe: pacotes ativos, preço, quantidade
 * de vídeos e adicionais com preço.
 */
class ListTenantPacksTool implements Tool
{
    public function __construct(
        protected int $tenantId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Lista os pacotes de vídeo ativos desta produtora, com preço (sem IVA), quantidade de vídeos e adicionais. Use para recomendar um pacote pelo id.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->packs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, video_quantity: int, price: float, currency: string, description: ?string, addons: list<array{name: string, price: float}>}>
     */
    public function packs(): array
    {
        return Pack::forTenant($this->tenantId)
            ->where('status', 'active')
            ->with(['addons' => fn ($query) => $query->forTenant($this->tenantId)])
            ->orderBy('price')
            ->get()
            ->map(fn (Pack $pack): array => [
                'id' => $pack->id,
                'name' => $pack->translated_name,
                'slug' => $pack->slug,
                'video_quantity' => (int) $pack->video_quantity,
                'price' => (float) $pack->price,
                'currency' => $pack->currency ?? 'EUR',
                'description' => $pack->translated_description,
                'addons' => array_values(array_map(fn (array $addon): array => [
                    'name' => (string) ($addon['name'] ?? ''),
                    'price' => (float) ($addon['price'] ?? 0),
                ], $pack->effective_addons)),
            ])
            ->values()
            ->all();
    }
}
