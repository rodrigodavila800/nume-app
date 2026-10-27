<?php

namespace App\Services;

use App\Ai\Agents\SofiScriptAgent;
use App\Ai\Agents\SofiScriptBlocksAgent;
use App\Ai\SofiProvider;
use App\Enums\PlataformaAlvo;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\Video;
use App\Models\VideoType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;

class SofiCopywriterService
{
    /**
     * Gera roteiro estruturado calibrado com o perfil do cliente e diretrizes de alta retenção.
     */
    public function generate(
        string $title,
        ?string $description = null,
        ?string $keywords = null,
        string $framework = 'pattern_interrupt',
        string $tone = 'persuasive',
        string $language = 'pt_BR',
        ?Cliente $cliente = null,
        ?VideoType $videoType = null
    ): array {
        $cleanTitle = trim($title);
        $cleanDesc = trim($description ?? 'Apresentação de solução e posicionamento de marca.');
        $isSpanish = in_array($language, ['es', 'es_ES', 'es_419']);

        // Extração dos dados de DNA de marca do Cliente
        $company = $cliente?->company_name ?: ($cliente?->name ?: ($isSpanish ? 'Tu Empresa' : 'Sua Empresa'));
        $niche = $cliente?->brand_niche ?: ($isSpanish ? 'comercio y servicios' : 'empresas e serviços');
        $audience = $cliente?->target_audience ?: ($isSpanish ? 'clientes potenciales en tu sector' : 'clientes qualificados no seu setor');
        $differentials = $cliente?->key_differentials ?: ($isSpanish ? 'calidad garantizada y atención personalizada' : 'qualidade comprovada e entrega rápida');
        // Conta conectada no ecossistema primeiro; depois o @ digitado no DNA da Marca.
        $instagramHandle = $cliente?->getEffectiveSocialHandle(PlataformaAlvo::INSTAGRAM);
        $instagram = $instagramHandle ? '@'.ltrim($instagramHandle, '@') : ($isSpanish ? 'nuestro perfil' : 'nossa bio');
        $website = $cliente?->website ? preg_replace('#^https?://#', '', $cliente->website) : null;

        $clientContext = [
            'company' => $company,
            'niche' => $niche,
            'audience' => $audience,
            'differentials' => $differentials,
            'instagram' => $instagram,
            'website' => $website,
            'brand_study' => $cliente?->brand_study,
        ];

        // Tipo de vídeo cadastrado pelo admin: a estrutura vem dos blocos dele, não dos frameworks fixos.
        if ($videoType) {
            return $this->generateFromVideoType($videoType, $cleanTitle, $cleanDesc, $keywords, $tone, $isSpanish, $clientContext, $cliente);
        }

        // 1. Tenta geração profunda com a Sofi (Laravel AI SDK, uso registrado por tenant/cliente)
        $aiResult = $this->generateViaAi($cleanTitle, $cleanDesc, $keywords, $framework, $tone, $isSpanish, $clientContext, $cliente);
        if ($aiResult) {
            return $aiResult;
        }

        // 2. Fallback Heurístico Estruturado (Templates Científicos de Alta Retenção)
        return match ($framework) {
            'authority_interview' => $this->buildAuthorityScript($cleanTitle, $cleanDesc, $tone, $isSpanish, $clientContext),
            'direct_offer' => $this->buildDirectOfferScript($cleanTitle, $cleanDesc, $tone, $isSpanish, $clientContext),
            'storytelling_case' => $this->buildStorytellingScript($cleanTitle, $cleanDesc, $tone, $isSpanish, $clientContext),
            default => $this->buildPatternInterruptScript($cleanTitle, $cleanDesc, $tone, $isSpanish, $clientContext),
        };
    }

    /**
     * Gera roteiro cinematográfico inteligente via Google Gemini API.
     */
    protected function generateViaAi(
        string $title,
        string $description,
        ?string $keywords,
        string $framework,
        string $tone,
        bool $isEs,
        array $ctx,
        ?Cliente $cliente = null
    ): ?array {
        $langPrompt = $isEs
            ? "El guión y todos los textos DEBEN estar en Español de España / Zaragoza (ej. 'vídeo', 'móvil', trato cercano y profesional)."
            : 'O roteiro e todos os textos DEVEM estar em Português do Brasil de alto padrão corporativo.';

        $frameworkNames = [
            'pattern_interrupt' => 'Padrão de Interrupção & Retenção (Gancho de quebra de padrão 0-3s)',
            'authority_interview' => 'Entrevista de Autoridade (Tom de podcast com perguntas e respostas incisivas)',
            'direct_offer' => 'Oferta Direta de Alta Conversão (Problema claro, solução imediata e CTA agressivo)',
            'storytelling_case' => 'Storytelling & Estudo de Caso (Jornada de superação com cliente real)',
        ];

        $frameworkDesc = $frameworkNames[$framework] ?? $frameworkNames['pattern_interrupt'];

        $detectedNiche = SofiMarketIntelligence::detectNiche($ctx['niche'].' '.$title.' '.$description);
        $nicheInsight = SofiMarketIntelligence::getNicheInsights($detectedNiche, $isEs);
        $marketTrends = SofiMarketIntelligence::getMarketTrends($isEs);
        $antiPatterns = SofiMarketIntelligence::getAntiPatterns($isEs);

        $studyPrompt = $this->brandStudyPrompt($ctx);

        $prompt = "Você é a Sofi, Agente de IA especialista em roteiros cinematográficos de alta retenção (0-3s), SEO e algoritmos de vídeo para TikTok, Reels e YouTube Shorts da NUME Creative.\n"
                ."Você NUNCA usa clichês amadores, nunca sugere técnicas mortas de marketing e sempre foca em métricas reais e retenção visual.\n\n"
                ."{$marketTrends}\n\n"
                ."{$antiPatterns}\n\n"
                .($nicheInsight ? "INSIGHTS ESPECÍFICOS DO NICHO:\n{$nicheInsight}\n\n" : '')
                .($studyPrompt ? "{$studyPrompt}\n" : '')
                ."FRAMEWORK DE RETENÇÃO: {$frameworkDesc} (duração 50-55 s: cena 1 00:00-00:03, cena 2 00:04-00:42, cena 3 00:43-00:55)\n"
                ."TOM DE VOZ: {$tone}\n"
                ."{$langPrompt}\n\n"
                ."DADOS DO PROJETO E DO CLIENTE:\n<<<\n"
                ."- TÍTULO DO PROJETO: {$title}\n"
                ."- OBJETIVO / DESCRIÇÃO: {$description}\n"
                ."- PALAVRAS-CHAVE: {$keywords}\n"
                ."- EMPRESA: {$ctx['company']}\n"
                ."- NICHO: {$ctx['niche']}\n"
                ."- PÚBLICO-ALVO: {$ctx['audience']}\n"
                ."- DIFERENCIAIS: {$ctx['differentials']}\n"
                ."- INSTAGRAM: {$ctx['instagram']}\n"
                ."- SITE: {$ctx['website']}\n>>>";

        $data = $this->runAgent(new SofiScriptAgent($cliente?->tenant_id, $cliente?->id), $prompt, $cliente);
        if (! is_array($data) || empty($data['hook']) || empty($data['body'])) {
            return null;
        }

        $data['framework'] = $frameworkDesc;
        $data['estimated_time'] = ($data['estimated_time'] ?? '') ?: '50-55s';
        $data['client_niche'] = $ctx['niche'];
        $data['full_script'] = $data['full_script'] ?? ($data['hook']."\n\n".$data['body']."\n\n".($data['cta'] ?? ''));
        $data['seo_title'] = $data['seo_title'] ?? $title;
        $data['seo_description'] = $data['seo_description'] ?? $description;
        $data['seo_hashtags'] = is_array($data['seo_hashtags'] ?? null) ? $data['seo_hashtags'] : ['#VideoMarketing', '#SaaS'];
        $data['b_roll_suggestions'] = is_array($data['b_roll_suggestions'] ?? null) ? $data['b_roll_suggestions'] : [];

        return $data;
    }

