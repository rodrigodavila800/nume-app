<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de vídeo da produtora (cadastrado pelo admin). Os blocos definem a estrutura do roteiro:
 * lista de ['key', 'name', 'start', 'end', 'purpose'], com tempos em segundos.
 */
class VideoType extends Model
{
    use BelongsToTenant;

    public const MAX_BLOCKS = 12;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'duration_seconds',
        'edit_hours_per_video',
        'blocks',
        'sofi_guidance',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_active' => 'boolean',
            'duration_seconds' => 'integer',
            'edit_hours_per_video' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Estrutura padrão de venda em 30 s (definida pelo analista da NUME).
     *
     * @return list<array{key: string, name: string, start: int, end: int, purpose: string}>
     */
    public static function salesBlocks(): array
    {
        return [
            ['key' => 'hook', 'name' => 'Gancho', 'start' => 0, 'end' => 3, 'purpose' => 'Parar o scroll. Visual e frase juntos.'],
            ['key' => 'problem', 'name' => 'Problema / dor', 'start' => 3, 'end' => 8, 'purpose' => 'Fazer o público se reconhecer.'],
            ['key' => 'turn', 'name' => 'Virada (solução)', 'start' => 8, 'end' => 15, 'purpose' => 'Apresentar o produto como a saída, mostrando, não explicando.'],
            ['key' => 'proof', 'name' => 'Prova', 'start' => 15, 'end' => 22, 'purpose' => 'Tirar a dúvida: resultado, depoimento, número, antes/depois.'],
            ['key' => 'offer', 'name' => 'Oferta', 'start' => 22, 'end' => 26, 'purpose' => 'O que a pessoa leva e por que agora.'],
            ['key' => 'cta', 'name' => 'CTA', 'start' => 26, 'end' => 30, 'purpose' => 'Uma ação só, clara e fácil.'],
        ];
    }

    /**
     * Cópia da estrutura para gravar no roteiro no momento da geração.
     */
    public function snapshot(): array
    {
        return [
            'video_type_id' => $this->id,
            'name' => $this->name,
            'duration_seconds' => $this->duration_seconds,
            'blocks' => $this->blocks,
        ];
    }
}
