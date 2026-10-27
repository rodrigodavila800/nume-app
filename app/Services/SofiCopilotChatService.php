<?php

namespace App\Services;

use App\Ai\Agents\SofiSupportChatAgent;
use App\Ai\SofiProvider;
use App\Enums\PlataformaAlvo;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use Illuminate\Support\Facades\Log;

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

        // 2. Contexto do cliente (dados, não instruções) para o agente de suporte
        $context = $this->buildContext($cliente, $production);

        // 3. Histórico recente (inclui a mensagem que acabou de chegar, a última)
        $recentMessages = $conversation->messages()
            ->latest('id')
            ->take(8)
            ->get()
            ->reverse();

        // 4. Agente de suporte (SDK) ou resposta pronta
        $responsePayload = $this->callAgent($conversation, $context, $recentMessages, $userMessageText, $isSpanish);

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
            $label = PlataformaAlvo::from($slug)->label();
            $channels[] = $handle ? "{$label} ({$handle})" : $label;
        }

        return $channels === []
            ? 'Nenhum informado (não cite @ nem perfis nos CTAs)'
            : implode('; ', $channels);
    }

    /**
     * Contexto do cliente para o agente de suporte (empresa, canais e, se houver, a produção da conversa).
     */
    protected function buildContext(Cliente $cliente, ?Production $production): string
    {
        $brandContext = 'EMPRESA: '.($cliente->company_name ?: $cliente->name)."\n"
                      .'NICHO: '.($cliente->brand_niche ?: 'Serviços e Negócios Locais')."\n"
                      .'PÚBLICO-ALVO (ICP): '.($cliente->target_audience ?: 'Clientes qualificados no setor')."\n"
                      .'DIFERENCIAIS: '.($cliente->key_differentials ?: 'Qualidade, atendimento ágil e autoridade')."\n"
                      .'WEBSITE: '.($cliente->website ?: 'Não informado')."\n"
                      .'CANAIS ATIVOS: '.$this->describeSocialChannels($cliente);

        $productionContext = 'NENHUMA PRODUÇÃO SELECIONADA.';
        if ($production) {
            $roteiro = $production->roteiros()->latest('id')->first();
            $productionContext = "PRODUÇÃO ATUAL: {$production->title} (Status: {$production->status})";
            if ($roteiro) {
                $productionContext .= "\nROTEIRO: status {$roteiro->status}";
            }
        }

        return "DADOS DO CLIENTE:\n{$brandContext}\n\nCONTEXTO DE PRODUÇÃO:\n{$productionContext}";
    }

    /**
     * Agente de suporte (Laravel AI SDK, uso registrado por tenant e cliente). Sem provedor, acima do
     * limite ou em falha: resposta pronta.
     */
    protected function callAgent(SofiConversation $conversation, string $context, $messages, string $lastUserMsg, bool $isEs): array
    {
        $sofiProvider = app(SofiProvider::class);
        $cliente = $conversation->cliente;

        try {
            $options = $sofiProvider->options($cliente->tenant, $cliente->id);

            if ($options !== null) {
                // O histórico vai por messages(); a mensagem atual é o prompt.
                $history = $messages->values()->slice(0, -1)
                    ->map(fn ($m) => ['sender' => $m->sender, 'content' => (string) $m->content])
                    ->values()->all();

                $agent = new SofiSupportChatAgent($cliente->tenant_id, $cliente->id, $context, $isEs, $history);
                $output = trim((string) $agent->prompt($lastUserMsg, ...$options));

                if ($output !== '') {
                    return ['content' => $output, 'metadata' => ['source' => 'sdk:'.$sofiProvider->driver()]];
                }
            }
        } catch (\Throwable $e) {
            // Só o tipo da exceção: a mensagem pode conter URL ou credenciais.
            Log::warning('SofiCopilotChatService AI SDK falhou ('.$sofiProvider->driver().'): '.get_class($e));
        }

        return $this->heuristicReply($lastUserMsg, $isEs);
    }

    /**
     * Resposta heurística de alta qualidade caso a API do Gemini esteja sem chave ou offline.
     */
    protected function heuristicReply(string $msg, bool $isEs): array
    {
        $lower = mb_strtolower($msg);

        if (str_contains($lower, 'roteiro') || str_contains($lower, 'guion') || str_contains($lower, 'gancho') || str_contains($lower, 'hook')) {
            // Suporte não escreve roteiro: isso nasce do plano de marketing aprovado.
            $content = $isEs
                ? 'Los guiones los prepara el equipo a partir de tu plan de marketing aprobado. Si quieres cambiar algo de un guion, pide un ajuste al revisarlo en el portal o abre una incidencia y el equipo te ayuda.'
                : 'Os roteiros são preparados pela equipe a partir do seu plano de marketing aprovado. Se quiser mudar algo em um roteiro, peça ajuste ao revisá-lo aqui no portal ou abra um chamado que a equipe te ajuda.';
        } elseif (str_contains($lower, 'fatura') || str_contains($lower, 'factura') || str_contains($lower, 'pagamento') || str_contains($lower, 'pago')) {
            $content = $isEs
                ? "Tus facturas están en Presupuestos, junto a cada propuesta pagada, y tus datos de facturación en 'Datos de facturación'. Si algo no cuadra, abre una incidencia de Facturación y el equipo lo revisa."
                : "Suas faturas ficam em Orçamentos, ao lado de cada proposta paga, e os dados de faturação em 'Dados de faturação'. Se algo não bater, abra um chamado de Financeiro que a equipe confere.";
        } elseif (str_contains($lower, 'status') || str_contains($lower, 'prazo') || str_contains($lower, 'entrega') || str_contains($lower, 'quando')) {
            $content = $isEs
                ? 'Tu producción sigue la metodología NUME con 100% de puntualidad. En cuanto el guion esté aprobado en el portal, nuestro equipo inicia la grabación cinematográfica 4K HDR y la edición dinámica. Puedes acompañar cada fase en tu panel.'
                : 'Sua produção segue rigorosamente nossa esteira ágil de 4 etapas. Assim que o roteiro for aprovado com 1 clique aqui no portal, a equipe técnica inicia a captação 4K HDR e a montagem das legendas magnéticas. Você pode acompanhar o progresso em tempo real pela sua esteira!';
        } elseif (str_contains($lower, 'público') || str_contains($lower, 'audiência') || str_contains($lower, 'nicho') || str_contains($lower, 'marca')) {
            $content = $isEs
                ? "¡Perfecto! Cuanto más específico sea tu perfil de cliente ideal (ICP), más persuasivos serán los ganchos de tus vídeos. Puedes actualizar los datos en 'Perfil de Marca & Redes' en cualquier momento o decirme aquí los diferenciales que deseas destacar este mes."
                : "Perfeito! Quanto mais detalhado for o perfil do seu cliente ideal (ICP), mais magnéticos ficam os primeiros 3 segundos dos seus vídeos. Você pode ajustar isso na aba 'Perfil da Marca & Redes' ou me mandar por aqui os principais diferenciais que deseja explorar no próximo lote!";
        } else {
            $content = $isEs
                ? "¡Hola! Soy Sofi, del soporte de NUME. Puedo ayudarte con el portal, tus producciones, presupuestos, facturas y redes. Si necesitas al equipo, pulsa 'Abrir incidencia'."
                : "Olá! Sou a Sofi, do suporte da NUME. Posso ajudar com o portal, suas produções, orçamentos, faturas e redes. Se precisar da equipe, use o botão 'Abrir chamado'.";
        }

        return [
            'content' => $content,
            'metadata' => ['source' => 'sofi-heuristic-support'],
        ];
    }
}
