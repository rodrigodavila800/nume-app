<?php

namespace App\Services\Payment;

use App\Models\Addon;
use App\Models\Pack;
use Illuminate\Support\Collection;

class PackCheckoutPricing
{
    /**
     * @param  array<int, array{id: ?int, name: string, price: float, slug: ?string}>  $addonsSnapshot
     * @param  array<int, string>  $addonNames
     */
    public function __construct(
        public readonly Pack $pack,
        public readonly array $addonsSnapshot,
        public readonly array $addonNames,
        public readonly float $addonsTotal,
        public readonly float $subtotal
    ) {}

    /**
     * Resolve os preços e adicionais do pacote a partir dos itens selecionados.
     *
     * @param  array<int, mixed>  $selectedAddons
     */
    public static function resolve(Pack $pack, array $selectedAddons = []): self
    {
        $available = static::availableAddons($pack);

        $snapshot = [];
        $names = [];
        $addonsTotal = 0.0;

        foreach ($selectedAddons as $selected) {
            $matched = $available->first(function ($addon) use ($selected) {
                return (isset($addon['id']) && (string) $addon['id'] === (string) $selected)
                    || (isset($addon['name']) && (string) $addon['name'] === (string) $selected)
                    || (isset($addon['slug']) && (string) $addon['slug'] === (string) $selected);
            });

            if ($matched) {
                $price = round((float) ($matched['price'] ?? 0), 2);
                $snapshot[] = [
                    'id' => isset($matched['id']) && $matched['id'] !== null ? (int) $matched['id'] : null,
                    'name' => (string) $matched['name'],
                    'slug' => $matched['slug'] ?? null,
                    'price' => $price,
                ];
                $names[] = (string) $matched['name'];
                $addonsTotal += $price;
            }
            // Addon que não casou com nenhum adicional válido do pacote é ignorado.
        }

        $subtotal = round((float) $pack->price + $addonsTotal, 2);

        return new self(
            pack: $pack,
            addonsSnapshot: $snapshot,
            addonNames: $names,
            addonsTotal: round($addonsTotal, 2),
            subtotal: $subtotal
        );
    }

    /**
     * Obtém os adicionais válidos vinculados ao pacote e ao mesmo tenant do pacote,
     * imune ao scope global do usuário logado (ex.: visitante ou cliente de outra produtora).
     *
     * @return Collection<int, array{id: ?int, name: string, slug: ?string, price: float}>
     */
    public static function availableAddons(Pack $pack): Collection
    {
        // Carrega adicionais relacionais ignorando o global scope de tenant,
        // mas filtrando estritamente pelo tenant do pacote.
        $relational = $pack->addons()
            ->withoutGlobalScope('tenant')
            ->where('addons.tenant_id', $pack->tenant_id)
            ->get();

        if ($relational->isNotEmpty()) {
            return $relational->map(function (Addon $addon) {
                $price = ($addon->pivot && $addon->pivot->custom_price !== null)
                    ? (float) $addon->pivot->custom_price
                    : (float) $addon->default_price;

                return [
                    'id' => $addon->id,
                    'name' => __($addon->name),
                    'slug' => $addon->slug,
                    'price' => round($price, 2),
                ];
            });
        }

        // Fallback para JSON legado do próprio pacote (legacy_addons)
        $legacy = $pack->legacy_addons ?? [];

        return collect($legacy)->map(function ($addon) {
            return [
                'id' => isset($addon['id']) && $addon['id'] !== null ? (int) $addon['id'] : null,
                'name' => $addon['name'] ?? '',
                'slug' => $addon['slug'] ?? null,
                'price' => round((float) ($addon['price'] ?? 0), 2),
            ];
        });
    }
}
