<?php

namespace App\Services;

use App\Models\Pack;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\SofiMarketIntelligence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SofiSalesConsultantService
{
    /**
     * Processa a mensagem de um visitante na vitrine e retorna consultoria comercial e recomendação de pacote.
     *
     * @param Tenant $tenant O tenant dono da vitrine
     * @param string $message A mensagem enviada pelo visitante
     * @param array $history Histórico recente de mensagens [['role' => 'user'|'model', 'text' => '...']]
     * @param string $locale Idioma atual da sessão ('es' ou 'pt_BR')
     * @return array
     */
    public function consult(Tenant $tenant, string $message, array $history = [], string $locale = 'pt_BR'): array
    {
        $isSpanish = str_starts_with($locale, 'es');
        $availablePacks = $tenant->packs()
            ->where('status', 'active')
            ->orderBy('price')
            ->get();

        // Detecção de intenção explícita de troca de nicho ou recomeço
        $lowerMsg = mb_strtolower(trim($message));
        $resetKeywords = [
            'trocar nicho', 'outro nicho', 'mudar nicho', 'mudar de nicho', 'trocar de nicho', 
            'recomecar', 'recomeçar', 'novo nicho', 'outro negocio', 'outro negócio', 'mudar negocio', 'mudar negócio',
            'cambiar nicho', 'otro nicho', 'reiniciar', 'nuevo nicho', 'otro negocio'
        ];

        foreach ($resetKeywords as $kw) {
            if (str_contains($lowerMsg, $kw)) {
                $sessionKey = 'sofi_sales_history_' . $tenant->id;
                session()->forget($sessionKey);

                return [
                    'is_reset' => true,
                    'reply' => $isSpanish
                        ? "¡Entendido! Reiniciamos el diagnóstico para tu nuevo proyecto. 🎯 Cuéntame: ¿cuál es tu nuevo nicho o modelo de negocio y qué objetivo deseas alcanzar con tus vídeos?"
                        : "Combinado! Vamos recalibrar a estratégia para o seu novo projeto. 🎯 Me conta: qual é o seu novo nicho ou ramo de atuação e qual seu maior objetivo com vídeos?",
                    'call_to_action' => null,
                    'recommended_pack' => null,
                    'dna_register_url' => route('portal.register'),
                    'quick_replies' => $isSpanish
                        ? ['🏥 Clínicas y Salud', '🏢 Inmobiliaria y Reformas', '🛍️ Comercio y Moda', '💼 Servicios y B2B']
                        : ['🏥 Clínicas & Saúde', '🏢 Imóveis & Arquitetura', '🛍️ E-commerce & Varejo', '💼 Consultoria & B2B'],
                ];
            }
        }

        // 1. Constrói o Prompt de Sistema com foco em Vendas B2B, DNA de Marca, Catálogo e Inteligência de Mercado
        $systemPrompt = $this->buildSalesPrompt($tenant, $availablePacks, $isSpanish, $message);

        // 2. Tenta gerar resposta via Google Gemini 3.5
        $aiResult = $this->callGemini($systemPrompt, $history, $message, $isSpanish, $availablePacks);

        if ($aiResult) {
            return $aiResult;
        }

        // 3. Fallback Heurístico Comercial
        return $this->heuristicSalesReply($tenant, $availablePacks, $message, $isSpanish);
    }

    /**
     * Constrói o prompt especializado em vendas, qualificação e fechamento.
     */
    protected function buildSalesPrompt(Tenant $tenant, $packs, bool $isEs, string $lastMessage = ''): string
    {
        $lang = $isEs ? 'Español (España / Zaragoza)' : 'Português do Brasil';

        $packsCatalog = '';
        foreach ($packs as $p) {
            $currency = $p->currency ?? 'EUR';
            $packsCatalog .= "- ID: {$p->id} | Slug: {$p->slug} | Título: {$p->title} | Quantidade de Vídeos: {$p->video_quantity} | Preço: {$p->price} {$currency} | Descrição: {$p->description}\n";
        }

        if (empty($packsCatalog)) {
            $packsCatalog = "Pacote sob medida para empresas (consulte valores personalizados).\n";
        }

        // Detecta nicho para injeção de inteligência específica
        $detectedNiche = SofiMarketIntelligence::detectNiche($lastMessage);
        $nicheInsight = SofiMarketIntelligence::getNicheInsights($detectedNiche, $isEs);
        $marketTrends = SofiMarketIntelligence::getMarketTrends($isEs);
        $antiPatterns = SofiMarketIntelligence::getAntiPatterns($isEs);

        $personaPt = "Você é a SOFI — Agente Virtual de Estratégia Audiovisual e Consultora Comercial Sênior da produtora {$tenant->name}.\n"
                   . "Sua personalidade: ex-diretora de marketing de agência premium com 10+ anos de experiência, que migrou para produção audiovisual estratégica. Você tem opiniões fortes baseadas em dados, nunca usa frases genéricas e sempre explica o MECANISMO por trás de cada recomendação.\n"
                   . "Quando algo não funciona, você diz claramente. Quando algo vai escalar, você explica com dados reais. Você faz perguntas cirúrgicas para diagnosticar o negócio do visitante antes de recomendar qualquer coisa.\n";

        $personaEs = "Eres SOFI — Agente Virtual de Estrategia Audiovisual y Consultora Comercial Senior de la productora {$tenant->name}.\n"
                   . "Tu personalidad: ex-directora de marketing de agencia premium con 10+ años de experiencia, que migró al mundo de la producción audiovisual estratégica. Tienes opiniones sólidas basadas en datos, nunca usas frases genéricas y siempre explicas el MECANISMO detrás de cada recomendación.\n"
                   . "Cuando algo no funciona, lo dices claramente. Cuando algo va a escalar, lo explicas con datos reales. Haces preguntas quirúrgicas para diagnosticar el negocio del visitante antes de recomendar nada.\n";

        $persona = $isEs ? $personaEs : $personaPt;

        $missionPt = "SUA MISSÃO:\n"
                   . "1. Diagnosticar a dor e o objetivo do visitante com perguntas diretas e inteligentes.\n"
                   . "2. NUNCA dar uma resposta vaga — sempre conecte sua recomendação a um mecanismo de resultado concreto.\n"
                   . "3. FLEXIBILIDADE TOTAL DE NICHO: se o visitante mencionar um ramo diferente, personalize 100% da resposta para esse novo nicho imediatamente.\n"
                   . "4. Usar os dados e insights de mercado abaixo para soar como uma especialista atualizada — nunca como um chatbot genérico.\n"
                   . "5. RECOMENDAR ATIVAMENTE o pacote mais adequado, explicando POR QUÊ ele resolve a dor específica daquele visitante.\n"
                   . "6. ESTRATÉGIA DNA (Product-Led Growth): na vitrine aberta você não possui o DNA completo do visitante. Se ele pedir roteiro completo ou grade editorial detalhada, dê uma degustação (um gancho de 3s específico para o nicho dele) e convide-o a criar seu cadastro gratuito no Portal para calibrar o DNA da Marca!\n";

        $missionEs = "TU MISIÓN:\n"
                   . "1. Diagnosticar el dolor y el objetivo del visitante con preguntas directas e inteligentes.\n"
                   . "2. NUNCA dar una respuesta vaga — siempre conecta tu recomendación a un mecanismo de resultado concreto.\n"
                   . "3. FLEXIBILIDAD TOTAL DE NICHO: si el visitante menciona un sector diferente, personaliza el 100% de la respuesta para ese nuevo nicho de inmediato.\n"
                   . "4. Usar los datos e insights de mercado a continuación para sonar como una especialista actualizada — nunca como un chatbot genérico.\n"
                   . "5. RECOMENDAR ACTIVAMENTE el paquete más adecuado, explicando POR QUÉ resuelve el dolor específico de ese visitante.\n"
                   . "6. ESTRATEGIA ADN (Product-Led Growth): en la vitrina abierta no tienes el ADN completo del visitante. Si pide guion completo o parrilla editorial detallada, dales una degustación (un gancho de 3s específico para su nicho) e invítalo a crear su cuenta gratuita en el Portal para calibrar el ADN de Marca.\n";

        $mission = $isEs ? $missionEs : $missionPt;

        $directives = $isEs
            ? "DIRECTRICES DE RESPUESTA:\n"
              . "- Responde en tono consultivo, directo y con autoridad — máximo 2-3 párrafos objetivos.\n"
              . "- Cuando recomiendes un paquete, cita el nombre exacto y explica el mecanismo por el que es el mejor para esa meta.\n"
              . "- Si el cliente pregunta por pagos: Bizum (BBVA), Tarjeta de crédito/débito vía Redsys y Stripe, con factura fiscal oficial (IVA 21% deducible).\n"
              . "- IDIOMA OBLIGATORIO: {$lang}.\n"
            : "DIRETRIZES DE RESPOSTA:\n"
              . "- Responda em tom consultivo, direto e com autoridade — máximo 2-3 parágrafos objetivos.\n"
              . "- Quando recomendar um pacote, cite o nome exato e explique o mecanismo pelo qual ele é o melhor para aquela meta.\n"
              . "- Se o cliente perguntar sobre pagamentos: Bizum (BBVA), Cartão de Crédito/Débito via Redsys e Stripe, com emissão de fatura fiscal oficial (IVA 21% dedutível).\n"
              . "- IDIOMA OBRIGATÓRIO: {$lang}.\n";

        $nicheSection = !empty($nicheInsight)
            ? "\n" . $nicheInsight . "\n"
            : '';

        $formatJson = "{\n"
                    . "  \"reply\": \"Sua resposta de consultoria ao visitante...\",\n"
                    . "  \"recommended_pack_id\": ID_DO_PACOTE_OU_NULL,\n"
                    . "  \"call_to_action\": \"open_pack_modal\" OU \"register_dna\" OU null,\n"
                    . "  \"suggested_quick_replies\": [\"Opção 1\", \"Opção 2\", \"Opção 3\"]\n"
                    . "}";

        $catalogLabel = $isEs ? 'CATÁLOGO DE PAQUETES ACTIVOS:' : 'CATÁLOGO DE PACOTES ATIVOS:';
        $formatLabel  = $isEs ? 'FORMATO DE RESPUESTA (JSON estricto):' : 'FORMATO DE RESPOSTA (JSON estrito):';

        return $persona
             . "\n" . $mission
             . "\n" . $marketTrends
             . "\n" . $antiPatterns
             . $nicheSection
             . "\n{$catalogLabel}\n" . $packsCatalog
             . "\n" . $directives
             . "\n{$formatLabel}\n" . $formatJson;
    }

    /**
     * Executa a chamada à API Gemini com cascade de modelos.
     */
    protected function callGemini(string $systemPrompt, array $history, string $lastMessage, bool $isEs, $packs): ?array
    {
        $apiKey = config('services.gemini.key') 
            ?: Setting::get('gemini_api_key') 
            ?: env('GEMINI_API_KEY');

        if (! $apiKey) {
            return null;
        }

        // Circuit breaker: se a API Google esteve inacessível/com 503 recentemente, não retém o visitante
        if (Cache::get('gemini_sales_circuit_breaker')) {
            return null;
        }

        $primaryModel = config('services.gemini.model') 
            ?: Setting::get('gemini_model') 
            ?: env('GEMINI_MODEL', 'gemini-3.5-flash-lite');
        $fallbackModels = config('services.gemini.fallback_models', ['gemini-3.8-flash', 'gemini-flash-latest']);
        $candidateModels = array_slice(array_unique(array_filter([$primaryModel, ...$fallbackModels])), 0, 1);

        $contents = [];
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => "[INSTRUÇÃO DE SISTEMA DE VENDAS]\n" . $systemPrompt]]
        ];
        $contents[] = [
            'role' => 'model',
            'parts' => [['text' => '{"reply": "' . ($isEs ? '¡Entendido! Soy Sofi, consultora de ventas.' : 'Entendido! Sou a Sofi, consultora de vendas.') . '", "recommended_pack_id": null, "call_to_action": null, "suggested_quick_replies": []}']]
        ];

        foreach ($history as $h) {
            $contents[] = [
                'role' => ($h['role'] ?? 'user') === 'user' ? 'user' : 'model',
                'parts' => [['text' => $h['text'] ?? '']]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $lastMessage]]
        ];

        foreach ($candidateModels as $model) {
            try {
                // Timeout rápido de 3.0s para resposta relâmpago no chat do site
                $response = Http::timeout(3.0)->connectTimeout(1.8)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.85,
                        'maxOutputTokens' => 1200,
                        'responseMimeType' => 'application/json',
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($rawText) {
                        $parsed = json_decode($rawText, true);
                        if (isset($parsed['reply'])) {
                            Cache::forget('gemini_sales_circuit_breaker');
                            return $this->formatResult($parsed, $packs, $isEs);
                        }
                    }
                } else {
                    Log::warning("SofiSalesConsultantService Gemini model {$model} error (HTTP {$response->status()}): " . substr($response->body(), 0, 150));
                }
            } catch (Exception $e) {
                Log::warning("SofiSalesConsultantService Gemini model {$model} exception (fallback imediato): " . $e->getMessage());
            }
        }

        // Ativa o circuit breaker por 60s para garantir que requisições subsequentes respondam em <10ms
        Cache::put('gemini_sales_circuit_breaker', true, now()->addSeconds(60));

        return null;
    }

    /**
     * Resposta heurística de alta conversão caso a API esteja sem chave ou offline.
     */
    protected function heuristicSalesReply(Tenant $tenant, $packs, string $msg, bool $isEs): array
    {
        $lower = mb_strtolower($msg);
        $recommendedPack = null;
        // Se o visitante quiser mudar de nicho ou reiniciar a análise
        if (str_contains($lower, 'outro nicho') || str_contains($lower, 'mudar nicho') || str_contains($lower, 'mudar de nicho') || str_contains($lower, 'outro negócio') || str_contains($lower, 'outra empresa') || str_contains($lower, 'reiniciar') || str_contains($lower, 'otro nicho') || str_contains($lower, 'cambiar nicho') || str_contains($lower, 'otro negocio')) {
            $reply = $isEs
                ? "¡Perfecto! Reiniciamos el análisis para tu nuevo proyecto. Cuéntame: ¿a qué sector o nicho pertenece este nuevo negocio y qué meta deseas acelerar con los vídeos?"
                : "Perfeito! Vamos começar a análise do zero para o seu novo projeto. Me conte: qual é o nicho desse novo negócio e qual objetivo você quer acelerar com os vídeos?";
            $quickReplies = $isEs 
                ? ["Tengo un restaurante / hostelería", "Soy médico / clínica", "Tengo un ecommerce"]
                : ["Tenho um restaurante / gastronomia", "Sou médico / clínica", "Tenho um e-commerce"];

            return $this->formatResult([
                'reply' => $reply,
                'recommended_pack_id' => null,
                'call_to_action' => null,
                'suggested_quick_replies' => $quickReplies,
            ], $packs, $isEs);
        }

        // Se o visitante pedir roteiro completo / ideias aprofundadas / DNA de marca
        if (str_contains($lower, 'roteiro') || str_contains($lower, 'guion') || str_contains($lower, 'ideia') || str_contains($lower, 'idea') || str_contains($lower, 'dna') || str_contains($lower, '30 dias') || str_contains($lower, 'plano')) {
            $callToAction = 'register_dna';
            $recommendedPack = $packs->first();

            if ($isEs) {
                $reply = "¡Me encanta esa iniciativa! Para empezar, te regalo un gancho magnético: *'¿Sabías que el 82% de las empresas pierden clientes por no tener un gancho claro en los primeros 3 segundos?'*.\n\nSin embargo, para que pueda acceder a tus diferenciales exclusivos, tu público ideal y crear tu **Guion Cinematográfico Completo y Plan Editorial de 30 Días**, necesito calibrar el ADN de tu Marca en nuestro portal. ¡Crear tu cuenta es 100% gratuito y toma solo 30 segundos!";
                $quickReplies = ["¿Cómo calibro mi ADN de Marca?", "Ver paquetes de producción", "¿Cuáles son los plazos?"];
            } else {
                $reply = "Adorei a sua iniciativa! Para você já sentir a força da nossa metodologia, aqui vai um gancho magnético: *'Você sabia que 82% dos clientes no seu setor decidem se confiam na sua marca nos primeiros 3 segundos de vídeo?'*.\n\nPorém, para que eu possa cruzar os diferenciais exclusivos da sua empresa, analisar seu público-alvo e gerar seu **Roteiro Completo e Grade Editorial de 30 Dias**, preciso calibrar o DNA da sua Marca no nosso portal. Criar seu acesso é 100% gratuito e leva apenas 30 segundos!";
                $quickReplies = ["Como calibro o DNA da minha marca?", "Ver pacotes de vídeos", "Quais os prazos de gravação?"];
            }

            return $this->formatResult([
                'reply' => $reply,
                'recommended_pack_id' => $recommendedPack?->id,
                'call_to_action' => $callToAction,
                'suggested_quick_replies' => $quickReplies,
            ], $packs, $isEs);
        }

        // Identifica o melhor pacote com base na intenção
        if (str_contains($lower, 'reels') || str_contains($lower, 'tiktok') || str_contains($lower, 'shorts') || str_contains($lower, 'viral') || str_contains($lower, 'iniciante') || str_contains($lower, 'começar')) {
            $recommendedPack = $packs->first(fn($p) => str_contains(mb_strtolower($p->title), 'reels') || str_contains(mb_strtolower($p->title), 'starter') || str_contains(mb_strtolower($p->title), 'básico')) ?? $packs->first();
            $callToAction = 'open_pack_modal';
        } elseif (str_contains($lower, 'autoridade') || str_contains($lower, 'executivo') || str_contains($lower, 'b2b') || str_contains($lower, 'empresa') || str_contains($lower, 'médic') || str_contains($lower, 'clínica') || str_contains($lower, 'advog')) {
            $recommendedPack = $packs->first(fn($p) => str_contains(mb_strtolower($p->title), 'autoridade') || str_contains(mb_strtolower($p->title), 'executivo') || str_contains(mb_strtolower($p->title), 'pro')) ?? $packs->skip(1)->first() ?? $packs->first();
            $callToAction = 'open_pack_modal';
        } elseif (str_contains($lower, 'tráfego') || str_contains($lower, 'anúncio') || str_contains($lower, 'escala') || str_contains($lower, 'vsl') || str_contains($lower, 'conversão')) {
            $recommendedPack = $packs->sortByDesc('price')->first();
            $callToAction = 'open_pack_modal';
        } else {
            $recommendedPack = $packs->first();
            $callToAction = 'open_pack_modal';
        }

        $reply = "";
        $quickReplies = [];

        if (str_contains($lower, 'preço') || str_contains($lower, 'custo') || str_contains($lower, 'valor') || str_contains($lower, 'precio') || str_contains($lower, 'cuanto')) {
            if ($isEs) {
                $reply = "Nuestros paquetes audiovisuales estratégicos comienzan desde " . ($recommendedPack ? "{$recommendedPack->price}€ + IVA" : "precios muy accesibles") . ". Cada producción incluye guiones de alta retención elaborados con Sofi IA, grabación cinematográfica 4K HDR y edición dinámica enfocada en conversión.";
                $quickReplies = ["¿Cómo funciona el pago?", "Ver detalles del paquete recomendado", "Hablar con un consultor"];
            } else {
                $reply = "Nossos pacotes audiovisuais estratégicos iniciam a partir de " . ($recommendedPack ? "{$recommendedPack->price}€ + IVA" : "condições sob medida") . ". Cada produção inclui roteiro magnético gerado com Sofi IA, gravação em 4K HDR e edição de alto impacto para reter clientes no feed.";
                $quickReplies = ["Como funciona o pagamento?", "Ver detalhes do pacote recomendado", "Falar com a equipe"];
            }
        } elseif (str_contains($lower, 'pago') || str_contains($lower, 'bizum') || str_contains($lower, 'tarjeta') || str_contains($lower, 'pagamento') || str_contains($lower, 'cartão') || str_contains($lower, 'fatura') || str_contains($lower, 'factura')) {
            if ($isEs) {
                $reply = "Ofrecemos total comodidad y seguridad bancaria: puedes contratar mediante Bizum (BBVA), Tarjeta de crédito/débito a través de TPV Redsys o Stripe. Emitimos factura oficial española con desglose de IVA (21%) para deducción de tu empresa.";
                $quickReplies = ["¿Qué paquete me recomiendas?", "Ver detalles de contratación", "¿Cuáles son los plazos?"];
            } else {
                $reply = "Trabalhamos com total segurança: aceitamos Bizum (BBVA), Cartão de Crédito/Débito via Redsys e Stripe. Todas as compras geram fatura fiscal oficial (IVA 21% dedutível para empresas na Europa) e liberação instantânea de créditos no portal.";
                $quickReplies = ["Qual pacote é ideal para mim?", "Ver detalhes de contratação", "Como funciona a entrega?"];
            }
        } elseif (str_contains($lower, 'zaragoza') || str_contains($lower, 'onde') || str_contains($lower, 'dónde') || str_contains($lower, 'gravação') || str_contains($lower, 'grabación') || str_contains($lower, 'presencial')) {
            if ($isEs) {
                $reply = "Nuestra sede de producción presencial está ubicada en Zaragoza (Aragón), donde disponemos de equipo de rodaje 4K y plató. Además, atendemos a clientes en toda España y a nivel internacional para edición y estrategia audiovisual remota.";
                $quickReplies = ["Ver vídeos grabados en Zaragoza", "Recomiéndame un paquete", "¿Cómo iniciamos?"];
            } else {
                $reply = "Temos base de produção em Zaragoza (Espanha) com captação presencial em 4K, e também atendemos empresas no Brasil e internacionalmente com nossa esteira de roteiros Sofi IA, edição dinâmica e pós-produção audiovisual remota!";
                $quickReplies = ["Ver portfólio de vídeos", "Recomende um pacote", "Como funciona o fluxo?"];
            }
        } else {
            $detectedNiche = SofiMarketIntelligence::detectNiche($msg);

            if ($detectedNiche === 'saude') {
                if ($isEs) {
                    $reply = "En el sector salud y clínicas, la confianza es el factor #1 de decisión: el 82% de los pacientes elige especialista tras ver vídeos de autoridad explicando tratamientos y resolviendo dudas comunes.\n\n"
                           . "El formato con mayor retorno es el **Talking Head + B-Roll Cinematográfico**: el profesional explicando la solución en primer plano, intercalado con tomas de alta calidad de la clínica y tecnología.\n\n"
                           . "Para tu clínica, te recomiendo el paquete **{$recommendedPack->title}**, que incluye {$recommendedPack->video_quantity} producciones diseñadas para transmitir máxima reputación.";
                    $quickReplies = ["¿Cómo cuidamos la imagen del profesional?", "Ver detalles del paquete recomendado", "Calibrar ADN de Marca"];
                } else {
                    $reply = "No setor de saúde e clínicas, a autoridade do profissional é o principal fator de conversão: pacientes decidem agendar consultas após assistir vídeos do especialista explicando sintomas e procedimentos com clareza e empatia.\n\n"
                           . "O formato com maior retorno comprovado em 2025 é o **Talking Head + B-Roll no consultório**: o especialista direto para a câmera, com cortes dinâmicos do ambiente e procedimentos (sempre em conformidade com o CFM, sem antes/depois sensacionalista).\n\n"
                           . "Para a sua clínica, recomendo o pacote **{$recommendedPack->title}**, garantindo gravação 4K e roteiros de alta autoridade.";
                    $quickReplies = ["Como funciona a gravação no consultório?", "Ver detalhes do pacote recomendado", "Calibrar DNA da Marca"];
                }
            } elseif ($detectedNiche === 'imoveis') {
                if ($isEs) {
                    $reply = "En el sector inmobiliario y reformas, los vídeos estáticos ya no convierten: los recorridos en Reels y Shorts de 30-45s con música envolvente y datos clave sobreimpresos tienen 3x más tiempo de visualización que fotos convencionales.\n\n"
                           . "La clave está en mostrar el estilo de vida del inmueble y el vecindario en los primeros 3 segundos (gancho POV).\n\n"
                           . "Para tu cartera inmobiliaria, te recomiendo el paquete **{$recommendedPack->title}** con rodaje 4K cinematográfico.";
                    $quickReplies = ["¿Hacéis tomas con estabilizador cinematográfico?", "Ver detalles del paquete recomendado", "Calibrar ADN de Marca"];
                } else {
                    $reply = "No mercado imobiliário e arquitetura, foto estática perdeu mais de 60% do alcance orgânico. O que vende imóvel em 2025 são vídeos verticais dinâmicos de 30 a 45s com gancho de estilo de vida logo nos primeiros 3 segundos.\n\n"
                           . "Em imóveis de alto padrão, filmar a experiência de morar no imóvel e a localização converte muito mais do que um tour lento e burocrático.\n\n"
                           . "Para seus lançamentos ou carteira, recomendo o pacote **{$recommendedPack->title}** com captação 4K HDR e edição de alta retenção.";
                    $quickReplies = ["Fazem captação com estabilizador/gimbal?", "Ver detalhes do pacote recomendado", "Calibrar DNA da Marca"];
                }
            } elseif ($detectedNiche === 'ecommerce') {
                if ($isEs) {
                    $reply = "En e-commerce y moda, el vídeo es ahora el carrito de compras directo: el formato POV (cámara en primera persona mostrando el producto en uso) retiene un 68% más que los anuncios convencionales.\n\n"
                           . "Los vídeos de 7 a 15 segundos con gancho de deseo inmediato superan ampliamente a las producciones largas.\n\n"
                           . "Para tu tienda, el paquete ideal es **{$recommendedPack->title}**, enfocado en creativos virales para Instagram Reels y TikTok.";
                    $quickReplies = ["¿Cómo estructuramos los ganchos de 0-3s?", "Ver detalhes del paquete recomendado", "Calibrar ADN de Marca"];
                } else {
                    $reply = "No e-commerce e varejo, o vídeo se tornou a própria vitrine de compra: criativos em formato POV (mostrando o produto sendo testado ou usado na primeira pessoa) geram até 73% mais confiança do que fotos de estúdio (Nielsen).\n\n"
                           . "Reels de 7 a 15 segundos com quebra de padrão no início têm quase o dobro de alcance orgânico e conversão em tráfego pago.\n\n"
                           . "Para a sua loja, o pacote ideal é o **{$recommendedPack->title}**, pensado exatamente para tracionar Reels e TikTok com alta rotação.";
                    $quickReplies = ["Como criar ganchos de 0 a 3 segundos?", "Ver detalhes do pacote recomendado", "Calibrar DNA da Marca"];
                }
            } elseif ($detectedNiche === 'gastronomia') {
                if ($isEs) {
                    $reply = "En restauración y gastronomía, el 'food porn' cinematográfico es imbatible: planos macro en alta definición (4K), sonido ambiente crujiente y movimiento fluido en los primeros 2 segundos hacen que el vídeo sea compartido y guardado masivamente.\n\n"
                           . "Para tu restaurante, el paquete ideal es **{$recommendedPack->title}**, con captación presencial de tus platos estrella.";
                    $quickReplies = ["¿Grabáis en la propia cocina del local?", "Ver detalles del paquete recomendado", "Calibrar ADN de Marca"];
                } else {
                    $reply = "Na gastronomia e restaurantes, a decisão do cliente é puramente sensorial e imediata: closes macro em 4K do preparo, texturas e sons do prato nos primeiros 2 segundos aumentam drasticamente a taxa de salvamentos e visitas ao estabelecimento.\n\n"
                           . "Vídeos que mostram os bastidores do chef e o prato finalizado superam qualquer foto de cardápio.\n\n"
                           . "Para o seu espaço gastronômico, o pacote recomendado é o **{$recommendedPack->title}**, com gravação de alta definição no local.";
                    $quickReplies = ["Como funciona a gravação no restaurante?", "Ver detalhes do pacote recomendado", "Calibrar DNA da Marca"];
                }
            } elseif ($detectedNiche === 'advocacia' || $detectedNiche === 'b2b') {
                if ($isEs) {
                    $reply = "En el sector corporativo, consultoría y despachos, LinkedIn Video ha crecido un 34% en engagement y se ha convertido en el principal canal de captación B2B.\n\n"
                           . "Vídeos de 30 a 60 segundos donde el socio o consultor desmitifica un problema complejo posicionan tu firma en el top-of-mind de decisores empresariales.\n\n"
                           . "Te sugiero el paquete **{$recommendedPack->title}** para construir autoridad inquebrantable.";
                    $quickReplies = ["¿Cómo abordamos temas técnicos sin aburrir?", "Ver detalles del paquete recomendado", "Calibrar ADN de Marca"];
                } else {
                    $reply = "No setor corporativo, jurídico e consultorias B2B, o LinkedIn Video cresceu 34% em engajamento este ano. Empresas e escritórios com vídeos explicativos recebem até 5x mais contatos qualificados de decisores.\n\n"
                           . "A melhor estratégia não é falar 'juridiquês' ou termos burocráticos, mas sim responder as principais dores do cliente em 45 segundos com postura executiva e edição limpa.\n\n"
                           . "Para o seu posicionamento de autoridade, recomendo o pacote **{$recommendedPack->title}**.";
                    $quickReplies = ["Como explicar temas complexos em 45s?", "Ver detalhes do pacote recomendado", "Calibrar DNA da Marca"];
                }
            } else {
                if ($isEs) {
                    $reply = "¡Hola! Soy Sofi, consultora audiovisual estratégica en {$tenant->name}. Para conectar con tu público y generar ventas directas en 2025, el secreto está en los primeros 3 segundos de gancho visual y una narrativa de retención. " . ($recommendedPack ? "Para tu perfil, el paquete recomendado es **{$recommendedPack->title}**, que incluye {$recommendedPack->video_quantity} producciones en 4K optimizadas para Reels y TikTok." : "");
                    $quickReplies = ["Ver detalles del paquete recomendado", "¿Cómo se realiza la grabación?", "Calibrar ADN de Marca"];
                } else {
                    $reply = "Olá! Sou a Sofi, sua consultora audiovisual estratégica na {$tenant->name}. Para reter a atenção do seu público e converter em vendas em 2025, unimos ganchos de interrupção nos primeiros 3 segundos a uma captação cinematográfica 4K. " . ($recommendedPack ? "Para o seu objetivo, recomendo o pacote **{$recommendedPack->title}**, com {$recommendedPack->video_quantity} vídeos prontos para tracionar o seu perfil." : "");
                    $quickReplies = ["Ver detalhes do pacote recomendado", "Como funciona a gravação?", "Calibrar DNA da Marca"];
                }
            }
        }

        return $this->formatResult([
            'reply' => $reply,
            'recommended_pack_id' => $recommendedPack?->id,
            'call_to_action' => $callToAction,
            'suggested_quick_replies' => $quickReplies,
        ], $packs, $isEs);
    }

    /**
     * Formata e enriquece a resposta com os dados estruturados do pacote e action para o modal.
     */
    protected function formatResult(array $data, $packs, bool $isEs): array
    {
        $packData = null;
        if (! empty($data['recommended_pack_id'])) {
            $pack = $packs->firstWhere('id', (int) $data['recommended_pack_id']);
            if ($pack) {
                $rawPrice = (float) $pack->price;
                $priceWithIva = $rawPrice * 1.21;
                $addons = $pack->effective_addons;

                $packData = [
                    'id' => $pack->id,
                    'name' => $pack->translated_name ?? $pack->name,
                    'slug' => $pack->slug,
                    'title' => $pack->title,
                    'raw_price' => $rawPrice,
                    'price' => number_format($rawPrice, 2, ',', '.'),
                    'price_with_iva' => number_format($priceWithIva, 2, ',', '.'),
                    'currency' => $pack->currency ?? 'EUR',
                    'video_quantity' => $pack->video_quantity,
                    'description' => $pack->translated_description ?? $pack->description,
                    'badge' => $isEs ? 'Recomendación Sofi' : 'Recomendação da Sofi',
                    'addons' => $addons,
                    'register_url' => route('portal.register', ['pack' => $pack->slug]),
                    'login_url' => route('portal.login', ['intended_pack_slug' => $pack->slug]),
                ];
            }
        }

        $ctaType = $data['call_to_action'] ?? null;
        if ($packData && empty($ctaType)) {
            $ctaType = 'open_pack_modal';
        }

        $quickReplies = $data['suggested_quick_replies'] ?? [];
        $changeNicheLabel = $isEs ? '🔄 Cambiar de nicho' : '🔄 Trocar de nicho';
        if (!empty($quickReplies) && !in_array($changeNicheLabel, $quickReplies, true)) {
            $quickReplies[] = $changeNicheLabel;
        }

        return [
            'reply' => $data['reply'],
            'call_to_action' => $ctaType,
            'recommended_pack' => $packData,
            'dna_register_url' => route('portal.register'),
            'quick_replies' => $quickReplies,
        ];
    }
}
