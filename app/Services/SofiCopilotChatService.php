<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Setting;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SofiCopilotChatService
{
    public function __construct(
        protected SofiTranslatorService $translator
    ) {}

    /**
     * Processa mensagem do cliente na conversa e gera resposta da Sofi.
     */
    public function reply(SofiConversation $conversation, string $userMessageText): SofiMessage
    {
        // 1. Salva a mensagem do usuário
        $userMsg = SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'user',
            'content' => trim($userMessageText),
        ]);

        $cliente = $conversation->cliente;
        $production = $conversation->production;
        $locale = app()->getLocale() ?: 'pt_BR';
        $isSpanish = str_starts_with($locale, 'es');

        // 2. Monta o Prompt de Sistema com o DNA da Marca e Contexto de Produção
        $systemPrompt = $this->buildSystemPrompt($cliente, $production, $isSpanish);

        // 3. Monta histórico recente de mensagens
        $recentMessages = $conversation->messages()
            ->latest('id')
            ->take(8)
            ->get()
            ->reverse();

        // 4. Executa chamada ao Gemini ou Fallback
        $responsePayload = $this->callGemini($systemPrompt, $recentMessages, $userMessageText, $isSpanish);

        // 5. Salva resposta da Sofi
        $sofiMsg = SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
            'content' => $responsePayload['content'],
            'metadata' => $responsePayload['metadata'] ?? null,
        ]);

        // Atualiza timestamp da conversa
        $conversation->touch();

        return $sofiMsg;
    }

    /**
     * Redes onde a marca está de fato (conectadas no ecossistema ou informadas no DNA).
     * Nunca inventa um @: sem canais, a Sofi é avisada para não citar perfis.
     */
    protected function describeSocialChannels(Cliente $cliente): string
    {
        $channels = [];

        foreach ($cliente->activeSocialChannels() as $slug => $handle) {
            $label = \App\Enums\PlataformaAlvo::from($slug)->label();
            $channels[] = $handle ? "{$label} ({$handle})" : $label;
        }

        return $channels === []
            ? 'Nenhum informado (não cite @ nem perfis nos CTAs)'
            : implode('; ', $channels);
    }

    /**
     * Constrói o prompt de sistema da Sofi com contexto corporativo.
     */
    protected function buildSystemPrompt(Cliente $cliente, ?Production $production, bool $isEs): string
    {
        $lang = $isEs ? 'Español (España / Zaragoza)' : 'Português do Brasil';
        
        $brandContext = "EMPRESA: " . ($cliente->company_name ?: $cliente->name) . "\n"
                      . "NICHO: " . ($cliente->brand_niche ?: 'Serviços e Negócios Locais') . "\n"
                      . "PÚBLICO-ALVO (ICP): " . ($cliente->target_audience ?: 'Clientes qualificados no setor') . "\n"
                      . "DIFERENCIAIS: " . ($cliente->key_differentials ?: 'Qualidade, atendimento ágil e autoridade') . "\n"
                      . "WEBSITE: " . ($cliente->website ?: 'Não informado') . "\n"
                      . "CANAIS ATIVOS: " . $this->describeSocialChannels($cliente);

        $productionContext = "NENHUMA PRODUÇÃO SELECIONADA.";
        if ($production) {
            $roteiro = $production->roteiros()->latest('id')->first();
            $productionContext = "PRODUÇÃO ATUAL: {$production->title} (Status: {$production->status})\n";
            if ($roteiro) {
                $productionContext .= "ROTEIRO (Status: {$roteiro->status}):\n"
                                    . "Gancho (0-3s): " . ($roteiro->hook ?: 'N/A') . "\n"
                                    . "Corpo: " . ($roteiro->content ?: 'N/A') . "\n"
                                    . "CTA: " . ($roteiro->cta ?: 'N/A');
            }
        }

        return "Você é a SOFI, a Agente de Inteligência Artificial e Consultora Audiovisual Estratégica da NUME Creative.\n"
             . "Você conversa diretamente com o cliente corporativo dentro do Portal do Cliente.\n"
             . "Seu objetivo é ser extremamente acolhedora, empática, ágil, altamente técnica em roteiros, algoritmos de vídeo (Meta, TikTok, YouTube) e vendas B2B.\n"
             . "Você ajuda o cliente a:\n"
             . "1. Refinar seu DNA de marca e público-alvo.\n"
             . "2. Sugerir ideias de roteiros, ganchos visuais nos primeiros 3 segundos e melhorias no conteúdo dos vídeos.\n"
             . "3. Tirar dúvidas sobre o fluxo de produção, qualidade 4K HDR e prazos da produtora.\n\n"
             . "DADOS DO CLIENTE:\n{$brandContext}\n\n"
             . "CONTEXTO DE PRODUÇÃO:\n{$productionContext}\n\n"
             . "IDIOMA DE RESPOSTA OBRIGATÓRIO: {$lang}.\n"
             . "Mantenha respostas objetivas (1 a 3 parágrafos curtos) com formatação limpa e profissional.";
    }

    /**
     * Executa chamada à API Gemini com fallback inteligente.
     */
    protected function callGemini(string $systemPrompt, $messages, string $lastUserMsg, bool $isEs): array
    {
        $apiKey = config('services.gemini.key') 
            ?: Setting::get('gemini_api_key') 
            ?: env('GEMINI_API_KEY');

        if ($apiKey) {
            $primaryModel = config('services.gemini.model') 
                ?: Setting::get('gemini_model') 
                ?: env('GEMINI_MODEL', 'gemini-2.5-flash');
            $fallbackModels = config('services.gemini.fallback_models', ['gemini-2.0-flash', 'gemini-1.5-flash']);
            $candidateModels = array_unique(array_filter([$primaryModel, ...$fallbackModels]));

            $contents = [];
            // System instruction via contents ou prompt inicial
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => "[INSTRUÇÃO DE SISTEMA]\n" . $systemPrompt]]
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => $isEs ? 'Entendido. Soy Sofi, tu consultora audiovisual. ¿En qué te ayudo hoy?' : 'Entendido. Sou a Sofi, sua consultora audiovisual. Como posso te ajudar hoje?']]
            ];

            foreach ($messages as $msg) {
                $contents[] = [
                    'role' => $msg->sender === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $msg->content]]
                ];
            }

            foreach ($candidateModels as $model) {
                try {
                    $response = Http::timeout(20)->withHeaders(['x-goog-api-key' => $apiKey])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 2048,
                        ]
                    ]);

                    if ($response->successful()) {
                        $result = $response->json();
                        $output = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
                        if ($output) {
                            return [
                                'content' => trim($output),
                                'metadata' => ['source' => $model]
                            ];
                        }
                    } else {
                        Log::warning("SofiCopilotChatService Gemini API response error with model {$model}: " . $response->body());
                    }
                } catch (Exception $e) {
                    Log::warning("SofiCopilotChatService Gemini error with model {$model}: " . get_class($e));
                }
            }
        }

        // Fallback Estratégico Heurístico
        return $this->heuristicReply($lastUserMsg, $isEs);
    }

    /**
     * Resposta heurística de alta qualidade caso a API do Gemini esteja sem chave ou offline.
     */
    protected function heuristicReply(string $msg, bool $isEs): array
    {
        $lower = mb_strtolower($msg);

        if (str_contains($lower, 'roteiro') || str_contains($lower, 'guion') || str_contains($lower, 'gancho') || str_contains($lower, 'hook')) {
            $content = $isEs
                ? "¡Excelente idea! Para este tipo de vídeo, te recomiendo un gancho de patrón de interrupción en los primeros 3 segundos: empezar con una pregunta directa sobre la mayor duda de tus clientes o quebrando un mito común de tu sector. ¿Quieres que prepare una versión de guion de 50 segundos enfocada en tu público objetivo?"
                : "Excelente ponto! Para esse formato de vídeo, recomendo começarmos com um gancho de quebra de padrão nos primeiros 3 segundos: uma pergunta contra-intuitiva ou quebrando um mito clássico do seu nicho. Quer que eu estruture uma sugestão completa de 50 segundos dividida em 3 atos para você?";
        } elseif (str_contains($lower, 'status') || str_contains($lower, 'prazo') || str_contains($lower, 'entrega') || str_contains($lower, 'quando')) {
            $content = $isEs
                ? "Tu producción sigue la metodología NUME con 100% de puntualidad. En cuanto el guion esté aprobado en el portal, nuestro equipo inicia la grabación cinematográfica 4K HDR y la edición dinámica. Puedes acompañar cada fase en tu panel."
                : "Sua produção segue rigorosamente nossa esteira ágil de 4 etapas. Assim que o roteiro for aprovado com 1 clique aqui no portal, a equipe técnica inicia a captação 4K HDR e a montagem das legendas magnéticas. Você pode acompanhar o progresso em tempo real pela sua esteira!";
        } elseif (str_contains($lower, 'público') || str_contains($lower, 'audiência') || str_contains($lower, 'nicho') || str_contains($lower, 'marca')) {
            $content = $isEs
                ? "¡Perfecto! Cuanto más específico sea tu perfil de cliente ideal (ICP), más persuasivos serán los ganchos de tus vídeos. Puedes actualizar los datos en 'Perfil de Marca & Redes' en cualquier momento o decirme aquí los diferenciales que deseas destacar este mes."
                : "Perfeito! Quanto mais detalhado for o perfil do seu cliente ideal (ICP), mais magnéticos ficam os primeiros 3 segundos dos seus vídeos. Você pode ajustar isso na aba 'Perfil da Marca & Redes' ou me mandar por aqui os principais diferenciais que deseja explorar no próximo lote!";
        } else {
            $content = $isEs
                ? "¡Hola! Soy Sofi, tu consultora audiovisual estratégica en NUME. Estoy analizando el perfil de tu marca para optimizar tus guiones y plan de publicación. ¿Qué objetivo deseas acelerar con tus próximos vídeos?"
                : "Olá! Sou a Sofi, sua consultora audiovisual estratégica na NUME. Estou com o DNA da sua marca calibrado para potencializar seus roteiros e retenção no feed. Qual meta você gostaria de acelerar com seus próximos vídeos?";
        }

        return [
            'content' => $content,
            'metadata' => ['source' => 'sofi-heuristic-copilot']
        ];
    }
}
