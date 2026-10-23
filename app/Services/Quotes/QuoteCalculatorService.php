<?php

namespace App\Services\Quotes;

use App\Models\Setting;
use App\Models\Tenant;

class QuoteCalculatorService
{
    /**
     * Retorna os valores padrão de configuração para um tenant.
     */
    public function getDefaults(?Tenant $tenant = null): array
    {
        $filmmakerRate = (float) Setting::get('quote_rate_filmmaker', 12.04);
        $editorRate = (float) Setting::get('quote_rate_editor', 14.85);
        $droneDefault = (float) Setting::get('quote_drone_price', 150.00);
        $vatRate = (float) Setting::get('quote_vat_rate', 21.00);
        $editHoursPerVideo = (float) Setting::get('quote_edit_hours_per_video', 3.00);
        $validityDays = (int) Setting::get('quote_validity_days', 10);
        $defaultMargin = (float) Setting::get('quote_default_margin_pct', 0.00);

        return [
            'videos' => 4,
            'pre' => 2.0,
            'gravacao' => 4.0,
            'edicao' => 12.0, // 4 vídeos * 3h
            'motion' => 0.0,
            'filmmakers' => 2,
            'fm_n' => 4,
            'valor_hora' => $filmmakerRate,
            'valor_hora_editor' => $editorRate,
            'horas_edicao_por_video' => $editHoursPerVideo,
            'drone' => false,
            'drone_valor' => $droneDefault,
            'iva' => $vatRate,
            'margem' => $defaultMargin,
            'adicional' => 0.0,
            'valor_unit' => 0.0,
            'cambios' => 3,
            'prazo' => 15,
            'validade_dias' => $validityDays,
            'extras' => [],
            'language' => 'es',
            'fonte_hora' => 'Valores padrão por hora: filmmaker 12,04 € (operador de câmara) e editor 14,85 € (montador de vídeo), mínimos do convênio audiovisual 2026 (BOE-A-2025-18060). Base 1.826 h/ano.',
        ];
    }

    /**
     * Sanitiza e calcula os valores completos do orçamento.
     */
    public function calculate(array $inputs): array
    {
        $videos = max(1, (int) ($inputs['videos'] ?? 1));
        $pre = max(0, (float) ($inputs['pre'] ?? 0));
        $gravacao = max(0, (float) ($inputs['gravacao'] ?? 0));
        $edicao = max(0, (float) ($inputs['edicao'] ?? 0));
        $motion = max(0, (float) ($inputs['motion'] ?? 0));

        $filmmakers = (int) ($inputs['filmmakers'] ?? 1);
        if ($filmmakers >= 4 && ! empty($inputs['fm_n'])) {
            $filmmakers = max(4, (int) $inputs['fm_n']);
        }
        $filmmakers = max(1, $filmmakers);

        $valorHora = max(0, (float) ($inputs['valor_hora'] ?? 12.04));
        $valorHoraEditor = max(0, (float) ($inputs['valor_hora_editor'] ?? 14.85));

        $hasDrone = ! empty($inputs['drone']);
        $droneValor = $hasDrone ? max(0, (float) ($inputs['drone_valor'] ?? 0)) : 0;

        $ivaPct = max(0, (float) ($inputs['iva'] ?? 21)) / 100;
        $margemPct = max(0, (float) ($inputs['margem'] ?? 0)) / 100;
        $valorUnit = max(0, (float) ($inputs['valor_unit'] ?? 0));
        $adicional = (float) ($inputs['adicional'] ?? 0);
        $cambios = max(1, (int) ($inputs['cambios'] ?? 3));
        $prazo = max(1, (int) ($inputs['prazo'] ?? 15));

        // Sanitização de extras
        $extras = [];
        $rawExtras = $inputs['extras'] ?? [];
        if (is_array($rawExtras)) {
            foreach ($rawExtras as $ex) {
                if (! is_array($ex)) continue;
                $nome = trim((string) ($ex['nome'] ?? ''));
                $valor = max(0, (float) ($ex['valor'] ?? 0));
                if ($nome !== '' || $valor > 0) {
                    $extras[] = ['nome' => $nome, 'valor' => $valor];
                }
            }
        }

        // Matemática idêntica ao artefato com arredondamento preciso a 2 casas
        $hGrav = $gravacao * $filmmakers;
        $horas = $pre + $hGrav + $edicao + $motion;
        $hFm = $pre + $hGrav;
        $hEd = $edicao + $motion;
        $custoFm = round($hFm * $valorHora, 2);
        $custoEd = round($hEd * $valorHoraEditor, 2);
        $trabalho = round($custoFm + $custoEd, 2);

        $drone = $hasDrone ? $droneValor : 0.0;
        $extrasSoma = array_reduce($extras, fn ($acc, $e) => $acc + $e['valor'], 0.0);
        $videosValor = round($videos * $valorUnit, 2);

        $precoBase = round($trabalho + $videosValor + $drone + $extrasSoma, 2);
        $margem = round($precoBase * $margemPct, 2);
        $preco = round($precoBase + $margem + $adicional, 2);
        $iva = round($preco * $ivaPct, 2);
        $total = round($preco + $iva, 2);

        $sinal = round($total * 0.50, 2);
        $naEntrega = round($total - $sinal, 2);
        $porVideo = $videos > 0 ? round($preco / $videos, 2) : 0.0;

        return [
            'inputs_sanitized' => [
                'videos' => $videos,
                'pre' => $pre,
                'gravacao' => $gravacao,
                'edicao' => $edicao,
                'motion' => $motion,
                'filmmakers' => $filmmakers,
                'valor_hora' => $valorHora,
                'valor_hora_editor' => $valorHoraEditor,
                'drone' => $hasDrone,
                'drone_valor' => $droneValor,
                'iva' => $ivaPct * 100,
                'margem' => $margemPct * 100,
                'valor_unit' => $valorUnit,
                'adicional' => $adicional,
                'cambios' => $cambios,
                'prazo' => $prazo,
                'extras' => $extras,
            ],
            'horas' => $horas,
            'h_grav' => $hGrav,
            'h_fm' => $hFm,
            'h_ed' => $hEd,
            'custo_fm' => $custoFm,
            'custo_ed' => $custoEd,
            'trabalho' => $trabalho,
            'videos_valor' => $videosValor,
            'drone' => $drone,
            'extras_total' => $extrasSoma,
            'preco_base' => $precoBase,
            'margem' => $margem,
            'adicional' => $adicional,
            'preco' => $preco,
            'iva' => $iva,
            'total_com_iva' => $total,
            'por_video' => $porVideo,
            'sinal' => $sinal,
            'na_entrega' => $naEntrega,
        ];
    }

