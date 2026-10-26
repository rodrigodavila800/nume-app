<?php

namespace App\Services\VideoTypes;

use App\Models\VideoType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de tipos de vídeo por produtora. Produtora sem nenhum tipo recebe o padrão
 * "Vendas (30 s)", que o admin pode editar ou desativar.
 */
class VideoTypeCatalog
{
    /**
     * @return Collection<int, VideoType>
     */
    public function active(int $tenantId): Collection
    {
        $this->ensureDefaults($tenantId);

        return VideoType::forTenant($tenantId)->active()->ordered()->get();
    }

    /**
     * @return Collection<int, VideoType>
     */
    public function all(int $tenantId): Collection
    {
        $this->ensureDefaults($tenantId);

        return VideoType::forTenant($tenantId)->ordered()->get();
    }

    public function ensureDefaults(int $tenantId): void
    {
        if (VideoType::forTenant($tenantId)->exists()) {
            return;
        }

        VideoType::create([
            'tenant_id' => $tenantId,
            'name' => 'Vendas (30 s)',
            'slug' => 'vendas-30s',
            'description' => 'Vídeo curto de venda: gancho, dor, virada, prova, oferta e uma única chamada para ação.',
            'duration_seconds' => 30,
            'blocks' => VideoType::salesBlocks(),
            'sofi_guidance' => 'Mostre o produto resolvendo a dor; nada de explicação longa. Um único CTA no final.',
            'sort_order' => 0,
        ]);
    }

    /**
     * Normaliza e valida os blocos vindos do formulário do admin.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return list<array{key: string, name: string, start: int, end: int, purpose: string}>
     *
     * @throws ValidationException
     */
    public function normalizeBlocks(array $blocks, int $durationSeconds): array
    {
        $normalized = [];
        $previousEnd = 0;
        $usedKeys = [];

        foreach (array_values($blocks) as $index => $block) {
            $name = trim((string) ($block['name'] ?? ''));
            if ($name === '' && trim((string) ($block['purpose'] ?? '')) === '') {
                continue; // linha vazia do formulário
            }

            $start = (int) ($block['start'] ?? -1);
            $end = (int) ($block['end'] ?? -1);
            $field = "blocks.{$index}";

            if ($name === '' || mb_strlen($name) > 60) {
                throw ValidationException::withMessages([$field.'.name' => __('Cada bloco precisa de um nome com até 60 caracteres.')]);
            }
            if ($start < $previousEnd || $end <= $start || $end > $durationSeconds) {
                throw ValidationException::withMessages([$field.'.end' => __('Os tempos dos blocos devem seguir em ordem, sem sobrepor, dentro da duração do vídeo.')]);
            }

            $key = Str::slug((string) ($block['key'] ?? '') ?: $name, '_') ?: 'bloco';
            $base = $key;
            for ($n = 2; in_array($key, $usedKeys, true); $n++) {
                $key = $base.'_'.$n;
            }
            $usedKeys[] = $key;

            $normalized[] = [
                'key' => mb_substr($key, 0, 40),
                'name' => $name,
                'start' => $start,
                'end' => $end,
                'purpose' => mb_substr(trim((string) ($block['purpose'] ?? '')), 0, 300),
            ];
            $previousEnd = $end;
        }

        if ($normalized === [] || count($normalized) > VideoType::MAX_BLOCKS) {
            throw ValidationException::withMessages(['blocks' => __('O tipo de vídeo precisa ter de 1 a :max blocos.', ['max' => VideoType::MAX_BLOCKS])]);
        }

        return $normalized;
    }

    public function uniqueSlug(int $tenantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'tipo';
        $slug = $base;

        for ($n = 2; VideoType::forTenant($tenantId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return mb_substr($slug, 0, 80);
    }
}
