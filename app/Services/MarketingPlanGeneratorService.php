<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\MarketingPlanItem;
use App\Models\PackPurchase;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

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
            'inconformistas', 'evolucionar', 'innovación', 'calidad'
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
    public function generate(Cliente $cliente, int $videoQuantity = 4, ?PackPurchase $packPurchase = null, ?string $monthYear = null): MarketingPlan
    {
        $targetMonthYear = $monthYear ?: now()->format('Y-m');
        $isEs = self::isSpanishLocale($cliente);

        // 1. Cria ou reutiliza plano existente para o mês
        $plan = MarketingPlan::firstOrCreate(
            [
                'tenant_id' => $cliente->tenant_id,
                'cliente_id' => $cliente->id,
                'month_year' => $targetMonthYear,
            ],
            [
                'pack_purchase_id' => $packPurchase?->id,
                'title' => $isEs ? 'Plan Editorial Estratégico de Vídeos' : 'Plano Editorial Estratégico de Vídeos',
                'strategic_goal' => $this->buildStrategicGoal($cliente, $isEs),
                'status' => 'active',
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

        // 2. Tenta gerar via Gemini ou usa Motor Heurístico Científico
        $itemsData = $this->generateEditorialItems($cliente, $videoQuantity, $isEs);

        // 3. Persiste cada item da grade
        foreach ($itemsData as $data) {
            $plan->items()->create($data);
        }

        return $plan->load('items');
    }

    /**
     * Recalcula a grade editorial preservando itens já convertidos em produção.
     * Retorna a quantidade de novos itens gerados.
     */
    public function regeneratePlan(MarketingPlan $plan, Cliente $cliente, int $targetVideoQty): int
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

        // 4. Atualiza o título e objetivo estratégico com o DNA atual do cliente
        $plan->update([
            'title' => $isEs ? 'Plan Editorial Estratégico de Vídeos' : 'Plano Editorial Estratégico de Vídeos',
            'strategic_goal' => $this->buildStrategicGoal($cliente, $isEs),
        ]);

        // 5. Gera os novos itens para as semanas vagas
        $newItemsData = $this->generateEditorialItemsForWeeks($cliente, $availableWeeks, $isEs);

        foreach ($newItemsData as $data) {
            $plan->items()->create($data);
        }

        return count($newItemsData);
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
     * Gera os dados de cada postagem do calendário para uma quantidade fixa.
     */
    protected function generateEditorialItems(Cliente $cliente, int $qty, bool $isEs): array
    {
        $weeks = [];
        for ($i = 1; $i <= $qty; $i++) {
            $week = (int) ceil($i / max(1, ($qty / 4)));
            if ($week > 4) $week = 4;
            $weeks[] = $week;
        }

        return $this->generateEditorialItemsForWeeks($cliente, $weeks, $isEs);
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
                    ? ($isEs ? "El error en {$niche}: " . mb_substr($primaryPain, 0, 48) : "O erro em {$niche}: " . mb_substr($primaryPain, 0, 48))
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
                    ? "📈 El resultado real que logramos cuando aplicamos estrategia antes de la cámara."
                    : "📈 O resultado que acontece quando alinhamos estratégia de vendas antes de ligar a câmera.");

                $caption = $isEs
                    ? "Un cliente llegó buscando visibilidad y salió con un posicionamiento de liderazgo en {$niche}. Mira la transformación. #Resultados #CasosDeExito"
                    : "Resultados reais não acontecem por acaso. Veja como este cliente transformou autoridade em vendas consistentes. #CaseDeSucesso #Crescimento";

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


