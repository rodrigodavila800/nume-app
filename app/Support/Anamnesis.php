<?php

namespace App\Support;

use App\Enums\PlataformaAlvo;
use Illuminate\Validation\Rule;

/**
 * Perguntas da anamnese do cliente (base do plano de marketing). As respostas são texto do
 * cliente: no prompt entram como dado não confiável, nunca como instrução.
 */
class Anamnesis
{
    public const VERSION = 1;

    public const OBJECTIVES = ['sell_more', 'authority', 'launch', 'recruit', 'community'];

    public const TONES = ['close', 'expert', 'inspiring', 'fun', 'premium'];

    public const FREQUENCIES = ['1_week', '2_week', '3_week', 'daily'];

    public static function objectiveLabels(): array
    {
        return [
            'sell_more' => __('Vender mais'),
            'authority' => __('Ganhar autoridade no mercado'),
            'launch' => __('Lançar um produto ou serviço'),
            'recruit' => __('Atrair talentos'),
            'community' => __('Criar comunidade e fidelizar'),
        ];
    }

    public static function toneLabels(): array
    {
        return [
            'close' => __('Próximo e conversado'),
            'expert' => __('Especialista e direto'),
            'inspiring' => __('Inspirador'),
            'fun' => __('Divertido'),
            'premium' => __('Sofisticado e premium'),
        ];
    }

    public static function frequencyLabels(): array
    {
        return [
            '1_week' => __('1 vídeo por semana'),
            '2_week' => __('2 vídeos por semana'),
            '3_week' => __('3 vídeos por semana'),
            'daily' => __('Todos os dias'),
        ];
    }

    public static function rules(): array
    {
        return [
            'objective' => ['required', Rule::in(self::OBJECTIVES)],
            'main_offer' => ['required', 'string', 'max:300'],
            'average_ticket' => ['nullable', 'string', 'max:60'],
            'ideal_customer' => ['required', 'string', 'max:1000'],
            'competitors' => ['nullable', 'string', 'max:500'],
            'tone' => ['required', Rule::in(self::TONES)],
            'priority_networks' => ['required', 'array', 'min:1'],
            'priority_networks.*' => ['string', Rule::in(PlataformaAlvo::values())],
            'posting_frequency' => ['required', Rule::in(self::FREQUENCIES)],
            'seasonality' => ['nullable', 'string', 'max:500'],
            'restrictions' => ['nullable', 'string', 'max:500'],
            'wants_ads' => ['boolean'],
            'ads_monthly_budget' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Respostas validadas no formato gravado em clientes.anamnesis.
     */
    public static function normalize(array $validated): array
    {
        $answers = ['version' => self::VERSION];
        foreach (array_keys(self::rules()) as $field) {
            if (! str_contains($field, '.')) {
                $answers[$field] = $validated[$field] ?? null;
            }
        }

        $answers['priority_networks'] = array_values(array_unique($answers['priority_networks'] ?? []));
        $answers['wants_ads'] = (bool) $answers['wants_ads'];
        if (! $answers['wants_ads']) {
            $answers['ads_monthly_budget'] = null;
        }

        return $answers;
    }

    /**
     * Vídeos por mês sugeridos pela frequência escolhida.
     */
    public static function monthlyVideos(?array $answers): ?int
    {
        return match ($answers['posting_frequency'] ?? null) {
            '1_week' => 4,
            '2_week' => 8,
            '3_week' => 12,
            'daily' => 30,
            default => null,
        };
    }

    /**
     * Respostas em texto legível para o prompt da Sofi.
     */
    public static function promptBlock(?array $answers): string
    {
        if (! $answers) {
            return 'Anamnese não preenchida.';
        }

        $networks = implode(', ', $answers['priority_networks'] ?? []);

        return implode("\n", array_filter([
            '- OBJETIVO: '.(self::objectiveLabels()[$answers['objective'] ?? ''] ?? ''),
            '- OFERTA PRINCIPAL: '.($answers['main_offer'] ?? ''),
            ! empty($answers['average_ticket']) ? '- TICKET MÉDIO: '.$answers['average_ticket'] : null,
            '- CLIENTE IDEAL: '.($answers['ideal_customer'] ?? ''),
            ! empty($answers['competitors']) ? '- CONCORRENTES: '.$answers['competitors'] : null,
            '- TOM: '.(self::toneLabels()[$answers['tone'] ?? ''] ?? ''),
            '- REDES PRIORITÁRIAS: '.$networks,
            '- FREQUÊNCIA: '.(self::frequencyLabels()[$answers['posting_frequency'] ?? ''] ?? ''),
            ! empty($answers['seasonality']) ? '- SAZONALIDADE: '.$answers['seasonality'] : null,
            ! empty($answers['restrictions']) ? '- RESTRIÇÕES: '.$answers['restrictions'] : null,
            '- QUER ANÚNCIOS: '.(! empty($answers['wants_ads']) ? 'sim'.(! empty($answers['ads_monthly_budget']) ? ' (verba mensal: '.$answers['ads_monthly_budget'].')' : '') : 'não'),
        ]));
    }
}
