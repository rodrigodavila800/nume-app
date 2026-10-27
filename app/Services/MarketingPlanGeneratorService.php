<?php

namespace App\Services;

use App\Ai\Agents\SofiMarketingPlanAgent;
use App\Ai\SofiProvider;
use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\PackPurchase;
use App\Services\SocialInsights\SocialInsightsReader;
use App\Services\VideoTypes\VideoTypeCatalog;
use App\Support\Anamnesis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MarketingPlanGeneratorService
{
    /**
     * Identifica se a sessão atual ou o perfil do cliente está configurado para espanhol.
     */
    public static function isSpanishLocale(?Cliente $cliente = null): bool
    {
        $currentLocale = session('locale', app()->getLocale() ?: 'pt_BR');
        if (str_starts_with($currentLocale, 'es')) {
            return true;
        }

        if ($cliente) {
            return self::isSpanishProfile($cliente);
        }

        return false;
    }

    /**
     * Análise heurística semântica para detectar se o DNA da marca do cliente está em espanhol.
     */
    public static function isSpanishProfile(Cliente $cliente): bool
    {
        $text = mb_strtolower(implode(' ', [
            $cliente->brand_niche ?? '',
            $cliente->target_audience ?? '',
            $cliente->key_differentials ?? '',
            $cliente->company_name ?? '',
        ]));

        $indicators = [
            ' de ', ' en ', ' para ', ' con ', ' por ', ' que ', ' los ', ' las ', ' del ', ' y ',
            'líderes', 'personas', 'liderazgo', 'quieren', 'españa', 'zaragoza', 'servicios', 'atención',
            'inconformistas', 'evolucionar', 'innovación', 'calidad',
        ];

        $matches = 0;
        foreach ($indicators as $ind) {
            if (str_contains($text, $ind)) {
                $matches++;
            }
        }

        return $matches >= 2;
    }

    /**
     * Gera e persiste o Plano de Marketing & Grade Editorial do Cliente.
     */
    /**
     * Gera e persiste o plano do mês como RASCUNHO: só vale depois que o cliente aprova.
     */
    public function generate(Cliente $cliente, int $videoQuantity = 4, ?PackPurchase $packPurchase = null, ?string $monthYear = null): MarketingPlan
    {
        $targetMonthYear = $monthYear ?: now()->format('Y-m');
        $isEs = self::isSpanishLocale($cliente);

        // 1. Cria ou reutiliza plano existente para o mês
        $plan = MarketingPlan::forTenant($cliente->tenant_id)->firstOrCreate(
            [
                'tenant_id' => $cliente->tenant_id,
                'cliente_id' => $cliente->id,
                'month_year' => $targetMonthYear,
            ],
            [
                'pack_purchase_id' => $packPurchase?->id,
                'title' => $isEs ? 'Plan Editorial Estratégico de Vídeos' : 'Plano Editorial Estratégico de Vídeos',
                'strategic_goal' => $this->buildStrategicGoal($cliente, $isEs),
                'status' => MarketingPlan::STATUS_DRAFT,
                'anamnesis_snapshot' => $cliente->anamnesis,
                'metrics_summary' => [
                    'target_videos' => $videoQuantity,
                    'estimated_retention' => '92-96%',
                    'primary_funnel' => 'Top to Bottom Conversion',
                ],
            ]
        );

        // Se o plano já possui itens gerados, retorna
        if ($plan->items()->count() >= $videoQuantity) {
            return $plan;
        }

        // 2. Sofi (Laravel AI SDK) com DNA + anamnese + redes; sem IA, motor heurístico
        $built = $this->buildItems($cliente, $this->weeksFor($videoQuantity), $isEs);
        if ($built['strategic_goal']) {
            $plan->update(['strategic_goal' => $built['strategic_goal']]);
        }

        // 3. Persiste cada item da grade
        foreach ($built['items'] as $data) {
            $plan->items()->create($data);
        }

        return $plan->load('items');
    }

    /**
     * Quantidade de vídeos do plano: frequência da anamnese, senão a cota da última compra, senão 4.
     */
    public static function videoQuantityFor(Cliente $cliente, ?PackPurchase $latestPurchase = null): int
    {
        $quantity = Anamnesis::monthlyVideos($cliente->anamnesis) ?? ($latestPurchase?->video_quantity ?: 4);

        return max(1, min(30, (int) $quantity));
    }

    /**
     * Recalcula a grade editorial preservando itens já convertidos em produção.
     * Retorna a quantidade de novos itens gerados.
     */
    public function regeneratePlan(MarketingPlan $plan, Cliente $cliente, int $targetVideoQty, ?string $adjustmentRequest = null): int
    {
        $isEs = self::isSpanishLocale($cliente);

        // 1. Identifica itens que JÁ foram para a produção
        $preservedItems = $plan->items()->whereNotNull('production_id')->get();
        $preservedCount = $preservedItems->count();

        // Se todos os vídeos já viraram produção, não há vagas para recalcular
        if ($preservedCount >= $targetVideoQty) {
            return 0;
        }

        // 2. Remove estritamente os itens que NÃO foram transformados em produção
        $plan->items()->whereNull('production_id')->delete();

        // 3. Identifica as semanas que já estão ocupadas por itens em produção
        $occupiedWeeks = $preservedItems->pluck('week_number')->toArray();

        // Quantidade de novos itens necessários
        $neededQty = $targetVideoQty - $preservedCount;

        // Determina as semanas vagas para os novos itens (entre semanas 1, 2, 3, 4...)
        $availableWeeks = [];
        for ($w = 1; $w <= max(4, $targetVideoQty); $w++) {
            if (! in_array($w, $occupiedWeeks, true)) {
                $availableWeeks[] = $w;
            }
        }

        // Se precisar de mais semanas do que disponíveis, preenche sequencialmente
        while (count($availableWeeks) < $neededQty) {
            $availableWeeks[] = count($availableWeeks) + 1;
        }
        $availableWeeks = array_slice($availableWeeks, 0, $neededQty);

        // 4. Gera os novos itens para as semanas vagas (com o pedido de ajuste do cliente, se houver)
        $built = $this->buildItems($cliente, $availableWeeks, $isEs, $adjustmentRequest);

        // 5. Atualiza o objetivo com o DNA atual; o plano mudou, então volta a precisar de aprovação
        $plan->update([
            'title' => $isEs ? 'Plan Editorial Estratégico de Vídeos' : 'Plano Editorial Estratégico de Vídeos',
            'strategic_goal' => $built['strategic_goal'] ?: $this->buildStrategicGoal($cliente, $isEs),
            'anamnesis_snapshot' => $cliente->anamnesis,
        ]);
        $plan->backToDraft();

        foreach ($built['items'] as $data) {
            $plan->items()->create($data);
        }

        return count($built['items']);
    }

    /**
     * Itens da grade para as semanas pedidas: IA quando há provedor; o que faltar ou vier inválido
     * é completado pelo motor heurístico. Todo item recebe um tipo de vídeo ativo do tenant.
     *
     * @param  list<int>  $weeks
     * @return array{strategic_goal: ?string, items: list<array<string, mixed>>}
     */
    protected function buildItems(Cliente $cliente, array $weeks, bool $isEs, ?string $adjustmentRequest = null): array
    {
        $types = app(VideoTypeCatalog::class)->active($cliente->tenant_id);
        $defaultTypeId = $types->first()?->id;

        $heuristic = $this->generateEditorialItemsForWeeks($cliente, $weeks, $isEs);
        $ai = $this->aiPlan($cliente, $weeks, $isEs, $types, $adjustmentRequest);

        $items = [];
        foreach ($weeks as $index => $week) {
            $item = $this->normalizeAiItem($ai['items'][$index] ?? null, $week, $types) ?? $heuristic[$index];
            $item['video_type_id'] ??= $defaultTypeId;
            $items[] = $item;
        }

        $goal = is_string($ai['strategic_goal'] ?? null) ? mb_substr(trim($ai['strategic_goal']), 0, 1000) : '';

        return ['strategic_goal' => $goal !== '' ? $goal : null, 'items' => $items];
    }

    /**
     * Chamada ao SofiMarketingPlanAgent. Null sem provedor ou em qualquer falha (cai no heurístico).
     */
    protected function aiPlan(Cliente $cliente, array $weeks, bool $isEs, Collection $types, ?string $adjustmentRequest): ?array
    {
        $sofiProvider = app(SofiProvider::class);

        try {
            $options = $sofiProvider->options($cliente->tenant, $cliente->id);
            if ($options === null) {
                return null;
            }

            $response = (new SofiMarketingPlanAgent($cliente->tenant_id, $cliente->id))
                ->prompt($this->planPrompt($cliente, $weeks, $isEs, $types, $adjustmentRequest), ...$options);
            $data = $response->structured ?? [];

            return is_array($data['items'] ?? null) ? $data : null;
        } catch (\Throwable $e) {
            // Só o tipo da exceção: a mensagem pode conter URL ou credenciais.
            Log::warning('MarketingPlanGeneratorService AI SDK falhou ('.$sofiProvider->driver().'): '.get_class($e));

            return null;
        }
    }

    /**
     * Dados do cliente para o agente. Tudo entre <<< >>> é conteúdo do cliente ou de terceiros.
     */
    protected function planPrompt(Cliente $cliente, array $weeks, bool $isEs, Collection $types, ?string $adjustmentRequest): string
    {
        $study = is_array($cliente->brand_study) ? $cliente->brand_study : [];
        $list = fn ($value) => is_array($value)
            ? implode(' | ', array_map(fn ($v) => is_array($v) ? implode(': ', array_filter($v, 'is_string')) : (string) $v, $value))
            : (string) $value;

        $typeLines = $types->map(fn ($type) => "- {$type->name} ({$type->duration_seconds} s): "
            .collect($type->blocks)->pluck('name')->implode(' → ')
            .($type->description ? " — {$type->description}" : ''))->implode("\n");

        return implode("\n", array_filter([
            'IDIOMA DO PLANO: '.($isEs ? 'Español de España' : 'Português do Brasil'),
            'QUANTIDADE DE VÍDEOS: '.count($weeks).' · SEMANAS DE CADA VÍDEO, NA ORDEM: '.implode(', ', $weeks),
            "TIPOS DE VÍDEO DISPONÍVEIS:\n".($typeLines ?: '- nenhum'),
            '',
            'DNA DA MARCA:',
            '<<<',
            '- EMPRESA: '.($cliente->company_name ?: $cliente->name),
            '- NICHO: '.$cliente->brand_niche,
            '- PÚBLICO: '.$cliente->target_audience,
            '- DIFERENCIAIS: '.$cliente->key_differentials,
            '- PROPOSTA DE VALOR: '.$list($study['unique_value_proposition'] ?? ''),
            '- OFERTAS: '.$list($study['flagship_offerings'] ?? []),
            '- DORES: '.$list($study['target_audience_pains'] ?? []),
            '- PILARES: '.$list($study['content_pillars'] ?? []),
            '- EVITAR: '.$list($study['forbidden_words'] ?? []),
            '>>>',
            'ANAMNESE (respostas do cliente):',
            '<<<',
            Anamnesis::promptBlock($cliente->anamnesis),
            '>>>',
            'REDES SOCIAIS CONECTADAS:',
            '<<<',
            SocialInsightsReader::promptBlock($study['social_insights'] ?? null),
            '>>>',
            $adjustmentRequest ? "PEDIDO DE AJUSTE DO CLIENTE SOBRE O PLANO ANTERIOR:\n<<<\n".mb_substr($adjustmentRequest, 0, 1000)."\n>>>" : null,
        ], fn ($line) => $line !== null));
    }

    /**
     * Item da IA validado campo a campo; null quando falta o essencial (o slot fica com o heurístico).
     */
    protected function normalizeAiItem(mixed $raw, int $week, Collection $types): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $text = fn ($value, int $max) => is_string($value) ? mb_substr(trim($value), 0, $max) : '';

        $topic = $text($raw['topic'] ?? null, 250);
        $hook = $text($raw['hook_text'] ?? null, 500);
        if ($topic === '' || $hook === '') {
            return null;
        }

        $hashtags = collect(is_array($raw['hashtags'] ?? null) ? $raw['hashtags'] : [])
            ->filter(fn ($tag) => is_string($tag))
            ->map(fn ($tag) => mb_substr(trim(ltrim(trim($tag), '#')), 0, 40))
            ->filter()->unique()->take(8)->values()->all();

        $typeName = mb_strtolower($text($raw['video_type'] ?? null, 80));
        $type = $typeName === '' ? null : $types->first(fn ($t) => mb_strtolower($t->name) === $typeName);

        return [
            'week_number' => $week,
            'funnel_stage' => in_array($raw['funnel_stage'] ?? null, ['top', 'middle', 'bottom'], true) ? $raw['funnel_stage'] : 'top',
            'scheduled_day_time' => $text($raw['scheduled_day_time'] ?? null, 100) ?: null,
            'topic' => $topic,
            'hook_text' => $hook,
            'seo_caption' => $text($raw['seo_caption'] ?? null, 2000) ?: null,
            'hashtags' => $hashtags,
            'format' => in_array($raw['format'] ?? null, ['vertical', 'horizontal'], true) ? $raw['format'] : 'vertical',
            'status' => 'pending',
            'video_type_id' => $type?->id,
        ];
    }

    /**
     * Semanas do mês para N vídeos (1 a 4), distribuídas por igual.
     *
     * @return list<int>
     */
    protected function weeksFor(int $qty): array
    {
        $weeks = [];
        for ($i = 1; $i <= $qty; $i++) {
            $weeks[] = min(4, (int) ceil($i / max(1, ($qty / 4))));
        }

        return $weeks;
    }

    /**
     * Define o objetivo do mês com base no nicho e Dossiê Estratégico do cliente.
     */
    protected function buildStrategicGoal(Cliente $cliente, bool $isEs): string
    {
        $company = $cliente->company_name ?: $cliente->name;
        $niche = $cliente->brand_niche ?: ($isEs ? 'servicios corporativos' : 'empresas e serviços');
        $study = $cliente->brand_study;

        if (! empty($study['unique_value_proposition'])) {
            return $isEs
                ? "{$company} — Posicionamiento de Referencia: {$study['unique_value_proposition']}"
                : "{$company} — Posicionamento de Referência: {$study['unique_value_proposition']}";
        }

        if ($isEs) {
            return "Consolidar a {$company} como referente de autoridad en el sector de {$niche}, quebrando mitos del mercado con vídeos de alta retención e impulsando la conversión directa de nuevos clientes.";
        }

        return "Consolidar a marca {$company} como autoridade máxima no segmento de {$niche}, atraindo audiência qualificada com ganchos de interrupção e direcionando espectadores para fechamento de vendas.";
    }

    /**
     * Gera os dados de cada postagem para semanas específicas aproveitando o Dossiê Sofi IA.
     */
    public function generateEditorialItemsForWeeks(Cliente $cliente, array $weeks, bool $isEs): array
    {
        $company = $cliente->company_name ?: ($cliente->name ?: 'NUME Client');
        $niche = $cliente->brand_niche ?: ($isEs ? 'comercio y servicios' : 'empresas e serviços');
        $audience = $cliente->target_audience ?: ($isEs ? 'clientes potenciales' : 'clientes qualificados');
        $differentials = $cliente->key_differentials ?: ($isEs ? 'alta calidad y rapidez' : 'excelência e agilidade');

        $study = $cliente->brand_study;
        $pains = is_array($study['target_audience_pains'] ?? null) ? $study['target_audience_pains'] : [];
        $offerings = is_array($study['flagship_offerings'] ?? null) ? $study['flagship_offerings'] : [];
        $suggestedHooks = is_array($study['suggested_hooks'] ?? null) ? $study['suggested_hooks'] : [];

        $funnelSequence = [
            ['stage' => 'top', 'type' => 'Atração & Quebra de Padrão', 'day' => $isEs ? 'Martes, 19:00' : 'Terça-feira, 19:00'],
            ['stage' => 'middle', 'type' => 'Autoridade & Bastidores', 'day' => $isEs ? 'Jueves, 12:30' : 'Quinta-feira, 12:30'],
            ['stage' => 'middle', 'type' => 'Prova Social & Transformação', 'day' => $isEs ? 'Sábado, 11:00' : 'Sábado, 11:00'],
            ['stage' => 'bottom', 'type' => 'Oferta Direta & Fechamento', 'day' => $isEs ? 'Lunes, 18:30' : 'Segunda-feira, 18:30'],
        ];

        $items = [];
        foreach ($weeks as $index => $week) {
            $seq = $funnelSequence[$index % 4];

            if ($seq['stage'] === 'top') {
                $primaryPain = $pains[0] ?? null;
                $topic = $primaryPain
                    ? ($isEs ? "El error en {$niche}: ".mb_substr($primaryPain, 0, 48) : "O erro em {$niche}: ".mb_substr($primaryPain, 0, 48))
                    : ($isEs ? "El mayor error que comete la mayoría en {$niche}" : "O maior erro que a maioria comete em {$niche}");

                $hook = $suggestedHooks[0] ?? ($isEs
                    ? "⚠️ Si todavía estás intentando captar {$audience} sin este método, estás quemando presupuesto todos los días."
                    : "⚠️ Se você ainda tenta atrair {$audience} sem este método, você está jogando orçamento fora todos os dias.");

                $caption = $isEs
                    ? "Muchos creen que en {$niche} basta con publicar fotos estáticas. En {$company} comprobamos que los primeros 3 segundos deciden tu venta. Conoce cómo transformamos la percepción de marca. 👇 #NUME #VídeoMarketing"
                    : "Muitos acham que basta publicar conteúdo aleatório. Na {$company}, provamos que os primeiros 3 segundos decidem quem compra. Veja como mudar isso hoje. 👇 #NUME #VideoMarketing";

            } elseif ($seq['stage'] === 'middle' && $index % 2 === 0) {
                $mainOffering = $offerings[0] ?? null;
                $topic = $mainOffering
                    ? ($isEs ? "Bastidores: Cómo aplicamos {$mainOffering} en {$company}" : "Bastidores: Como aplicamos {$mainOffering} na {$company}")
                    : ($isEs ? "Cómo {$company} resuelve el mayor dolor de {$audience}" : "Como a {$company} resolve a maior dor de {$audience}");

                $hook = $suggestedHooks[1] ?? ($isEs
                    ? "💎 ¿Por qué el 90% de nuestros clientes eligen {$differentials} en lugar de las opciones tradicionales?"
                    : "💎 Por que nossos clientes priorizam {$differentials} em vez de soluções genéricas do mercado?");

                $caption = $isEs
                    ? "Autoridad no se proclama, se demuestra. Descubre los bastidores y procesos que garantizan resultados de alto estándar en {$company}. #Autoridad #Zaragoza #Empresas"
                    : "Autoridade não se fala, se demonstra. Conheça os bastidores que garantem o padrão de entrega impecável na {$company}. #Autoridade #B2B #Negocios";

            } elseif ($seq['stage'] === 'middle') {
                $topic = $isEs ? "Caso de Éxito: Antes vs. Después en {$niche}" : "Caso de Sucesso: Antes vs. Depois em {$niche}";
                $hook = $suggestedHooks[2] ?? ($isEs
                    ? '📈 El resultado real que logramos cuando aplicamos estrategia antes de la cámara.'
                    : '📈 O resultado que acontece quando alinhamos estratégia de vendas antes de ligar a câmera.');

                $caption = $isEs
                    ? "Un cliente llegó buscando visibilidad y salió con un posicionamiento de liderazgo en {$niche}. Mira la transformación. #Resultados #CasosDeExito"
                    : 'Resultados reais não acontecem por acaso. Veja como este cliente transformou autoridade em vendas consistentes. #CaseDeSucesso #Crescimento';

            } else {
                $closingOffering = $offerings[1] ?? ($offerings[0] ?? null);
                $topic = $closingOffering
                    ? ($isEs ? "Oferta Exclusiva: Contrata {$closingOffering} con {$company}" : "Oferta Exclusiva: Contrate {$closingOffering} com a {$company}")
                    : ($isEs ? "Oferta Exclusiva: Agenda tu sesión estratégica con {$company}" : "Oferta Exclusiva: Agende sua sessão estratégica com a {$company}");

                $hook = $suggestedHooks[3] ?? ($isEs
                    ? "🎯 Si buscas escalar tu facturación en {$niche} este trimestre, este mensaje es para ti."
                    : "🎯 Se você quer acelerar seus resultados em {$niche} este mês, este recado é direto.");

                $caption = $isEs
                    ? "Abrimos vacantes para clientes que buscan {$differentials}. Toca en el enlace del perfil o envíanos un mensaje directo ahora. 📲 #Oferta #Contacto"
                    : "Condições especiais abertas para marcas que buscam {$differentials}. Toque no link da bio ou chame no WhatsApp agora. 📲 #Vendas #Oportunidade";
            }

            $items[] = [
                'week_number' => $week,
                'funnel_stage' => $seq['stage'],
                'scheduled_day_time' => $seq['day'],
                'topic' => $topic,
                'hook_text' => $hook,
                'seo_caption' => $caption,
                'hashtags' => ['VideoMarketing', 'NUME', 'Reels', 'B2B'],
                'format' => 'vertical',
                'status' => 'pending',
            ];
        }

        return $items;
    }
}