    /**
     * Única chamada a modelo deste serviço: agente do Laravel AI SDK (uso registrado por tenant e
     * cliente, limite mensal conferido pelo AiBudget). Sem provedor, acima do limite ou em falha: null,
     * e quem chamou usa o motor heurístico.
     */
    protected function runAgent(Agent $agent, string $prompt, ?Cliente $cliente): ?array
    {
        $sofiProvider = app(SofiProvider::class);

        try {
            $options = $sofiProvider->options($cliente?->tenant, $cliente?->id);
            if ($options === null) {
                return null;
            }

            $data = $agent->prompt($prompt, ...$options)->structured ?? null;

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            // Só o tipo da exceção: a mensagem pode conter URL ou credenciais.
            Log::warning('SofiCopywriterService AI SDK falhou ('.$sofiProvider->driver().'): '.get_class($e));

            return null;
        }
    }

    /**
     * Dossiê da marca (análise do site) para o prompt. É conteúdo de terceiros: vai como dado, não como ordem.
     */
    protected function brandStudyPrompt(array $ctx): string
    {
        if (empty($ctx['brand_study']) || ! is_array($ctx['brand_study'])) {
            return '';
        }

        $study = $ctx['brand_study'];
        $studyPrompt = "DOSSIÊ ESTRATÉGICO DA MARCA & ANÁLISE DO SITE (SOFI IA):\n";
        if (! empty($study['unique_value_proposition'])) {
            $studyPrompt .= "- PROPOSTA ÚNICA DE VALOR (UVP): {$study['unique_value_proposition']}\n";
        }
        if (! empty($study['flagship_offerings'])) {
            $offerings = is_array($study['flagship_offerings']) ? implode(', ', $study['flagship_offerings']) : $study['flagship_offerings'];
            $studyPrompt .= "- PRODUTOS/SERVIÇOS EM DESTAQUE (SITE): {$offerings}\n";
        }
        if (! empty($study['target_audience_pains'])) {
            $pains = is_array($study['target_audience_pains']) ? implode(' | ', $study['target_audience_pains']) : $study['target_audience_pains'];
            $studyPrompt .= "- DORES MAPEADAS DO PÚBLICO: {$pains}\n";
        }
        if (! empty($study['content_pillars'])) {
            $pillars = is_array($study['content_pillars'])
                ? implode(', ', array_map(fn ($p) => is_array($p) ? ($p['pillar'] ?? '') : $p, $study['content_pillars']))
                : $study['content_pillars'];
            $studyPrompt .= "- PILARES DE CONTEÚDO: {$pillars}\n";
        }
        if (! empty($study['forbidden_words'])) {
            $forbidden = is_array($study['forbidden_words']) ? implode(', ', $study['forbidden_words']) : $study['forbidden_words'];
            $studyPrompt .= "- PALAVRAS/TERMOS PROIBIDOS OU A EVITAR: {$forbidden}\n";
        }

        return $studyPrompt."\n";
    }

    /**
     * Roteiro seguindo os blocos do tipo de vídeo cadastrado pelo admin: IA bloco a bloco e, se ela
     * falhar, o motor heurístico escreve cada bloco. A resposta leva a cópia da estrutura usada.
     */
    protected function generateFromVideoType(VideoType $type, string $title, string $desc, ?string $keywords, string $tone, bool $isEs, array $ctx, ?Cliente $cliente = null): array
    {
        $blocks = array_values(array_filter($type->blocks ?? [], fn ($b) => is_array($b) && ! empty($b['key'])));
        $keys = array_column($blocks, 'key');

        $ai = null;
        if ($blocks !== []) {
            $raw = $this->runAgent(
                new SofiScriptBlocksAgent($cliente?->tenant_id, $cliente?->id),
                $this->blocksPrompt($type, $blocks, $title, $desc, $keywords, $tone, $isEs, $ctx),
                $cliente
            );

            // Saída em lista [{key, text}] vira mapa; só vale se TODOS os blocos vierem preenchidos.
            $byKey = [];
            foreach ((array) ($raw['blocks'] ?? []) as $item) {
                if (is_array($item) && is_string($item['key'] ?? null) && is_string($item['text'] ?? null) && trim($item['text']) !== '') {
                    $byKey[$item['key']] = $item['text'];
                }
            }

            if ($raw !== null && array_diff($keys, array_keys($byKey)) === []) {
                $ai = ['blocks' => $byKey] + $raw;
            }
        }

        $written = [];
        foreach ($blocks as $index => $block) {
            $text = $ai ? trim($ai['blocks'][$block['key']]) : $this->heuristicBlock($block, $title, $desc, $isEs, $ctx);
            $written[] = [
                'key' => $block['key'],
                'name' => $block['name'],
                'start' => (int) $block['start'],
                'end' => (int) $block['end'],
                'script' => $this->blockHeader($index + 1, $block, $isEs)."\n".$text,
            ];
        }

        $scripts = array_column($written, 'script');

        return [
            'framework' => $type->name,
            'estimated_time' => $type->duration_seconds.'s',
            'client_niche' => $ctx['niche'],
            'video_type_id' => $type->id,
            'structure' => $type->snapshot(),
            'blocks' => $written,
            'hook' => $scripts[0] ?? '',
            'body' => implode("\n\n", array_slice($scripts, 1, -1)),
            'cta' => count($scripts) > 1 ? end($scripts) : '',
            'full_script' => implode("\n\n", $scripts),
            'seo_title' => is_string($ai['seo_title'] ?? null) ? $ai['seo_title'] : $title,
            'seo_description' => is_string($ai['seo_description'] ?? null) ? $ai['seo_description'] : $desc,
            'seo_hashtags' => is_array($ai['seo_hashtags'] ?? null) ? $ai['seo_hashtags'] : ['#VideoMarketing'],
            'b_roll_suggestions' => is_array($ai['b_roll_suggestions'] ?? null) ? $ai['b_roll_suggestions'] : [],
            'source' => $ai ? 'ai' : 'heuristic',
        ];
    }

    protected function blocksPrompt(VideoType $type, array $blocks, string $title, string $desc, ?string $keywords, string $tone, bool $isEs, array $ctx): string
    {
        $lines = [];
        foreach ($blocks as $i => $block) {
            $lines[] = ($i + 1).". [{$block['key']}] {$block['name']} (".$this->clock((int) $block['start']).' - '.$this->clock((int) $block['end'])."): {$block['purpose']}";
        }

        $langPrompt = $isEs
            ? "El guión y todos los textos DEBEN estar en Español de España (ej. 'vídeo', 'móvil'), trato cercano y profesional."
            : 'O roteiro e todos os textos DEVEM estar em Português do Brasil de alto padrão corporativo.';

        return 'TIPO DE VÍDEO: "'.$type->name.'", duração total de '.$type->duration_seconds." segundos. BLOCOS, nesta ordem ([key] nome (tempo): função):\n"
            .implode("\n", $lines)."\n\n"
            .($type->sofi_guidance ? "ORIENTAÇÃO DA PRODUTORA PARA ESTE TIPO: {$type->sofi_guidance}\n\n" : '')
            ."TOM DE VOZ: {$tone}\n{$langPrompt}\n\n"
            .$this->brandStudyPrompt($ctx)
            ."DADOS DO PROJETO E DO CLIENTE:\n<<<\n"
            ."- TÍTULO: {$title}\n- OBJETIVO: {$desc}\n- PALAVRAS-CHAVE: {$keywords}\n"
            ."- EMPRESA: {$ctx['company']}\n- NICHO: {$ctx['niche']}\n- PÚBLICO: {$ctx['audience']}\n- DIFERENCIAIS: {$ctx['differentials']}\n"
            ."- INSTAGRAM: {$ctx['instagram']}\n- SITE: {$ctx['website']}\n>>>";
    }

