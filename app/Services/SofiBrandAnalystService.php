<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SofiBrandAnalystService
{
    public function __construct(
        protected BrandDnaCrawlerService $crawler
    ) {}

    /**
     * Analisa o site e o DNA da marca do cliente e gera um Dossiê Estratégico de Posicionamento.
     */
    public function analyze(Cliente $cliente, ?string $explicitUrl = null): array
    {
        $urlToCrawl = $explicitUrl ?: $cliente->website;
        $crawlerData = ! empty($urlToCrawl) ? $this->crawler->crawl($urlToCrawl) : null;

        $isEs = MarketingPlanGeneratorService::isSpanishLocale($cliente);

        // 1. Tenta gerar via IA (Gemini) com saída JSON estruturada
        $aiStudy = $this->analyzeViaAi($cliente, $crawlerData, $isEs);
        if ($aiStudy) {
            $cliente->update([
                'brand_study' => $aiStudy,
                'brand_study_generated_at' => now(),
            ]);

            return $aiStudy;
        }

        // 2. Fallback Heurístico Estruturado caso a API esteja offline ou indisponível
        $heuristicStudy = $this->buildHeuristicStudy($cliente, $crawlerData, $isEs);
        $cliente->update([
            'brand_study' => $heuristicStudy,
            'brand_study_generated_at' => now(),
        ]);

        return $heuristicStudy;
    }

    /**
     * Chamada à API Gemini com cascade de modelos e Circuit Breaker.
     */
    protected function analyzeViaAi(Cliente $cliente, ?array $crawlerData, bool $isEs): ?array
    {
        $apiKey = config('services.gemini.key') 
            ?: Setting::get('gemini_api_key') 
            ?: env('GEMINI_API_KEY');

        if (! $apiKey || Cache::get('gemini_sales_circuit_breaker')) {
            return null;
        }

        $primaryModel = config('services.gemini.model') 
            ?: Setting::get('gemini_model') 
            ?: env('GEMINI_MODEL', 'gemini-3.5-flash-lite');

        // Cascade de modelos para alta disponibilidade
        $candidateModels = array_values(array_unique(array_filter([
            $primaryModel,
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-1.5-flash',
        ])));

        $company = $cliente->company_name ?: ($cliente->name ?: 'Empresa Cliente');
        $niche = $cliente->brand_niche ?: 'Serviços e Negócios';
        $audience = $cliente->target_audience ?: 'Clientes corporativos e consumidores qualificados';
        $differentials = $cliente->key_differentials ?: 'Excelência e atendimento diferenciado';
        $websiteText = $crawlerData['text_summary'] ?? 'Nenhum dado de site disponível';
        $websiteUrl = $crawlerData['url'] ?? $cliente->website ?? '';

        $langPrompt = $isEs
            ? "TODAS as respostas e análises DEVEM estar em Espanhol da Espanha (Zaragoza)."
            : "TODAS as respostas e análises DEVEM estar em Português do Brasil de alto nível executivo.";

        $prompt = "Você é a Sofi, Diretora de Branding e Estrategista Audiovisual B2B da NUME Creative.\n"
                . "Analise as informações do perfil corporativo e a extração do website da empresa para criar um DOSSIÊ ESTRATÉGICO DE POSICIONAMENTO E VÍDEO.\n\n"
                . "{$langPrompt}\n\n"
                . "DADOS DA EMPRESA:\n"
                . "- NOME: {$company}\n"
                . "- NICHO: {$niche}\n"
                . "- PÚBLICO-ALVO INFORMADO: {$audience}\n"
                . "- DIFERENCIAIS INFORMADOS: {$differentials}\n"
                . "- WEBSITE: {$websiteUrl}\n\n"
                . "CONTEÚDO EXTRAÍDO DO WEBSITE OFICIAL:\n"
                . "{$websiteText}\n\n"
                . "Retorne ESTRITAMENTE um objeto JSON válido (sem blocos de código markdown ou texto explicativo extra) com o seguinte formato:\n"
                . "{\n"
                . '  "unique_value_proposition": "Proposta única de valor clara e magnética em 1 ou 2 frases",' . "\n"
                . '  "tone_of_voice": "Definição do tom de voz arquetípico (ex: Especialista e direto, Sofisticado e acolhedor, etc.)",' . "\n"
                . '  "flagship_offerings": ["Serviço ou produto principal 1", "Serviço ou produto principal 2", "Serviço ou produto principal 3"],' . "\n"
                . '  "target_audience_pains": ["Dor profunda 1 que o cliente quer resolver", "Dor 2", "Dor 3"],' . "\n"
                . '  "content_pillars": [' . "\n"
                . '    {"pillar": "Quebra de Mitos / Padrão", "focus": "Tema central para vídeo de topo de funil"},' . "\n"
                . '    {"pillar": "Autoridade & Processos", "focus": "Tema central para vídeo de meio de funil"},' . "\n"
                . '    {"pillar": "Transformação & Cases", "focus": "Tema central para vídeo de prova social"},' . "\n"
                . '    {"pillar": "Oferta Direta", "focus": "Tema central para conversão imediata"}' . "\n"
                . '  ],' . "\n"
                . '  "forbidden_words": ["termo amador 1", "clichê que a marca deve evitar"]' . "\n"
                . "}";

        foreach ($candidateModels as $candidateModel) {
            try {
                $response = Http::timeout(18.0)
                    ->connectTimeout(4.0)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$candidateModel}:generateContent?key={$apiKey}", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [['text' => $prompt]]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 1500,
                            'responseMimeType' => 'application/json',
                        ]
                    ]);

                if ($response->successful()) {
                    $raw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
                    $clean = preg_replace('/\s*```$/', '', $clean);

                    $data = json_decode($clean, true);
                    if (is_array($data) && !empty($data['unique_value_proposition'])) {
                        $data['analyzed_at'] = now()->toDateTimeString();
                        $data['source_url'] = $websiteUrl;
                        $data['engine'] = "gemini:{$candidateModel}";
                        $data['crawler_summary'] = [
                            'crawled_url' => $websiteUrl,
                            'http_status' => $crawlerData['http_status'] ?? 200,
                            'title' => $crawlerData['title'] ?? '',
                            'meta_description' => $crawlerData['meta_description'] ?? '',
                            'headings_count' => count($crawlerData['headings'] ?? []),
                            'raw_preview' => mb_substr($crawlerData['text_summary'] ?? '', 0, 400),
                        ];

                        return $data;
                    }
                } else {
                    Log::warning("SofiBrandAnalystService model {$candidateModel} error: " . substr($response->body(), 0, 150));
                }
            } catch (Exception $e) {
                Log::warning("SofiBrandAnalystService model {$candidateModel} exception: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Síntese heurística profunda combinando dados reais do site e do formulário do cliente.
     */
    protected function buildHeuristicStudy(Cliente $cliente, ?array $crawlerData, bool $isEs): array
    {
        $company = $cliente->company_name ?: ($cliente->name ?: 'Empresa Cliente');
        $niche = $cliente->brand_niche ?: ($isEs ? 'Servicios Profesionales' : 'Serviços Profissionais');
        $audience = $cliente->target_audience ?: ($isEs ? 'Clientes que buscan calidad y confianza' : 'Clientes que buscam excelência e confiança');
        $differentials = $cliente->key_differentials ?: ($isEs ? 'Atención personalizada y experiencia' : 'Atendimento personalizado e agilidade');
        $websiteUrl = $crawlerData['url'] ?? $cliente->website ?? '';

        // 1. Extração inteligente de ofertas reais a partir dos cabeçalhos do site
        $headings = $crawlerData['headings'] ?? [];
        $flagshipOfferings = [];
        $ignoredPatterns = '/contacto|blog|inicio|home|aviso|política|copyright|cookies|¿cómo trabajo\?|¿podrías ser tú\?|mira cómo puedo|cuando te autolideras/i';

        foreach ($headings as $h) {
            $hClean = trim(preg_replace('/\s+/', ' ', $h));
            // Filtra fragmentos curtos ou incompletos (ex: "Lidéra(te) y") e perguntas de transição
            if (mb_strlen($hClean) >= 8 && mb_strlen($hClean) <= 70 && ! preg_match($ignoredPatterns, $hClean)) {
                // Não adiciona se terminar com conjunção quebrada
                if (! preg_match('/(\s(y|e|o|u|de|en|a|com))$/i', $hClean)) {
                    if (! in_array($hClean, $flagshipOfferings, true)) {
                        $flagshipOfferings[] = $hClean;
                    }
                    if (count($flagshipOfferings) >= 4) break;
                }
            }
        }

        if (empty($flagshipOfferings)) {
            $flagshipOfferings = $isEs
                ? ["Consultoría Estratégica en {$niche}", "Soluciones Avanzadas para {$company}", "Atención Personalizada y Resultados"]
                : ["Consultoria Estratégica em {$niche}", "Soluções Avançadas com {$company}", "Atendimento Personalizado e Alta Performance"];
        }

        // 2. Extração inteligente de dores reais a partir dos parágrafos do site
        $paragraphs = $crawlerData['paragraphs'] ?? [];
        $pains = [];
        foreach ($paragraphs as $p) {
            $pClean = trim(preg_replace('/\s+/', ' ', $p));
            if (preg_match('/insatisfacción|estancamiento|miedos|gris|arrastra|no queremos|no estás|problema|desafío|perda|dificuldade|frustra/i', $pClean)) {
                if (mb_strlen($pClean) > 25 && mb_strlen($pClean) < 180) {
                    $pains[] = $pClean;
                    if (count($pains) >= 3) break;
                }
            }
        }

        if (empty($pains)) {
            $pains = $isEs
                ? [
                    "Pérdida de tiempo y presupuesto con soluciones genéricas que no generan conversión",
                    "Dificultad para transmitir la verdadera calidad y autoridad de {$company} en los primeros 3 segundos",
                    "Sensación de estancamiento frente a competidores que comunican de manera más dinámica en redes",
                ]
                : [
                    "Perda de tempo e orçamento com abordagens genéricas que não geram autoridade imediata",
                    "Dificuldade de comunicar a verdadeira qualidade de {$company} nos primeiros 3 segundos de vídeo",
                    "Sensação de estagnação diante de concorrentes que se comunicam com vídeos dinâmicos no feed",
                ];
        }

        // 3. Proposta Única de Valor (UVP)
        $metaDesc = $crawlerData['meta_description'] ?? '';
        $title = $crawlerData['title'] ?? '';
        $uvp = '';
        if ($metaDesc && mb_strlen($metaDesc) > 25) {
            $uvp = preg_replace('/\s*\[\s*…\s*\]\s*|\s*\.\.\.\s*/u', '', $metaDesc);
            $uvp = mb_substr(trim($uvp), 0, 220);
        } elseif ($title && mb_strlen($title) > 10) {
            $uvp = $isEs 
                ? "{$title}: Soluciones y posicionamiento referente en {$niche}, combinando {$differentials}."
                : "{$title}: Soluções e posicionamento de liderança em {$niche}, unindo {$differentials}.";
        } else {
            $uvp = $isEs
                ? "Liderazgo y posicionamiento referente en {$niche}, combinando {$differentials} para transformar los resultados de {$audience}."
                : "Posicionamento de liderança e autoridade em {$niche}, unindo {$differentials} para acelerar os resultados de {$audience}.";
        }

        $firstPillarTopic = !empty($flagshipOfferings[0]) ? $flagshipOfferings[0] : $niche;
        $pillars = $isEs
            ? [
                ['pillar' => 'Ruptura de Mitos (0-3s)', 'focus' => "Desmitificar los principales errores y bloqueos en torno a {$firstPillarTopic}"],
                ['pillar' => 'Autoridad & Bastidores', 'focus' => "Demostrar cómo {$company} aplica {$differentials} en la práctica"],
                ['pillar' => 'Prueba Social & Transformación', 'focus' => "Casos de éxito reales de {$audience} que lograron superar el estancamiento"],
                ['pillar' => 'Oferta Directa & Conversión', 'focus' => "Llamada a la acción clara para sesión estratégica o contratación"],
            ]
            : [
                ['pillar' => 'Quebra de Mitos (0-3s)', 'focus' => "Desmistificar os maiores erros e bloqueios em torno de {$firstPillarTopic}"],
                ['pillar' => 'Autoridade & Bastidores', 'focus' => "Demonstrar como {$company} entrega {$differentials} na prática"],
                ['pillar' => 'Prova Social & Transformação', 'focus' => "Histórias reais de {$audience} que superaram a estagnação e alcançaram resultados"],
                ['pillar' => 'Oferta Direta & Fechamento', 'focus' => "Chamada clara para agendamento de sessão estratégica e contratação"],
            ];

        $forbiddenWords = $isEs
            ? ['compre ya', 'fórmula mágica', 'somos los mejores', 'calidad garantizada sin pruebas']
            : ['compre agora', 'fórmula mágica', 'melhor do mercado sem provas', 'conteúdo amador'];

        $tone = $isEs
            ? "Autoridad incisiva, cercana y orientada a resultados estratégicos en {$niche}."
            : "Autoridade executiva, direta e orientada a conversão e resultados em {$niche}.";

        return [
            'analyzed_at' => now()->toDateTimeString(),
            'source_url' => $websiteUrl,
            'engine' => 'heuristic:semantic',
            'unique_value_proposition' => $uvp,
            'tone_of_voice' => $tone,
            'flagship_offerings' => $flagshipOfferings,
            'target_audience_pains' => $pains,
            'content_pillars' => $pillars,
            'forbidden_words' => $forbiddenWords,
            'crawler_summary' => [
                'crawled_url' => $websiteUrl,
                'http_status' => $crawlerData['http_status'] ?? 200,
                'title' => $crawlerData['title'] ?? '',
                'meta_description' => $crawlerData['meta_description'] ?? '',
                'headings_count' => count($crawlerData['headings'] ?? []),
                'raw_preview' => mb_substr($crawlerData['text_summary'] ?? '', 0, 400),
            ],
        ];
    }
}
