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
                // Publicação liberada pelo orçamento (vai para a compra); null = sem limite
                'redes' => isset($inputs['redes']) && $inputs['redes'] !== '' ? max(1, min(8, (int) $inputs['redes'])) : null,
                'envios_mes' => isset($inputs['envios_mes']) && $inputs['envios_mes'] !== '' ? max(1, min(1000, (int) $inputs['envios_mes'])) : null,
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

    /** Plataformas de anúncio aceitas no orçamento de anúncios. */
    public const AD_PLATFORMS = ['meta', 'google', 'youtube', 'tiktok', 'linkedin', 'pinterest', 'x'];

    /**
     * Padrões do orçamento de anúncios: a NUME cobra criação dos criativos e gestão da campanha.
     * A verba é paga pelo cliente direto à plataforma e só aparece como informação.
     */
    public function getAdsDefaults(?Tenant $tenant = null): array
    {
        return [
            'criativos' => 4,
            'valor_criativo' => (float) Setting::get('quote_ads_creative_price', 120.00),
            'gestao_mensal' => (float) Setting::get('quote_ads_management_fee', 250.00),
            'meses' => 1,
            'verba_mensal' => 0.0,
            'plataformas' => ['meta'],
            'iva' => (float) Setting::get('quote_vat_rate', 21.00),
            'adicional' => 0.0,
            'prazo' => 10,
            'validade_dias' => (int) Setting::get('quote_validity_days', 10),
        ];
    }

    /**
     * Cálculo do orçamento de anúncios com as mesmas chaves do calculate(), para gravar e
     * cobrar pelo mesmo fluxo. Pagamento integral na aprovação (sem sinal/saldo).
     */
    public function calculateAds(array $inputs): array
    {
        $criativos = max(0, min(100, (int) ($inputs['criativos'] ?? 0)));
        $valorCriativo = max(0, (float) ($inputs['valor_criativo'] ?? 0));
        $gestaoMensal = max(0, (float) ($inputs['gestao_mensal'] ?? 0));
        $meses = max(1, min(24, (int) ($inputs['meses'] ?? 1)));
        $verbaMensal = max(0, (float) ($inputs['verba_mensal'] ?? 0));
        $ivaPct = max(0, (float) ($inputs['iva'] ?? 21)) / 100;
        $adicional = (float) ($inputs['adicional'] ?? 0);
        $prazo = max(1, (int) ($inputs['prazo'] ?? 10));
        $plataformas = array_values(array_intersect(self::AD_PLATFORMS, (array) ($inputs['plataformas'] ?? [])));

        $criacao = round($criativos * $valorCriativo, 2);
        $gestao = round($gestaoMensal * $meses, 2);
        $preco = round($criacao + $gestao + $adicional, 2);
        $iva = round($preco * $ivaPct, 2);
        $total = round($preco + $iva, 2);

        return [
            'inputs_sanitized' => [
                'criativos' => $criativos,
                'valor_criativo' => $valorCriativo,
                'gestao_mensal' => $gestaoMensal,
                'meses' => $meses,
                'verba_mensal' => $verbaMensal,
                'plataformas' => $plataformas,
                'iva' => $ivaPct * 100,
                'adicional' => $adicional,
                'prazo' => $prazo,
            ],
            'criacao' => $criacao,
            'gestao' => $gestao,
            'margem' => 0.0,
            'adicional' => $adicional,
            'preco' => $preco,
            'iva' => $iva,
            'total_com_iva' => $total,
            'sinal' => $total,
            'na_entrega' => 0.0,
            // Informativo: não entra no total, o cliente paga direto à plataforma.
            'verba_total_info' => round($verbaMensal * $meses, 2),
        ];
    }

    /**
     * Entregáveis do orçamento de anúncios (a verba aparece como fora do orçamento).
     */
    public function getAdsDeliverables(array $inputs, string $language = 'es'): array
    {
        $isEs = $language !== 'pt_BR';
        $criativos = (int) ($inputs['criativos'] ?? 0);
        $meses = (int) ($inputs['meses'] ?? 1);
        $plataformas = implode(', ', array_map('ucfirst', (array) ($inputs['plataformas'] ?? [])));
        $verba = number_format((float) ($inputs['verba_mensal'] ?? 0), 2, ',', '.');

        $items = $isEs
            ? [
                "{$criativos} ".($criativos === 1 ? 'creatividad publicitaria' : 'creatividades publicitarias').' (vídeo o imagen)',
                "Configuración y gestión de la campaña durante {$meses} ".($meses === 1 ? 'mes' : 'meses'),
            ]
            : [
                "{$criativos} ".($criativos === 1 ? 'criativo de anúncio' : 'criativos de anúncio').' (vídeo ou imagem)',
                "Configuração e gestão da campanha por {$meses} ".($meses === 1 ? 'mês' : 'meses'),
            ];

        if ($plataformas !== '') {
            $items[] = ($isEs ? 'Plataformas: ' : 'Plataformas: ').$plataformas;
        }

        if ((float) ($inputs['verba_mensal'] ?? 0) > 0) {
            $items[] = $isEs
                ? "Inversión publicitaria de {$verba} €/mes: la pagas tú directamente a la plataforma (no incluida en este presupuesto)"
                : "Verba de anúncio de {$verba} €/mês: paga por você direto à plataforma (não incluída neste orçamento)";
        }

        return $items;
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