    /**
     * Texto heurístico de um bloco. Reconhece a função pelo key (inclusive em tipos criados pelo admin:
     * "gancho", "dor", "prova", "convite"...); bloco desconhecido usa a função descrita pelo admin.
     */
    protected function heuristicBlock(array $block, string $title, string $desc, bool $isEs, array $ctx): string
    {
        $key = mb_strtolower($block['key'].' '.$block['name']);
        $role = match (true) {
            (bool) preg_match('/hook|gancho/', $key) => 'hook',
            (bool) preg_match('/problem|dor|dolor|pain/', $key) => 'problem',
            (bool) preg_match('/turn|virada|solu|giro/', $key) => 'turn',
            (bool) preg_match('/proof|prova|prueba|depoimento|testimon|resultado/', $key) => 'proof',
            (bool) preg_match('/offer|oferta/', $key) => 'offer',
            (bool) preg_match('/cta|chamada|llamada|convite|acao|ação|acción/', $key) => 'cta',
            default => 'generic',
        };

        $link = $ctx['website'] ?: $ctx['instagram'];

        if ($isEs) {
            return match ($role) {
                'hook' => "ENCUADRE: Primer plano con zoom rápido y mirada a cámara.\nTEXTO EN PANTALLA: ¿Te pasa esto en {$ctx['niche']}?\nAUDIO / VOZ: '{$title}: lo que nadie te cuenta en los primeros 3 segundos.'",
                'problem' => "ENCUADRE: Plano medio, gesto de frustración reconocible.\nTEXTO EN PANTALLA: El problema de {$ctx['audience']}\nAUDIO / VOZ: 'Si eres de {$ctx['audience']}, seguro que esto te suena.'",
                'turn' => "ENCUADRE: Producto/servicio de {$ctx['company']} en uso, mostrando, no explicando.\nTEXTO EN PANTALLA: La salida\nAUDIO / VOZ: '{$desc}'",
                'proof' => "ENCUADRE: Resultado real, antes/después o testimonio.\nTEXTO EN PANTALLA: Prueba real\nAUDIO / VOZ: 'Por eso confían en {$ctx['company']}: {$ctx['differentials']}.'",
                'offer' => "ENCUADRE: Plano limpio del producto con la oferta en pantalla.\nTEXTO EN PANTALLA: Lo que te llevas hoy\nAUDIO / VOZ: 'Y lo mejor: puedes empezar ahora.'",
                'cta' => "ENCUADRE: Primer plano señalando el enlace.\nTEXTO EN PANTALLA: 📲 {$link}\nAUDIO / VOZ: 'Toca el enlace y da el primer paso hoy.'",
                default => "OBJETIVO DEL BLOQUE: {$block['purpose']}\nENCUADRE: B-roll de {$ctx['company']} relacionado con {$ctx['niche']}.\nAUDIO / VOZ: '{$title}'",
            };
        }

        return match ($role) {
            'hook' => "ENQUADRAMENTO: Plano fechado com zoom rápido e olhar na lente.\nTEXTO NA TELA: Isso acontece com você em {$ctx['niche']}?\nÁUDIO / FALA: '{$title}: o que ninguém te conta nos primeiros 3 segundos.'",
            'problem' => "ENQUADRAMENTO: Plano médio, gesto de frustração fácil de reconhecer.\nTEXTO NA TELA: O problema de {$ctx['audience']}\nÁUDIO / FALA: 'Se você faz parte de {$ctx['audience']}, com certeza já passou por isso.'",
            'turn' => "ENQUADRAMENTO: Produto/serviço da {$ctx['company']} em uso, mostrando em vez de explicar.\nTEXTO NA TELA: A saída\nÁUDIO / FALA: '{$desc}'",
            'proof' => "ENQUADRAMENTO: Resultado real, antes/depois ou depoimento.\nTEXTO NA TELA: Prova real\nÁUDIO / FALA: 'É por isso que confiam na {$ctx['company']}: {$ctx['differentials']}.'",
            'offer' => "ENQUADRAMENTO: Plano limpo do produto com a oferta na tela.\nTEXTO NA TELA: O que você leva hoje\nÁUDIO / FALA: 'E o melhor: dá para começar agora.'",
            'cta' => "ENQUADRAMENTO: Plano fechado apontando para o link.\nTEXTO NA TELA: 📲 {$link}\nÁUDIO / FALA: 'Toque no link e dê o primeiro passo hoje.'",
            default => "OBJETIVO DO BLOCO: {$block['purpose']}\nENQUADRAMENTO: B-roll da {$ctx['company']} ligado a {$ctx['niche']}.\nÁUDIO / FALA: '{$title}'",
        };
    }

    protected function blockHeader(int $number, array $block, bool $isEs): string
    {
        return '🎬 ['.($isEs ? 'BLOQUE' : 'BLOCO')." {$number} - ".mb_strtoupper($block['name'])
            .' ('.$this->clock((int) $block['start']).' - '.$this->clock((int) $block['end']).')]';
    }