    /**
     * Gera lista de entregáveis para a proposta do cliente (em espanhol ou português).
     */
    public function getDeliverables(array $inputs, array $calc, string $language = 'es', ?string $projectTitle = null): array
    {
        $isEs = $language !== 'pt_BR';
        $n = (int) ($inputs['videos'] ?? 1);
        $deliverables = [];

        if ($isEs) {
            $videoTxt = "{$n} " . ($n === 1 ? 'vídeo' : 'vídeos');
            if (! empty($projectTitle)) {
                $videoTxt .= " · {$projectTitle}";
            }
            $deliverables[] = $videoTxt;

            if (($inputs['edicao'] ?? 0) > 0) {
                $deliverables[] = 'Edición y postproducción';
            }
            if (($inputs['motion'] ?? 0) > 0) {
                $deliverables[] = 'Motion graphics, VFX o IA';
            }
            if (! empty($inputs['drone'])) {
                $deliverables[] = 'Imágenes aéreas con dron';
            }
            $extras = $inputs['extras'] ?? [];
            foreach ($extras as $idx => $ex) {
                if (! empty($ex['nome'])) {
                    $deliverables[] = $ex['nome'];
                } elseif (! empty($ex['valor'])) {
                    $deliverables[] = 'Servicio extra ' . ($idx + 1);
                }
            }
            $cambios = (int) ($inputs['cambios'] ?? 3);
            $deliverables[] = "{$cambios} " . ($cambios === 1 ? 'cambio incluido' : 'cambios incluidos') . ' en cada vídeo';
        } else {
            $videoTxt = "{$n} " . ($n === 1 ? 'vídeo' : 'vídeos');
            if (! empty($projectTitle)) {
                $videoTxt .= " · {$projectTitle}";
            }
            $deliverables[] = $videoTxt;

            if (($inputs['edicao'] ?? 0) > 0) {
                $deliverables[] = 'Edição e pós-produção';
            }
            if (($inputs['motion'] ?? 0) > 0) {
                $deliverables[] = 'Motion graphics, VFX ou IA';
            }
            if (! empty($inputs['drone'])) {
                $deliverables[] = 'Imagens aéreas com drone';
            }
            $extras = $inputs['extras'] ?? [];
            foreach ($extras as $idx => $ex) {
                if (! empty($ex['nome'])) {
                    $deliverables[] = $ex['nome'];
                } elseif (! empty($ex['valor'])) {
                    $deliverables[] = 'Serviço extra ' . ($idx + 1);
                }
            }
            $cambios = (int) ($inputs['cambios'] ?? 3);
            $deliverables[] = "{$cambios} " . ($cambios === 1 ? 'alteração incluída' : 'alterações incluídas') . ' em cada vídeo';
        }

        return $deliverables;
    }
}
