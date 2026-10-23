<?php

namespace App\Services;

use App\Ai\Agents\SofiTranslatorAgent;
use App\Ai\SofiProvider;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SofiTranslatorService
{
    /**
     * Glossário de termos preservados e vocabulário chave da NUME.
     */
    protected array $termGlossary = [
        'NUME' => 'NUME',
        'NUME Creative' => 'NUME Creative',
        'Sofi IA' => 'Sofi IA',
        'Sofi Copilot' => 'Sofi Copilot',
        'Sofi AI Copilot' => 'Sofi AI Copilot',
        'Bizum' => 'Bizum',
        'Redsys' => 'Redsys',
        'Stripe' => 'Stripe',
        'AEAT' => 'AEAT',
        'IVA 21%' => 'IVA 21%',
        '4K HDR' => '4K HDR',
        'Reels' => 'Reels',
        'Shorts' => 'Shorts',
        'VSL' => 'VSL',
        'B2B' => 'B2B',
    ];

    /**
     * Traduz uma string de texto entre pt_BR e es (ou outros idiomas suportados).
     *
     * @param  string  $text  Texto original a ser traduzido
     * @param  string  $to  Idioma destino ('es' ou 'pt_BR')
     * @param  string|null  $from  Idioma de origem (default autodetect ou 'pt_BR')
     * @return string Texto traduzido
     */
    public function translate(string $text, string $to = 'es', ?string $from = 'pt_BR'): string
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            return '';
        }

        // Se o destino for o mesmo da origem, retorna o próprio texto
        $targetLocale = $this->normalizeLocale($to);
        $sourceLocale = $from ? $this->normalizeLocale($from) : 'pt_BR';

        if ($targetLocale === $sourceLocale) {
            return $cleanText;
        }

        // 1. Tenta encontrar no dicionário estático local (Custo zero, 0ms)
        $localDictionaryMatch = $this->lookupLocalDictionary($cleanText, $targetLocale, $sourceLocale);
        if ($localDictionaryMatch !== null) {
            return $localDictionaryMatch;
        }

        // 2. Tenta recuperar do Cache persistente (chave por MD5)
        $cacheKey = 'sofi_trans_'.$sourceLocale.'_'.$targetLocale.'_'.md5($cleanText);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 3. Executa tradução com motor de IA Sofi (Gemini) se chave disponível
        $translated = $this->translateViaAi($cleanText, $targetLocale, $sourceLocale);

        if ($translated !== null && trim($translated) !== '') {
            $finalText = trim($translated);
            // Armazena no cache por 60 dias
            Cache::put($cacheKey, $finalText, now()->addDays(60));

            return $finalText;
        }

        // 4. Fallback léxico inteligente se IA não estiver configurada ou falhar
        $fallbackText = $this->lexicalFallback($cleanText, $targetLocale, $sourceLocale);
        Cache::put($cacheKey, $fallbackText, now()->addDays(7));

        return $fallbackText;
    }

    /**
     * Traduz um lote (batch) de textos em uma única operação.
     *
     * @param  array  $texts  Array de strings
     * @param  string  $to  Idioma destino
     * @param  string|null  $from  Idioma origem
     * @return array Array com textos traduzidos mantendo as chaves originais
     */
    public function translateBatch(array $texts, string $to = 'es', ?string $from = 'pt_BR'): array
    {
        $results = [];
        foreach ($texts as $key => $text) {
            if (is_string($text)) {
                $results[$key] = $this->translate($text, $to, $from);
            } elseif (is_array($text)) {
                $results[$key] = $this->translateBatch($text, $to, $from);
            } else {
                $results[$key] = $text;
            }
        }

        return $results;
    }

    /**
     * Consulta os arquivos json locais de lang/.
     */
    protected function lookupLocalDictionary(string $text, string $to, string $from): ?string
    {
        $file = base_path("lang/{$to}.json");
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
            if (isset($data[$text]) && $data[$text] !== '') {
                return $data[$text];
            }
        }

        // Se estiver traduzindo do espanhol para o português: busca reversa
        if ($to === 'pt_BR' && $from === 'es') {
            $esFile = base_path('lang/es.json');
            if (file_exists($esFile)) {
                $esData = json_decode(file_get_contents($esFile), true) ?: [];
                $foundKey = array_search($text, $esData, true);
                if ($foundKey !== false) {
                    return (string) $foundKey;
                }
            }
        }

        return null;
    }

    /**
     * Tradução via API Google Gemini (IA Sofi).
     */
    protected function translateViaAi(string $text, string $to, string $from): ?string
    {
        // Piloto Laravel AI SDK (desligado por padrão: SOFI_USE_AI_SDK=false)
        if (config('sofi.use_ai_sdk')) {
            return $this->translateViaSdk($text, $to, $from);
        }

        if (Cache::get('gemini_trans_circuit_breaker')) {
            return null;
        }

        $apiKey = config('services.gemini.key')
            ?: Setting::get('gemini_api_key')
            ?: env('GEMINI_API_KEY');

        if (! $apiKey) {
            return null;
        }

        $primaryModel = config('services.gemini.model')
            ?: Setting::get('gemini_model')
            ?: env('GEMINI_MODEL', 'gemini-3.5-flash-lite');
        $fallbackModels = config('services.gemini.fallback_models', ['gemini-3.8-flash', 'gemini-1.5-flash']);
        $candidateModels = array_unique(array_filter([$primaryModel, ...$fallbackModels]));

        $targetName = $to === 'es' ? 'Español (España / Zaragoza)' : 'Português do Brasil';
        $sourceName = $from === 'es' ? 'Español' : 'Português';

        $prompt = "Você é a Sofi, Agente de IA especialista em audiovisual, copywriting e marketing B2B para NUME Creative.\n"
                ."Traduza o seguinte texto de {$sourceName} para {$targetName}.\n"
                ."Diretrizes obrigatórias:\n"
                ."- Mantenha tom profissional, comercial, persuasivo e natural para o mercado corporativo espanhol/brasileiro.\n"
                ."- Mantenha termos técnicos (Reels, Shorts, 4K HDR, VSL, Bizum, Redsys, Stripe, IVA 21%, AEAT, Sofi IA).\n"
                ."- Retorne EXCLUSIVAMENTE o texto traduzido final, sem aspas adicionais, sem preâmbulos e sem explicações.\n\n"
                ."Texto:\n{$text}";

        foreach ($candidateModels as $model) {
            try {
                $response = Http::timeout(3.0)
                    ->connectTimeout(1.5)
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 1024,
                        ],
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $output = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($output) {
                        return trim($output);
                    }
                } else {
                    if ($response->status() === 429 || $response->status() === 503) {
                        Cache::put('gemini_trans_circuit_breaker', true, now()->addSeconds(60));
                        Log::warning("SofiTranslatorService Gemini Rate Limit ({$response->status()}). Circuit breaker ativado por 60s.");

                        return null;
                    }
                    Log::warning("SofiTranslatorService Gemini API Error with model {$model}: ".$response->body());
                }
            } catch (Exception $e) {
                Log::warning("SofiTranslatorService Gemini exception with model {$model}: ".get_class($e));
            }
        }

        return null;
    }

    /**
     * Tradução via Laravel AI SDK (piloto). Qualquer falha devolve null e o
     * fluxo cai no fallback léxico, como na chamada HTTP antiga.
     */
    protected function translateViaSdk(string $text, string $to, string $from): ?string
    {
        $sofiProvider = app(SofiProvider::class);
        $provider = $sofiProvider->driver();

        $targetName = $to === 'es' ? 'Español (España / Zaragoza)' : 'Português do Brasil';
        $sourceName = $from === 'es' ? 'Español' : 'Português';

        try {
            $options = $sofiProvider->options();

            if ($options === null) {
                return null;
            }

            $response = (new SofiTranslatorAgent($sourceName, $targetName))
                ->prompt($text, ...$options);

            $output = trim((string) $response);

            return $output !== '' ? $output : null;
        } catch (\Throwable $e) {
            // Só o tipo da exceção: a mensagem pode conter URL ou credenciais.
            Log::warning('SofiTranslatorService AI SDK falhou ('.$provider.'): '.get_class($e));

            return null;
        }
    }

    /**
     * Fallback léxico em caso de indisponibilidade de rede ou chave de API externa.
     */
    protected function lexicalFallback(string $text, string $to, string $from): string
    {
        if ($to === 'es') {
            $replacements = [
                'vídeos' => 'vídeos',
                'vídeo' => 'vídeo',
                'produção' => 'producción',
                'produções' => 'producciones',
                'estratégico' => 'estratégico',
                'estratégica' => 'estratégica',
                'roteiro' => 'guion',
                'roteiros' => 'guiones',
                'marca' => 'marca',
                'marcas' => 'marcas',
                'conversão' => 'conversión',
                'retenção' => 'retención',
                'pacote' => 'paquete',
                'pacotes' => 'paquetes',
                'gravação' => 'grabación',
                'entregáveis' => 'entregables',
                'inclusos' => 'incluidos',
                'selecionados' => 'seleccionados',
                'opcionais' => 'opcionales',
                'desde' => 'desde',
                'contratar' => 'contratar',
                'faturamento' => 'facturación',
                'relatório' => 'informe',
                'painel' => 'panel',
            ];
        } else {
            $replacements = [
                'vídeos' => 'vídeos',
                'vídeo' => 'vídeo',
                'producción' => 'produção',
                'producciones' => 'produções',
                'estratégico' => 'estratégico',
                'estratégica' => 'estratégica',
                'guion' => 'roteiro',
                'guiones' => 'roteiros',
                'conversión' => 'conversão',
                'retención' => 'retenção',
                'paquete' => 'pacote',
                'paquetes' => 'pacotes',
                'grabación' => 'gravação',
                'entregables' => 'entregáveis',
                'incluidos' => 'inclusos',
                'seleccionados' => 'selecionados',
                'opcionales' => 'opcionais',
                'facturación' => 'faturamento',
                'informe' => 'relatório',
                'panel' => 'painel',
            ];
        }

        return str_ireplace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Normaliza locale para pt_BR ou es.
     */
    protected function normalizeLocale(string $locale): string
    {
        $normalized = str_replace('-', '_', strtolower(trim($locale)));
        if (str_starts_with($normalized, 'es')) {
            return 'es';
        }

        return 'pt_BR';
    }
}