    protected function clock(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Framework 1: Padrão de Interrupção (0-3s) + Retenção
     */
    protected function buildPatternInterruptScript(string $title, string $desc, string $tone, bool $isEs, array $ctx): array
    {
        if ($isEs) {
            $hook = "🎬 [ESCENA 1 - GANCHO DE INTERRUPCIÓN (00:00 - 00:03)]\n"
                  ."ENQUADRE: Primer plano con zoom in rápido y mirada directa a cámara.\n"
                  ."TEXTO EN PANTALLA: ⚠️ ¿Cometes este error en {$ctx['niche']}?\n"
                  ."B-ROLL / VISUAL: Plano dinámico de atención inmediata.\n"
                  ."AUDIO / VOZ: 'Si todavía estás buscando captar clientes en {$ctx['niche']} con fotos estáticas... estás quemando dinero todos los días.'";

            $body = "💡 [ESCENA 2 - AGITACIÓN & DIFERENCIAL (00:04 - 00:42)]\n"
                  ."ENQUADRE: Plano medio con iluminación profesional y cortes de apoyo.\n"
                  ."TEXTO EN PANTALLA: 82% de {$ctx['audience']} decide tras ver un vídeo\n"
                  ."B-ROLL SUGERIDO: Detalle de procesos reales de {$ctx['company']} mostrando {$ctx['differentials']}.\n"
                  ."AUDIO / VOZ: 'El mercado cambió por completo. {$desc} En {$ctx['company']}, ayudamos a que tu marca transmita confianza inmediata con narrativas que retienen la atención en los primeros 3 segundos.'";

            $ctaLink = $ctx['website'] ? "o accede a {$ctx['website']}" : 'y agenda una sesión estratégica';
            $cta = "🎯 [ESCENA 3 - LLAMADA A LA ACCIÓN (00:43 - 00:55)]\n"
                 ."ENQUADRE: Primer plano con gesto hacia la bio y sonrisa de autoridad.\n"
                 ."TEXTO EN PANTALLA: 📲 Toca en {$ctx['instagram']} ".($ctx['website'] ? "· {$ctx['website']}" : '')."\n"
                 ."BANDA SONORA: Tech Beat moderno con ritmo ascendente.\n"
                 ."AUDIO / VOZ: '¿Quieres destacar y dominar tu sector? Haz clic en el enlace de {$ctx['instagram']} {$ctaLink} hoy mismo.'";
        } else {
            $hook = "🎬 [CENA 1 - GANCHO DE PADRÃO DE INTERRUPÇÃO (00:00 - 00:03)]\n"
                  ."ENQUADRAMENTO: Plano fechado com zoom in dinâmico e olhar firme na lente.\n"
                  ."TEXTO NA TELA: ⚠️ Você comete esse erro em {$ctx['niche']}?\n"
                  ."B-ROLL / VISUAL: Corte rápido com padrão de interrupção visual.\n"
                  ."ÁUDIO / FALA: 'Se você ainda tenta atrair clientes em {$ctx['niche']} postando fotos estáticas... está deixando dinheiro na mesa todos os dias.'";

            $body = "💡 [CENA 2 - AGITAÇÃO & DIFERENCIAL (00:04 - 00:42)]\n"
                  ."ENQUADRAMENTO: Plano médio cinematográfico com B-Rolls dinâmicos.\n"
                  ."TEXTO NA TELA: 82% de {$ctx['audience']} compra após assistir vídeo\n"
                  ."B-ROLL SUGERIDO: Gravação de bastidores de {$ctx['company']} destacando {$ctx['differentials']}.\n"
                  ."ÁUDIO / FALA: 'O algoritmo mudou. {$desc} Na {$ctx['company']}, estruturamos vídeos pensados cientificamente para gerar autoridade imediata e converter quem assiste em cliente fiel.'";

            $ctaLink = $ctx['website'] ? "ou acesse {$ctx['website']}" : 'para falar com nosso time';
            $cta = "🎯 [CENA 3 - CHAMADA PARA AÇÃO (00:43 - 00:55)]\n"
                 ."ENQUADRAMENTO: Plano médio com gesto apontando para a bio/link.\n"
                 ."TEXTO NA TELA: 📲 Link na Bio: {$ctx['instagram']} ".($ctx['website'] ? "· {$ctx['website']}" : '')."\n"
                 ."TRILHA SONORA: Lo-Fi Corporativo Moderno / Beat Eletrônico de alta energia.\n"
                 ."ÁUDIO / FALA: 'Quer posicionar a sua marca no topo do mercado? Toque no link de {$ctx['instagram']} {$ctaLink} e solicite seu plano agora mesmo.'";
        }

        $fullScript = "{$hook}\n\n{$body}\n\n{$cta}";

        return [
            'framework' => 'Padrão de Interrupção & Retenção',
            'estimated_time' => '50-55s',
            'client_niche' => $ctx['niche'],
            'full_script' => $fullScript,
        ];
    }

    /**
     * Framework 2: Autoridade Executiva & B2B
     */
    protected function buildAuthorityScript(string $title, string $desc, string $tone, bool $isEs, array $ctx): array
    {
        if ($isEs) {
            $fullScript = "🎬 [ESCENA 1 - GANCHO DE AUTORIDAD EJECUTIVA (00:00 - 00:04)]\n"
                        ."ENQUADRE: Formato entrevista / podcast con micrófono profesional en toma.\n"
                        ."TEXTO EN PANTALLA: 🎙️ El estándar en {$ctx['niche']}\n"
                        ."AUDIO / VOZ: 'Hay un principio clave que los líderes de {$ctx['niche']} aplican para construir reputación inquebrantable.'\n\n"
                        ."💡 [ESCENA 2 - POSICIONAMIENTO & DIFERENCIAL (00:05 - 00:45)]\n"
                        ."B-ROLL: Tomas de instalaciones y detalles de excelencia de {$ctx['company']}.\n"
                        ."AUDIO / VOZ: '{$desc} En {$ctx['company']}, nos enfocamos en {$ctx['differentials']}. Cuando tu mensaje se transmite con claridad y autoridad visual, dejas de competir por precio.'\n\n"
                        ."🎯 [ESCENA 3 - CIERRE DE AUTORIDAD (00:46 - 00:58)]\n"
                        ."TEXTO EN PANTALLA: Sigue a {$ctx['instagram']}".($ctx['website'] ? " · {$ctx['website']}" : '')."\n"
                        ."AUDIO / VOZ: 'Síguenos en {$ctx['instagram']} para más insights de alto nivel y visita {$ctx['website']} para conocer nuestro trabajo.'";
        } else {
            $fullScript = "🎬 [CENA 1 - GANCHO DE AUTORIDADE EXECUTIVA (00:00 - 00:04)]\n"
                        ."ENQUADRAMENTO: Formato podcast / entrevista com iluminação quente e microfone no plano.\n"
                        ."TEXTO NA TELA: 🎙️ A virada de chave em {$ctx['niche']}\n"
                        ."ÁUDIO / FALA: 'Existe uma regra clara que os líderes em {$ctx['niche']} dominam para construir valor inegociável.'\n\n"
                        ."💡 [CENA 2 - POSICIONAMENTO & DIFERENCIAL (00:05 - 00:45)]\n"
                        ."B-ROLL: Imagens de alto padrão de {$ctx['company']} ressaltando {$ctx['differentials']}.\n"
                        ."ÁUDIO / FALA: '{$desc} Na {$ctx['company']}, nós priorizamos {$ctx['differentials']}. Quando sua autoridade visual reflete a excelência do seu produto, o cliente fecha sem hesitar.'\n\n"
                        ."🎯 [CENA 3 - FECHAMENTO & CTA (00:46 - 00:58)]\n"
                        ."TEXTO NA TELA: Siga {$ctx['instagram']}".($ctx['website'] ? " · {$ctx['website']}" : '')."\n"
                        ."ÁUDIO / FALA: 'Acompanhe nosso perfil em {$ctx['instagram']} para análises estratégicas e acesse nosso link para acelerar seus resultados.'";
        }

        return [
            'framework' => 'Autoridade Executiva & B2B',
            'estimated_time' => '58s',
            'client_niche' => $ctx['niche'],
            'full_script' => $fullScript,
        ];
    }

    /**
     * Framework 3: Oferta Direta & Urgência (Paid Media)
     */
    protected function buildDirectOfferScript(string $title, string $desc, string $tone, bool $isEs, array $ctx): array
    {
        if ($isEs) {
            $fullScript = "🎬 [ESCENA 1 - GANCHO DE OFERTA EXCLUSIVA (00:00 - 00:03)]\n"
                        ."TEXTO EN PANTALLA: 🔥 Oferta Especial para {$ctx['niche']}\n"
                        ."AUDIO / VOZ: 'Si eres parte de {$ctx['audience']}, esto es exactamente lo que estabas esperando.'\n\n"
                        ."💡 [ESCENA 2 - SOLUCIÓN {$ctx['company']} (00:04 - 00:35)]\n"
                        ."AUDIO / VOZ: '{$title}: {$desc}. Contamos con {$ctx['differentials']}, garantizando una entrega impecable.'\n\n"
                        ."🎯 [ESCENA 3 - CTA DIRECTA (00:36 - 00:45)]\n"
                        ."TEXTO EN PANTALLA: 📲 Toca en el botón o visita {$ctx['instagram']}\n"
                        ."AUDIO / VOZ: 'Cupos limitados. Haz clic en el botón abajo o ingresa a {$ctx['instagram']} para asegurar tu plaza ahora.'";
        } else {
            $fullScript = "🎬 [CENA 1 - GANCHO DE OFERTA EXCLUSIVA (00:00 - 00:03)]\n"
                        ."TEXTO NA TELA: 🔥 Oportunidade Exclusiva para {$ctx['niche']}\n"
                        ."ÁUDIO / FALA: 'Se você faz parte de {$ctx['audience']}, preste muita atenção nos próximos 30 segundos.'\n\n"
                        ."💡 [CENA 2 - SOLUÇÃO {$ctx['company']} (00:04 - 00:35)]\n"
                        ."ÁUDIO / FALA: '{$title}: {$desc}. Com {$ctx['differentials']}, garantimos uma experiência completa sem dores de cabeça.'\n\n"
                        ."🎯 [CENA 3 - CTA DIRETA (00:36 - 00:45)]\n"
                        ."TEXTO NA TELA: 📲 Clique em Saiba Mais ou acesse {$ctx['instagram']}\n"
                        ."ÁUDIO / FALA: 'Condições exclusivas por tempo limitado. Clique no botão abaixo ou acesse {$ctx['instagram']} para garantir sua vaga.'";
        }

        return [
            'framework' => 'Oferta Direta & Tráfego Pago',
            'estimated_time' => '45s',
            'client_niche' => $ctx['niche'],
            'full_script' => $fullScript,
        ];
    }

    /**
     * Framework 4: Storytelling & Prova Social
     */
    protected function buildStorytellingScript(string $title, string $desc, string $tone, bool $isEs, array $ctx): array
    {
        if ($isEs) {
            $fullScript = "🎬 [ESCENA 1 - GANCHO DE TRANSFORMACIÓN (00:00 - 00:04)]\n"
                        ."TEXTO EN PANTALLA: 🚀 Caso Real en {$ctx['niche']}\n"
                        ."AUDIO / VOZ: 'Muchos nos decían que destacar en {$ctx['niche']} era cada vez más difícil... hasta que implementamos este método.'\n\n"
                        ."💡 [ESCENA 2 - EL CAMINO DE {$ctx['company']} (00:05 - 00:45)]\n"
                        ."AUDIO / VOZ: '{$desc} Combinamos {$ctx['differentials']} con una estrategia visual enfocada 100% en {$ctx['audience']}. El resultado fue un incremento directo en la tasa de cierre.'\n\n"
                        ."🎯 [ESCENA 3 - CTA INSPIRACIONAL (00:46 - 00:55)]\n"
                        ."AUDIO / VOZ: 'El próximo caso de éxito puede ser tu marca. Síguenos en {$ctx['instagram']}".($ctx['website'] ? " o visita {$ctx['website']}" : '')." para comenzar.'";
        } else {
            $fullScript = "🎬 [CENA 1 - GANCHO DE TRANSFORMAÇÃO (00:00 - 00:04)]\n"
                        ."TEXTO NA TELA: 🚀 Case Real em {$ctx['niche']}\n"
                        ."ÁUDIO / FALA: 'Muitos diziam que era impossível se destacar em {$ctx['niche']} hoje em dia... até aplicarmos esta estratégia.'\n\n"
                        ."💡 [CENA 2 - O CAMINHO DA {$ctx['company']} (00:05 - 00:45)]\n"
                        ."ÁUDIO / FALA: '{$desc} Unimos {$ctx['differentials']} com uma comunicação magnética para {$ctx['audience']}. O resultado foi um salto no fechamento de contratos.'\n\n"
                        ."🎯 [CENA 3 - CTA INSPIRACIONAL (00:46 - 00:55)]\n"
                        ."ÁUDIO / FALA: 'A sua empresa pode ser o nosso próximo case. Siga {$ctx['instagram']}".($ctx['website'] ? " ou acesse {$ctx['website']}" : '')." e dê o próximo passo hoje.'";
        }

        return [
            'framework' => 'Storytelling & Case Real',
            'estimated_time' => '55s',
            'client_niche' => $ctx['niche'],
            'full_script' => $fullScript,
        ];
    }

    /**
     * Sofi AI (/ceo + /strategy): Gera SEO Estratégico completo para o Tenant (Produtora)
     */
    public function generateTenantSeo(Tenant $tenant): array
    {
        $name = $tenant->name ?: 'NUME Vídeos Estratégicos';
        $desc = $tenant->description ?: 'Produção audiovisual estratégica, roteiros persuasivos e vídeos de alta conversão.';
        $slug = $tenant->slug ?: 'nume';

        // Análise com olhar CEO: conversão, posicionamento de autoridade e retenção
        $metaTitle = "{$name} | Vídeos Estratégicos de Alta Conversão";
        if (mb_strlen($metaTitle) > 70) {
            $metaTitle = mb_substr($metaTitle, 0, 67).'...';
        }

        $metaDescription = "Aumente seu faturamento e autoridade com vídeos de alta retenção produzidos pela {$name}. Roteiros estratégicos para B2B e marcas líderes.";
        if (mb_strlen($metaDescription) > 160) {
            $metaDescription = mb_substr($metaDescription, 0, 157).'...';
        }

        $ogDescription = 'Transforme visualizações em contratos. Vídeos gravados em 4K, roteiros persuasivos e padrão de interrupção com foco em ROI.';
        if (mb_strlen($ogDescription) > 200) {
            $ogDescription = mb_substr($ogDescription, 0, 197).'...';
        }

        $keywords = [
            'produtora de videos',
            'video marketing estrategico',
            'videos para reels e tiktok',
            'producao audiovisual b2b',
            'roteiros de alta conversao',
            'video corporativo 4k',
            'padrao de interrupcao',
            mb_strtolower($name),
        ];

        return [
            'seo_meta_title' => $metaTitle,
            'seo_meta_description' => $metaDescription,
            'seo_og_description' => $ogDescription,
            'seo_schema_keywords' => implode(', ', $keywords),
            'seo_canonical_url' => url("/vitrine/{$slug}"),
            'seo_og_image' => asset('assests/img/logo.png'),
        ];
    }

    /**
     * Sofi AI (/ceo + /copywriter): Sugere títulos, descrição, hashtags e tags SEO para o vídeo entregue ao cliente
     */
    public function generateVideoSeo(Video $video, ?Cliente $cliente = null): array
    {
        $cliente = $cliente ?: $video->production?->cliente;
        $company = $cliente?->company_name ?: ($cliente?->name ?: 'Sua Marca');
        $niche = $cliente?->brand_niche ?: 'Mercado & Negócios';
        $audience = $cliente?->target_audience ?: 'Clientes em potencial';
        $title = $video->title ?: ($video->production?->title ?: 'Vídeo Estratégico');

        // Olhar CEO para título: Gancho forte + Promessa clara de valor
        $sofiTitle = "Como se destacar em {$niche}: O que você precisa saber | {$company}";
        if (mb_strlen($sofiTitle) > 100) {
            $sofiTitle = "Estratégia de Alto Impacto para {$niche} | {$company}";
        }

        // Descrição estruturada para YouTube / Instagram / TikTok / LinkedIn
        $sofiDescription = "Você quer saber como atrair mais clientes e se consolidar em {$niche}?\n\n"
            ."Neste vídeo especial da {$company}, mostramos na prática os pontos fundamentais que geram autoridade imediata e retenção máxima.\n\n"
            ."🎯 Direcionado para: {$audience}\n"
            ."💡 Produção e Estratégia Audiovisual: Padrão NUME Creative\n\n"
            .'Comente o que achou e compartilhe com quem precisa transformar conteúdo em resultados!';

        // Hashtags estratégicas organizadas por nicho e alcance orgânico
        $sanitizeTag = fn ($str) => preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $str));
        $companyTag = '#'.$sanitizeTag($company);
        $nicheWords = explode(' ', $niche);
        $nicheTag = ! empty($nicheWords[0]) ? '#'.$sanitizeTag($nicheWords[0]) : '#Negocios';

