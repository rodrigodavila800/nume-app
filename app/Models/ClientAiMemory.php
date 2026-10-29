<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAiMemory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'cliente_id',
        'tone_preference',
        'positive_patterns',
        'anti_patterns',
        'crm_learnings',
        'chat_learnings',
        'few_shot_exemplars',
        'last_distilled_at',
    ];

    protected function casts(): array
    {
        return [
            'positive_patterns' => 'array',
            'anti_patterns' => 'array',
            'crm_learnings' => 'array',
            'chat_learnings' => 'array',
            'few_shot_exemplars' => 'array',
            'last_distilled_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addAntiPattern(string $item): void
    {
        $patterns = $this->anti_patterns ?? [];
        $trimmed = trim($item);
        if ($trimmed !== '' && ! in_array($trimmed, $patterns, true)) {
            $patterns[] = $trimmed;
            if (count($patterns) > 20) {
                array_shift($patterns);
            }
            $this->anti_patterns = array_values($patterns);
        }
    }

    public function addPositivePattern(string $item): void
    {
        $patterns = $this->positive_patterns ?? [];
        $trimmed = trim($item);
        if ($trimmed !== '' && ! in_array($trimmed, $patterns, true)) {
            $patterns[] = $trimmed;
            if (count($patterns) > 20) {
                array_shift($patterns);
            }
            $this->positive_patterns = array_values($patterns);
        }
    }

    public function addCrmLearning(string $item): void
    {
        $learnings = $this->crm_learnings ?? [];
        $trimmed = trim($item);
        if ($trimmed !== '' && ! in_array($trimmed, $learnings, true)) {
            $learnings[] = $trimmed;
            if (count($learnings) > 20) {
                array_shift($learnings);
            }
            $this->crm_learnings = array_values($learnings);
        }
    }

    public function addChatLearning(string $item): void
    {
        $learnings = $this->chat_learnings ?? [];
        $trimmed = trim($item);
        if ($trimmed !== '' && ! in_array($trimmed, $learnings, true)) {
            $learnings[] = $trimmed;
            if (count($learnings) > 25) {
                array_shift($learnings);
            }
            $this->chat_learnings = array_values($learnings);
        }
    }

    public function addFewShotExemplar(array $exemplar): void
    {
        $exemplars = $this->few_shot_exemplars ?? [];
        $exemplars[] = $exemplar;
        if (count($exemplars) > 5) {
            array_shift($exemplars);
        }
        $this->few_shot_exemplars = array_values($exemplars);
    }

    /**
     * Formata as memórias aprendidas para injeção contextual em agentes e prompts da Sofi.
     */
    public function formatForPrompt(): string
    {
        $parts = [];

        if (! empty($this->tone_preference)) {
            $parts[] = "TOM DE VOZ APRENDIDO: {$this->tone_preference}";
        }

        if (! empty($this->anti_patterns)) {
            $items = implode('; ', array_slice($this->anti_patterns, -5));
            $parts[] = "ANTI-PATTERNS (EVITAR ABSOLUTAMENTE - já reprovado anteriormente): {$items}";
        }

        if (! empty($this->positive_patterns)) {
            $items = implode('; ', array_slice($this->positive_patterns, -5));
            $parts[] = "PADRÕES DE ALTA ACEITAÇÃO (PREFERIDOS): {$items}";
        }

        if (! empty($this->crm_learnings)) {
            $items = implode('; ', array_slice($this->crm_learnings, -4));
            $parts[] = "HISTÓRICO COMERCIAL/CRM (OBJEÇÕES E CONTEXTO): {$items}";
        }

        if (! empty($this->chat_learnings)) {
            $items = implode('; ', array_slice($this->chat_learnings, -4));
            $parts[] = "DÚVIDAS & TÓPICOS FREQUENTES (ATENDIMENTO/WHATSAPP): {$items}";
        }

        return implode("\n", $parts);
    }
}
