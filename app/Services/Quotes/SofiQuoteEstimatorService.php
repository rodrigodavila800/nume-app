<?php

namespace App\Services\Quotes;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SofiQuoteEstimatorService
{
    public function __construct(
        protected QuoteCalculatorService $calculator
    ) {}

    /**
     * Processa a descrição do cliente e estima horas, filmmakers e drone.
     * Retorna array no formato: ['pedido' => [...], 'resumo' => '...', 'suposicoes' => [...], 'duvidas' => [...]]
     */
    public function estimate(string $text): array
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            return $this->buildFallbackEstimate($cleanText);
        }

        $apiKey = config('services.gemini.key')
            ?: Setting::get('gemini_api_key')
            ?: env('GEMINI_API_KEY');

        if (! empty($apiKey)) {
            $aiResult = $this->callGemini($cleanText, $apiKey);
            if ($aiResult !== null) {
                return $aiResult;
            }
        }

        return $this->buildFallbackEstimate($cleanText);
    }

    /**
     * Chamada via Google Gemini com cascade de modelos.
     */
    protected function callGemini(string $text, string $apiKey): ?array
    {
        $primaryModel = config('services.gemini.model')
            ?: Setting::get('gemini_model')
            ?: env('GEMINI_MODEL', 'gemini-3.5-flash-lite');

        $candidateModels = array_values(array_unique(array_filter([
            $primaryModel,
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-1.5-flash',
        ])));

        $prompt = <<<PROMPT
Você é a Sofi, Agente de Inteligência Artificial e Produtora Audiovisual Executiva da NUME Creative (Zaragoza).
Leia o pedido do cliente e orce o projeto de forma técnica e cinematográfica.
Responda ESTRITAMENTE com um objeto JSON válido (sem blocos de código markdown ou texto extra):

{
  "pedido": {
    "cliente": string|null,
    "projeto": string|null,
    "videos": int,
    "pre": number,
    "gravacao": number,
    "edicao": number,
    "motion": number,
    "filmmakers": int,
    "drone": bool
  },
  "resumo": string,
  "suposicoes": [string],
  "duvidas": [string]
}

Regras e padrões quando o texto não especificar expressamente:
- edicao = videos × 3
- pre = 2 (horas de pré-produção)
- gravacao = 4 (meia jornada; 8 se mencionar dia inteiro/jornada completa)
- motion = 0 (aumentar se pedir animação, VFX, 3D ou IA)
- filmmakers = 2 (1 câmera principal + 1 suporte/detalhe; se pedir grande evento/multicâmera usar 3 ou 4)
- drone = true se mencionar "aéreas", "drone", "vinha/vista do alto", "campo", caso contrário false
- cliente e projeto = extrair o nome da empresa e tipo de vídeo se aparecerem, senão null
- Textos de resumo, suposições e dúvidas em português claro e profissional.

Pedido do cliente:
"""{$text}"""
PROMPT;

        foreach ($candidateModels as $model) {
            try {
                $response = Http::timeout(12.0)
                    ->connectTimeout(3.0)
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [['text' => $prompt]],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 1200,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);

                if ($response->successful()) {
                    $raw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
                    $clean = preg_replace('/\s*```$/', '', $clean);

                    $data = json_decode($clean, true);
                    if (is_array($data) && isset($data['pedido']) && is_array($data['pedido'])) {
                        return $this->normalizeParsedData($data);
                    }
                }
            } catch (Exception $e) {
                Log::warning("SofiQuoteEstimatorService model {$model} exception: " . get_class($e));
            }
        }

        return null;
    }

    /**
     * Fallback Heurístico robusto caso a API de IA esteja sem chave ou offline.
     */
    protected function buildFallbackEstimate(string $text): array
    {
        $videos = 4;
        if (preg_match('/(\d+)\s*(?:vídeo|video|reels|shorts|tiktoks?)/ui', $text, $m)) {
            $videos = max(1, (int) $m[1]);
        }

        $drone = (bool) preg_match('/(dron|drone|aérea|aerea|vista aérea|do alto|campo|vinhedo|vinha)/ui', $text);

        $gravacao = 4.0;
        if (preg_match('/(dia todo|dia inteiro|jornada completa|full day|8\s*h)/ui', $text)) {
            $gravacao = 8.0;
        } elseif (preg_match('/(manhã|tarde|meia jornada|half day|4\s*h)/ui', $text)) {
            $gravacao = 4.0;
        }

        $motion = 0.0;
        if (preg_match('/(motion|vfx|animação|animacao|3d|inteligência artificial|ia)/ui', $text)) {
            $motion = 4.0;
        }

        $filmmakers = 2;
        if (preg_match('/(1|um)\s*(?:filmmaker|câmera|camera)/ui', $text)) {
            $filmmakers = 1;
        } elseif (preg_match('/(3|três|tres)\s*(?:filmmakers?|câmeras?)/ui', $text)) {
            $filmmakers = 3;
        }

        $cliente = null;
        if (preg_match('/(?:para a|para o|da nossa|do nosso|empresa|marca)\s+([A-ZÁÉÍÓÚÂÊÔÇ][\w\s]{2,25})/u', $text, $cm)) {
            $cliente = trim($cm[1]);
        }

        $projeto = null;
        if (preg_match('/(vídeos para redes|anúncios|institucional|cobertura|campanha)/ui', $text, $pm)) {
            $projeto = ucfirst(trim($pm[1]));
        }

        $edicao = $videos * 3.0;
        $pre = 2.0;

        $suposicoes = [
            "Estimativa de {$videos} vídeos com 3h de edição cada ({$edicao}h de pós-produção).",
            "Jornada de gravação fixada em {$gravacao}h com equipe de {$filmmakers} filmmakers.",
            $drone ? 'Inclusão de captação aérea com drone identificada no pedido.' : 'Captação exclusivamente terrestre (sem drone).',
        ];

        $duvidas = [
            'Confirmar a data e o local exato da gravação para checagem de deslocamento.',
            'Verificar se o cliente fornecerá roteiro prévio ou se desenvolveremos juntos com a Sofi.',
        ];

        return [
            'pedido' => [
                'cliente' => $cliente,
                'projeto' => $projeto ?: 'Vídeos para redes sociais',
                'videos' => $videos,
                'pre' => $pre,
                'gravacao' => $gravacao,
                'edicao' => $edicao,
                'motion' => $motion,
                'filmmakers' => $filmmakers,
                'drone' => $drone,
            ],
            'resumo' => "Produção estimada de {$videos} vídeos com {$gravacao}h de gravação e {$filmmakers} filmmakers.",
            'suposicoes' => $suposicoes,
            'duvidas' => $duvidas,
        ];
    }

    /**
     * Valida e normaliza o retorno da IA.
     */
    protected function normalizeParsedData(array $data): array
    {
        $p = $data['pedido'] ?? [];
        $videos = max(1, (int) ($p['videos'] ?? 4));

        return [
            'pedido' => [
                'cliente' => ! empty($p['cliente']) ? (string) $p['cliente'] : null,
                'projeto' => ! empty($p['projeto']) ? (string) $p['projeto'] : null,
                'videos' => $videos,
                'pre' => max(0, (float) ($p['pre'] ?? 2)),
                'gravacao' => max(0, (float) ($p['gravacao'] ?? 4)),
                'edicao' => max(0, (float) ($p['edicao'] ?? ($videos * 3))),
                'motion' => max(0, (float) ($p['motion'] ?? 0)),
                'filmmakers' => max(1, (int) ($p['filmmakers'] ?? 2)),
                'drone' => (bool) ($p['drone'] ?? false),
            ],
            'resumo' => (string) ($data['resumo'] ?? 'Estimativa calculada a partir do pedido do cliente.'),
            'suposicoes' => is_array($data['suposicoes'] ?? null) ? $data['suposicoes'] : [],
            'duvidas' => is_array($data['duvidas'] ?? null) ? $data['duvidas'] : [],
        ];
    }
}