        $hashtags = array_unique(array_filter([
            $companyTag,
            $nicheTag,
            '#VideoMarketing',
            '#EstrategiaDigital',
            '#AltaConversao',
            '#CrescimentoOrganico',
            '#Empreendedorismo',
            '#ProducaoAudiovisual',
            '#NUME',
        ]));

        $keywords = [
            mb_strtolower($company),
            mb_strtolower($niche),
            'dicas de '.mb_strtolower($niche),
            'autoridade em '.mb_strtolower($niche),
            'video marketing',
            'como atrair '.mb_strtolower($audience),
            'conteudo estrategico',
        ];

        return [
            'sofi_seo_title' => $sofiTitle,
            'sofi_seo_description' => $sofiDescription,
            'sofi_hashtags' => array_values($hashtags),
            'sofi_seo_keywords' => implode(', ', $keywords),
        ];
    }

    /**
     * Sofi AI (/ceo + /copywriter): Gera automaticamente copy comercial, entregáveis e Add-ons estratégicos para um pacote
     */
    public function generatePackContent(
        string $categoryName = '',
        string $packName = '',
        float $price = 0,
        int $videoQuantity = 1,
        string $language = 'es'
    ): array {
        $isEs = in_array(strtolower($language), ['es', 'es_es', 'es_419']);
        $cat = mb_strtolower($categoryName);
        $name = trim($packName) ?: ($isEs ? 'Pack Estratégico Audiovisual' : 'Pacote Estratégico Audiovisual');
        $qty = max(1, $videoQuantity);

        // Análise de Nicho/Tipo de Produção
        if (str_contains($cat, 'ad') || str_contains($cat, 'anuncio') || str_contains(mb_strtolower($name), 'ad')) {
            $desc = $isEs
                ? 'Campañas de vídeo diseñadas con ganchos de alta retención (0-3s) y llamadas a la acción directas para maximizar el ROI en Meta Ads y TikTok.'
                : 'Campanhas de vídeo estruturadas com ganchos de padrão de interrupção (0-3s) e CTAs diretas para maximizar o ROI no Meta Ads e TikTok.';
            $features = $isEs ? [
                "{$qty} vídeos orientados 100% a captación y ventas",
                'Guiones con ganchos de alta retención (0-3s)',
                'Subtítulos dinámicos de alto impacto visual',
                'Formatos optimizados para Reels, TikTok y Feed (9:16 / 4:5)',
                'Asesoramiento en segmentación de audiencia',
            ] : [
                "{$qty} vídeos focados 100% em captação e conversão",
                'Roteiros com ganchos de padrão de interrupção (0-3s)',
                'Legendas dinâmicas de alto impacto visual',
                'Formatos otimizados para Reels, TikTok e Feed',
                'Consultoria de segmentação de público',
            ];
            $addons = $isEs ? [
                ['name' => 'Variaciones de Ganchos A/B (0-3s)', 'price' => 90],
                ['name' => 'Animación de Texto & Motion Graphics', 'price' => 80],
                ['name' => 'Gestión y Tráfico Pagado (Meta Ads)', 'price' => 200],
                ['name' => 'Composición con Inteligencia Artificial', 'price' => 100],
            ] : [
                ['name' => 'Variações de Ganchos A/B (0-3s)', 'price' => 450],
                ['name' => 'Animação de Texto & Motion Graphics', 'price' => 350],
                ['name' => 'Gestão e Tráfego Pago (Meta Ads)', 'price' => 900],
                ['name' => 'Composição com Inteligência Artificial', 'price' => 500],
            ];
        } elseif (str_contains($cat, 'podcast') || str_contains(mb_strtolower($name), 'podcast')) {
            $desc = $isEs
                ? 'Producción multicámara profesional de podcast con cortes estratégicos (Reels/Shorts) para viralizar tu mensaje y consolidar autoridad.'
                : 'Produção multicâmera profissional de podcast com cortes estratégicos (Reels/Shorts) para viralizar seu conteúdo e consolidar autoridade.';
            $features = $isEs ? [
                'Grabación multicámara 4K en estudio o localización',
                'Tratamiento y masterización de audio profesional',
                'Edición integral del episodio completo',
                'Cortes estratégicos (Shorts/Reels) incluidos',
                'Aprobación y feedback en el Portal del Cliente',
            ] : [
                'Gravação multicâmera 4K em estúdio ou externa',
                'Tratamento e masterização de áudio profissional',
                'Edição integral do episódio completo',
                'Cortes estratégicos (Shorts/Reels) inclusos',
                'Aprovação e feedback no Portal do Cliente',
            ];
            $addons = $isEs ? [
                ['name' => 'Cortes adicionales para redes (+3 shorts)', 'price' => 120],
                ['name' => 'Composición y Escenarios con IA', 'price' => 100],
                ['name' => 'Motion Graphics y animaciones de marca', 'price' => 90],
                ['name' => 'Imágenes de apoyo y banco premium', 'price' => 60],
            ] : [
                ['name' => 'Cortes adicionais para redes (+3 shorts)', 'price' => 550],
                ['name' => 'Composição e Cenários com IA', 'price' => 500],
                ['name' => 'Motion Graphics e animações de marca', 'price' => 400],
                ['name' => 'Imagens de apoio e banco premium', 'price' => 300],
            ];
        } elseif (str_contains($cat, 'evento') || str_contains(mb_strtolower($name), 'evento')) {
            $desc = $isEs
                ? 'Cobertura audiovisual cinematográfica de eventos corporativos: Aftermovie dinámico, testimonios y fotografía profesional para inmortalizar tu marca.'
                : 'Cobertura audiovisual cinematográfica de eventos corporativos: Aftermovie dinâmico, depoimentos e fotografia profissional para valorizar sua marca.';
            $features = $isEs ? [
                'Cobertura presencial con equipo audiovisual completo',
                'Aftermovie cinematográfico dinámico',
                'Selección de fotografías profesionales tratadas',
                'Cortes rápidos para difusión en tiempo real',
                'Entrega en máxima resolución 4K',
            ] : [
                'Cobertura presencial com equipe audiovisual completa',
                'Aftermovie cinematográfico dinâmico',
                'Seleção de fotografias profissionais tratadas',
                'Cortes rápidos para divulgação em tempo real',
                'Entrega em resolução máxima 4K',
            ];
            $addons = $isEs ? [
                ['name' => 'Grabación de Ponencia Completa', 'price' => 400],
                ['name' => 'Testimonios en vídeo de asistentes/invitados', 'price' => 150],
                ['name' => 'Piloto de Dron 4K Certificado', 'price' => 180],
                ['name' => 'Edición Express (Entrega en 24 horas)', 'price' => 250],
            ] : [
                ['name' => 'Gravação de Palestra/Palco Completa', 'price' => 1800],
                ['name' => 'Depoimentos em vídeo de convidados', 'price' => 700],
                ['name' => 'Piloto de Drone 4K Certificado', 'price' => 850],
                ['name' => 'Edição Express (Entrega em 24h)', 'price' => 1200],
            ];
        } elseif (str_contains($cat, 'instituc') || str_contains(mb_strtolower($name), 'instituc')) {
            $desc = $isEs
                ? 'Vídeo de presentación corporativa de alto impacto para comunicar la esencia, dimensión e infraestructura de tu empresa con estética cinematográfica.'
                : 'Vídeo de apresentação corporativa de alto padrão para comunicar a essência, dimensão e infraestrutura da sua empresa com estética cinematográfica.';
            $features = $isEs ? [
                'Guion institucional y narrativa corporativa',
                'Grabación cinematográfica 4K de instalaciones y equipo',
                'Color grading y corrección de color avanzada',
                'Diseño sonoro y banda sonora con licencia comercial',
                'Versión completa + versión reducida para redes',
            ] : [
                'Roteiro institucional e narrativa corporativa',
                'Gravação cinematográfica 4K de instalações e equipe',
                'Color grading e correção de cor avançada',
                'Sound design e trilha com licença comercial',
                'Versão completa + versão reduzida para redes',
            ];
            $addons = $isEs ? [
                ['name' => 'Locución Profesional en múltiples idiomas', 'price' => 80],
                ['name' => 'Imágenes aéreas con Dron 4K', 'price' => 180],
                ['name' => 'Subtítulos y traducción oficial', 'price' => 60],
                ['name' => 'Composición de Efectos Visuales con IA', 'price' => 120],
            ] : [
                ['name' => 'Locução Profissional em múltiplos idiomas', 'price' => 400],
                ['name' => 'Imagens aéreas com Drone 4K', 'price' => 850],
                ['name' => 'Legendas e tradução oficial', 'price' => 300],
                ['name' => 'Composição de Efeitos Visuais com IA', 'price' => 600],
            ];
        } else {
            // Padrão: Posicionamiento de Marca / Reels
            $desc = $isEs
                ? 'Producción audiovisual recurrente para posicionar tu marca como referente de mercado mediante narrativas persuasivas y retención algorítmica.'
                : 'Produção audiovisual recorrente para posicionar sua marca como referência de mercado através de narrativas persuasivas e retenção algorítmica.';
            $features = $isEs ? [
                "{$qty} vídeos grabados y editados en calidad profesional",
                'Roteirización estratégica orientada a tu público objetivo',
                'Alineación con directrices de retención (0-3s)',
                'Edición dinámica, b-rolls y subtítulos animados',
                'Aprobación individual por vídeo en el Portal del Cliente',
            ] : [
                "{$qty} vídeos gravados e editados em qualidade profissional",
                'Roteirização estratégica orientada ao seu público-alvo',
                'Alinhamento com diretrizes de retenção (0-3s)',
                'Edição dinâmica, b-rolls e legendas animadas',
                'Aprovação individual por vídeo no Portal do Cliente',
            ];
            $addons = $isEs ? [
                ['name' => 'Composición con Inteligencia Artificial', 'price' => 100],
                ['name' => 'Animación de Texto & Motion Graphics', 'price' => 80],
                ['name' => 'Tráfico Pagado / Campañas Meta Ads', 'price' => 200],
                ['name' => 'Locución Profesional de Voz', 'price' => 70],
            ] : [
                ['name' => 'Composição com Inteligência Artificial', 'price' => 500],
                ['name' => 'Animação de Texto & Motion Graphics', 'price' => 400],
                ['name' => 'Tráfego Pago / Campanhas Meta Ads', 'price' => 900],
                ['name' => 'Locução Profissional de Voz', 'price' => 350],
            ];
        }

        return [
            'description' => $desc,
            'features' => $features,
            'addons' => $addons,
            'vat_rate' => 21.00,
            'currency' => $isEs ? 'eur' : 'brl',
        ];
    }

    /**
     * Gera um lote estratégico de 4, 8 ou 12 roteiros e produções para o mês (Sofi Batch Content Planner).
     *
     * @return array<int, array{title: string, description: string, framework: string, hook: string, script_content: string, target_day_offset: int, keywords: string}>
     */
    public function generateBatchEditorialPlan(
        Cliente $cliente,
        string $theme,
        int $quantity = 4,
        string $language = 'pt_BR',
        ?string $notes = null
    ): array {
        $quantity = in_array($quantity, [4, 8, 12]) ? $quantity : 4;
        $isSpanish = in_array($language, ['es', 'es_ES', 'es_419']);

        $company = $cliente->company_name ?: ($cliente->name ?: ($isSpanish ? 'Tu Marca' : 'Sua Marca'));
        $niche = $cliente->brand_niche ?: ($isSpanish ? 'comercio y servicios' : 'negócios e serviços');

        $frameworkTemplates = [
            0 => [
                'framework' => 'pattern_interrupt',
                'funnel_stage' => 'topo',
                'funnel_label_pt' => 'Topo de Funil (Atração & Retenção)',
                'funnel_label_es' => 'Top de Embudo (Atracción & Retención)',
                'angle_pt' => "O maior erro em {$niche} que custa caro",
                'angle_es' => "El mayor error en {$niche} que cuesta muy caro",
                'purpose_pt' => 'Topo de Funil: Quebra de mito e retenção máxima nos 3 primeiros segundos.',
                'purpose_es' => 'Top de Embudo: Romper mitos y retención máxima en los primeros 3 segundos.',
            ],
            1 => [
                'framework' => 'authority_interview',
                'funnel_stage' => 'meio',
                'funnel_label_pt' => 'Meio de Funil (Autoridade & Método)',
                'funnel_label_es' => 'Medio de Embudo (Autoridad & Método)',
                'angle_pt' => "Bastidores & O Segredo que ninguém conta sobre {$theme}",
                'angle_es' => "Detrás de escena & El secreto que nadie cuenta sobre {$theme}",
                'purpose_pt' => 'Meio de Funil: Autoridade, didática e posicionamento premium.',
                'purpose_es' => 'Medio de Embudo: Autoridad, didáctica y posicionamiento premium.',
            ],
            2 => [
                'framework' => 'storytelling_case',
                'funnel_stage' => 'meio',
                'funnel_label_pt' => 'Meio de Funil (Estudo de Caso & Prova)',
                'funnel_label_es' => 'Medio de Embudo (Estudio de Caso & Prueba)',
                'angle_pt' => "Como um cliente transformou seus resultados com a {$company}",
                'angle_es' => "Cómo un cliente transformó sus resultados con {$company}",
                'purpose_pt' => 'Fundo de Funil: Estudo de caso real, jornada do herói e prova social irrefutável.',
                'purpose_es' => 'Fondo de Embudo: Estudio de caso real, viaje del héroe y prueba social irrefutable.',
            ],
            3 => [
                'framework' => 'direct_offer',
                'funnel_stage' => 'fundo',
                'funnel_label_pt' => 'Fundo de Funil (Oferta & Conversão)',
                'funnel_label_es' => 'Fondo de Embudo (Oferta & Conversión)',
                'angle_pt' => "Condição Exclusiva de {$theme}: O Próximo Passo",
                'angle_es' => "Condición Exclusiva de {$theme}: El Próximo Paso",
                'purpose_pt' => 'Conversão Direta: Oferta clara com gatilho de urgência e CTA para WhatsApp/Direct.',
                'purpose_es' => 'Conversión Directa: Oferta clara con gatillo de urgencia y CTA directo a ventas.',
            ],
            4 => [
                'framework' => 'pattern_interrupt',
                'funnel_stage' => 'topo',
                'funnel_label_pt' => 'Topo de Funil (Viralidade & Polêmica)',
                'funnel_label_es' => 'Top de Embudo (Viralidad & Polémica)',
                'angle_pt' => "Pare de fazer isso se você quer ter sucesso em {$theme}",
                'angle_es' => "Deja de hacer esto si quieres tener éxito en {$theme}",
                'purpose_pt' => 'Viralidade: Interrupção de rolagem com conselho contraintuitivo.',
                'purpose_es' => 'Viralidad: Interrupción de scroll con consejo contraintuitivo.',
            ],
            5 => [
                'framework' => 'authority_interview',
                'funnel_stage' => 'meio',
                'funnel_label_pt' => 'Meio de Funil (Critérios & Educação)',
                'funnel_label_es' => 'Medio de Embudo (Criterios & Educación)',
                'angle_pt' => "3 perguntas essenciais antes de contratar qualquer serviço de {$niche}",
                'angle_es' => "3 preguntas esenciales antes de contratar cualquier servicio de {$niche}",
                'purpose_pt' => 'Educação do Mercado: Filtro de clientes qualificados.',
                'purpose_es' => 'Educación del Mercado: Filtro de clientes cualificados.',
            ],
            6 => [
                'framework' => 'storytelling_case',
                'funnel_stage' => 'meio',
                'funnel_label_pt' => 'Meio de Funil (Conexão & Valores)',
                'funnel_label_es' => 'Medio de Embudo (Conexión & Valores)',
                'angle_pt' => "O dia em que quase tudo deu errado e a lição que aprendemos na {$company}",
                'angle_es' => "El día que casi todo salió mal y la lección que aprendimos en {$company}",
                'purpose_pt' => 'Conexão Humana: Vulnerabilidade calculada e reforço de valores.',
                'purpose_es' => 'Conexión Humana: Vulnerabilidad calculada y refuerzo de valores.',
            ],
            7 => [
                'framework' => 'direct_offer',
                'funnel_stage' => 'fundo',
                'funnel_label_pt' => 'Fundo de Funil (Escassez & Fechamento)',
                'funnel_label_es' => 'Fondo de Embudo (Escasez & Cierre)',
                'angle_pt' => "Últimas Vagas para a Campanha {$theme}",
                'angle_es' => "Últimas Plazas para la Campaña {$theme}",
                'purpose_pt' => 'Fechamento: Escassez legítima para prospects indecisos.',
                'purpose_es' => 'Cierre: Escasez legítima para prospectos indecisos.',
            ],
            8 => [
                'framework' => 'pattern_interrupt',
                'funnel_stage' => 'topo',
                'funnel_label_pt' => 'Topo de Funil (Urgência & Futuro)',
                'funnel_label_es' => 'Top de Embudo (Urgencia & Futuro)',
                'angle_pt' => "Se você ignorar isso sobre {$theme} em 2026, você vai ficar para trás",
                'angle_es' => "Si ignoras esto sobre {$theme} en 2026, te quedarás atrás",
                'purpose_pt' => 'Urgência e Tendência: Posicionamento como pioneiro.',
                'purpose_es' => 'Urgencia y Tendencia: Posicionamiento como pionero.',
            ],
            9 => [
                'framework' => 'authority_interview',
                'funnel_stage' => 'meio',
                'funnel_label_pt' => 'Meio de Funil (Hack Prático & Valor)',
                'funnel_label_es' => 'Medio de Embudo (Hack Práctico & Valor)',
                'angle_pt' => "Dica de 60 Segundos: O Hack prático em {$theme}",
                'angle_es' => "Consejo de 60 Segundos: El Hack práctico en {$theme}",
                'purpose_pt' => 'Micro-conteúdo: Alto salvamento e compartilhamento.',
                'purpose_es' => 'Micro-contenido: Alto guardado y compartición.',
            ],
            10 => [
                'framework' => 'storytelling_case',
                'funnel_stage' => 'meio',
                'funnel_label_pt' => 'Meio de Funil (Antes vs. Depois)',
                'funnel_label_es' => 'Medio de Embudo (Antes vs. Después)',
                'angle_pt' => "Antes vs. Depois: O impacto real do método {$company}",
                'angle_es' => "Antes vs. Después: El impacto real del método {$company}",
                'purpose_pt' => 'Comparativo visual dinâmico.',
                'purpose_es' => 'Comparativa visual dinámica.',
            ],
            11 => [
                'framework' => 'direct_offer',
                'funnel_stage' => 'fundo',
                'funnel_label_pt' => 'Fundo de Funil (Chamada Final & Venda)',
                'funnel_label_es' => 'Fondo de Embudo (Llamada Final & Venta)',
                'angle_pt' => "Chamada Final: Agende sua consultoria sobre {$theme}",
                'angle_es' => "Llamada Final: Agenda tu consultoría sobre {$theme}",
                'purpose_pt' => 'Encerramento de Mês: Bateria de agendamentos.',
                'purpose_es' => 'Cierre de Mes: Batería de citas y llamadas.',
            ],
        ];

        $plan = [];

        for ($i = 0; $i < $quantity; $i++) {
            $template = $frameworkTemplates[$i % count($frameworkTemplates)];
            $title = $isSpanish ? $template['angle_es'] : $template['angle_pt'];
            $desc = ($isSpanish ? $template['purpose_es'] : $template['purpose_pt']).($notes ? " [Contexto: {$notes}]" : '');
            $funnelLabel = $isSpanish ? $template['funnel_label_es'] : $template['funnel_label_pt'];

            // Gera o roteiro completo usando o gerador individual do serviço
            $scriptData = $this->generate(
                title: $title,
                description: $desc,
                keywords: "{$theme}, {$niche}, {$company}",
                framework: $template['framework'],
                tone: 'persuasive',
                language: $language,
                cliente: $cliente
            );

            $scriptContent = $scriptData['full_script'] ?? $scriptData['script'] ?? '';
            $hook = $scriptData['hook'] ?? '';

            if (empty($hook)) {
                if (preg_match("/(?:ÁUDIO \/ FALA|AUDIO \/ VOZ):\s*['\"]([^'\"]+)['\"]/u", $scriptContent, $matches)) {
                    $hook = $matches[1];
                } else {
                    $hook = Str::limit($scriptContent, 140);
                }
            }

            // Espaçamento sugerido de dias ao longo do mês (ex: 4 vídeos = dias 7, 14, 21, 28)
            $intervalDays = (int) floor(28 / $quantity);
            $dayOffset = ($i + 1) * $intervalDays;

            $plan[] = [
                'item_number' => $i + 1,
                'title' => $title,
                'description' => $desc,
                'framework' => $template['framework'],
                'funnel_stage' => $template['funnel_stage'],
                'funnel_label' => $funnelLabel,
                'hook' => $hook,
                'script_content' => $scriptContent,
                'seo_keywords' => $scriptData['keywords'] ?? "{$theme}, {$niche}",
                'target_day_offset' => $dayOffset,
                'suggested_date' => now()->addDays($dayOffset)->toDateString(),
            ];
        }

        return $plan;
    }
}
