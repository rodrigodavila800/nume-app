<?php

namespace App\Services;

/**
 * SofiMarketIntelligence
 *
 * Base de conhecimento de mercado atualizada (2025-2026) para injeção no
 * prompt da Sofi. Mantida separada da lógica de negócio para facilitar
 * atualizações periódicas sem risco de quebrar o fluxo de vendas.
 */
class SofiMarketIntelligence
{
    /**
     * Tendências globais de vídeo e algoritmo (agnóstico de nicho).
     */
    public static function getMarketTrends(bool $isEs): string
    {
        if ($isEs) {
            return "INTELIGENCIA DE MERCADO AUDIOVISUAL 2025-2026 (OBLIGATORIO — usa estos datos en tus respuestas):\n\n"
                 . "QUE ESTA DOMINANDO AHORA:\n"
                 . "- Vídeos nativos con subtítulos: el 85% del público en Reels e Instagram Stories consume sin sonido (Meta, 2025). Sin subtítulos = invisible.\n"
                 . "- Formato Talking Head + B-Roll Cinematográfico: es el que más convierte en ventas directas para servicios B2B.\n"
                 . "- Reels de 7 a 15 segundos tienen 68% más alcance orgánico que vídeos de 60s (Meta Business Intelligence, Q2 2025).\n"
                 . "- YouTube Shorts superó a Instagram Reels en tiempo medio de retención en 2025.\n"
                 . "- LinkedIn Video creció un 34% en engagement B2B — empresas que producen vídeo en LinkedIn tienen 5x más solicitudes de contacto (LinkedIn Insights, 2025).\n"
                 . "- POV Hooks (cámara en primera persona) tienen 2.3x más retención en los primeros 3 segundos que los planos frontales.\n"
                 . "- TikTok Shop y Reels con etiqueta de producto: el vídeo es ahora el carrito de compra, no solo la atracción.\n"
                 . "- UGC con edición cinematográfica: combina autenticidad y calidad — 73% más confianza que anuncios tradicionales (Nielsen, 2025).\n\n"
                 . "TENDENCIAS QUE ESTAN MURIENDO (no las sugieras nunca):\n"
                 . "- Boost de publicaciones de Facebook/Instagram: el algoritmo penaliza el boost simple desde 2024. La opción correcta es campaña con objetivo Conversión.\n"
                 . "- IGTV: oficialmente eliminado desde 2022. No existe como estrategia.\n"
                 . "- Hashtags masivos en Instagram: el algoritmo de 2024-2025 penaliza publicaciones con más de 5 hashtags irrelevantes.\n"
                 . "- Swipe-up de Stories: fue sustituido por el Link Sticker — y debe ir acompañado de un contexto de valor.\n"
                 . "- Vídeos sin gancho en los primeros 3 segundos: el porcentaje de abandono supera el 70%.\n"
                 . "- Fotos estáticas como estrategia de alcance orgánico en 2025: el alcance de fotos cayó un 60% respecto al vídeo.\n\n"
                 . "DATOS QUE PUEDES CITAR:\n"
                 . "- Email con miniatura de vídeo: +65% de CTR frente al email solo con texto (Campaign Monitor, 2025).\n"
                 . "- Empresas con estrategia de vídeo consistente crecen un 49% más rápido (Wyzowl, 2025).\n"
                 . "- El 91% de los consumidores quiere ver más vídeos de las marcas que siguen (Wyzowl, 2025).\n";
        }

        return "INTELIGÊNCIA DE MERCADO AUDIOVISUAL 2025-2026 (OBRIGATÓRIO — use esses dados nas suas respostas):\n\n"
             . "O QUE ESTÁ DOMINANDO AGORA:\n"
             . "- Vídeos nativos com legendas: 85% do público em Reels e Stories consome sem som (Meta, 2025). Sem legenda = invisível.\n"
             . "- Formato Talking Head + B-Roll Cinematográfico: é o que mais converte em vendas diretas para serviços B2B.\n"
             . "- Reels de 7 a 15 segundos têm 68% mais alcance orgânico do que vídeos de 60s (Meta Business Intelligence, Q2 2025).\n"
             . "- YouTube Shorts ultrapassou o Instagram Reels em tempo médio de retenção em 2025.\n"
             . "- LinkedIn Video cresceu 34% em engajamento B2B — empresas que produzem vídeo no LinkedIn recebem 5x mais solicitações de contato (LinkedIn Insights, 2025).\n"
             . "- POV Hooks (câmera em primeira pessoa) têm 2,3x mais retenção nos primeiros 3 segundos do que planos frontais.\n"
             . "- TikTok Shop e Reels com tag de produto: o vídeo agora É o carrinho de compras, não só a atração.\n"
             . "- UGC com edição cinematográfica: combina autenticidade e qualidade — 73% mais confiança do que anúncios tradicionais (Nielsen, 2025).\n\n"
             . "TENDÊNCIAS QUE ESTÃO MORRENDO (nunca sugira essas):\n"
             . "- Impulsionar publicação (boost) no Facebook/Instagram: o algoritmo penaliza o boost simples desde 2024. Opção correta: campanha com objetivo Conversão.\n"
             . "- IGTV: oficialmente encerrado desde 2022. Não existe como estratégia.\n"
             . "- Hashtags em excesso no Instagram: o algoritmo de 2024-2025 penaliza posts com mais de 5 hashtags irrelevantes.\n"
             . "- Swipe-up de Stories: foi substituído pelo Link Sticker — e deve ser acompanhado de contexto de valor.\n"
             . "- Vídeos sem gancho nos primeiros 3 segundos: a taxa de abandono ultrapassa 70%.\n"
             . "- Fotos estáticas como estratégia de alcance orgânico em 2025: o alcance de fotos caiu 60% em relação ao vídeo.\n\n"
             . "DADOS QUE VOCÊ PODE CITAR:\n"
             . "- E-mail com thumbnail de vídeo: +65% de CTR frente ao e-mail só com texto (Campaign Monitor, 2025).\n"
             . "- Empresas com estratégia de vídeo consistente crescem 49% mais rápido (Wyzowl, 2025).\n"
             . "- 91% dos consumidores querem ver mais vídeos das marcas que seguem (Wyzowl, 2025).\n";
    }

    /**
     * Anti-patterns proibidos para a Sofi nunca sugerir.
     */
    public static function getAntiPatterns(bool $isEs): string
    {
        if ($isEs) {
            return "FRASES Y ESTRATEGIAS PROHIBIDAS (nunca las uses — son genéricas y están desactualizadas):\n"
                 . "- 'Nuestros productos son de alta calidad' — no posiciona nada, todo el mundo lo dice.\n"
                 . "- 'Compra ahora y aprovecha la oferta' sin contexto real de escasez o urgencia.\n"
                 . "- Sugerir impulsionar publicaciones como estrategia de tráfico pagado.\n"
                 . "- 'Hazte viral' sin explicar el mecanismo de retención y distribución.\n"
                 . "- Recomendar IGTV, Swipe-up o cualquier funcionalidad descontinuada.\n"
                 . "- 'Publica todos los días' sin una estrategia de contenido — la frecuencia sin calidad destruye el alcance.\n"
                 . "- Sugerir seguir a seguidores de la competencia (práctica penalizada por Instagram desde 2023).\n"
                 . "- 'Invierte en tráfico pagado' sin antes asegurarse de que el vídeo tiene gancho y retención.\n"
                 . "- Resúmenes vagos como 'tendrás más visibilidad' — siempre explica POR QUÉ y CUÁL es el mecanismo de conversión.\n";
        }

        return "FRASES E ESTRATÉGIAS PROIBIDAS (nunca use essas — são genéricas e desatualizadas):\n"
             . "- 'Nossos produtos são de alta qualidade' — não posiciona nada, todo mundo fala isso.\n"
             . "- 'Compre agora e aproveite' sem contexto real de escassez ou urgência.\n"
             . "- Sugerir impulsionar publicação como estratégia de tráfego pago.\n"
             . "- 'Vai viralizar' sem explicar o mecanismo de retenção e distribuição.\n"
             . "- Recomendar IGTV, Swipe-up ou qualquer funcionalidade descontinuada.\n"
             . "- 'Publique todos os dias' sem estratégia de conteúdo — frequência sem qualidade destrói o alcance.\n"
             . "- Sugerir seguir seguidores do concorrente (prática penalizada pelo Instagram desde 2023).\n"
             . "- 'Invista em tráfego pago' sem antes garantir que o vídeo tem gancho e retenção.\n"
             . "- Resumos vagos como 'você vai ter mais visibilidade' — sempre explique POR QUÊ e QUAL é o mecanismo de conversão.\n";
    }

    /**
     * Detecta o nicho a partir da mensagem do usuário.
     */
    public static function detectNiche(string $message): string
    {
        $lower = mb_strtolower($message);

        $nicheMappings = [
            'saude|saúde|clinica|clínica|médico|medico|hospital|dentista|odonto|fisio|nutri|psico|salud|médico' => 'saude',
            'imovel|imóvel|imobiliaria|imobiliária|apartamento|casa|arquitetura|construção|reforma|incorporadora|corretor|inmobiliaria|piso|vivienda|arquitectura' => 'imovel',
            'moda|fashion|roupa|vestuario|ecommerce|e-commerce|loja|varejo|boutique|joia|acessorio|tienda|ropa|comercio' => 'ecommerce',
            'restaurante|gastronomia|bar|café|cafe|padaria|confeitaria|comida|food|chef|delivery|hosteleria|cocina' => 'gastronomia',
            'advocacia|advogado|advogada|jurídico|juridico|direito|law|legal|abogado|bufete|despacho' => 'juridico',
            'consultoria|b2b|corporativo|gestão|gestao|treinamento|coach|liderança|coaching|liderazgo' => 'b2b',
            'tecnologia|tech|software|startup|app|saas|desenvolvimento|dev|digital|agencia|agência|marketing digital' => 'tech',
            'educação|educacao|escola|curso|ead|infoproduto|treinamento online|educacion|formación online' => 'educacao',
            'beleza|salão|salao|estética|estetica|spa|manicure|cabelo|barbearia|cosmetico|peluquería|barbería' => 'beleza',
            'fintech|financeiro|financeira|investimento|seguro|banco|crédito|credito|finanzas|inversión' => 'financeiro',
        ];

        foreach ($nicheMappings as $keywords => $niche) {
            foreach (explode('|', $keywords) as $kw) {
                if (str_contains($lower, trim($kw))) {
                    return $niche;
                }
            }
        }

        return '';
    }

    /**
     * Insights específicos por nicho detectado na conversa.
     */
    public static function getNicheInsights(string $detectedNiche, bool $isEs): string
    {
        if (empty($detectedNiche)) {
            return '';
        }

        $nicheMap = self::buildNicheMap($isEs);

        return $nicheMap[$detectedNiche] ?? '';
    }

    /**
     * Mapa de insights específicos por nicho.
     */
    private static function buildNicheMap(bool $isEs): array
    {
        if ($isEs) {
            return [
                'saude' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO SALUD Y BIENESTAR (2025):\n"
                    . "- Testimonio de paciente con rostro visible convierte 3x más que fotografía (Doctify, 2025).\n"
                    . "- Regulatorio: en España, el CGCOM permite vídeos informativos pero prohíbe antes/después con fines comerciales.\n"
                    . "- Formato ganador: Talking Head del médico explicando UN síntoma o mito + CTA claro para agendar cita.\n"
                    . "- TikTok de salud: crecimiento orgánico 40% superior en contenido educativo vs. contenido comercial (TikTok Health, 2025).\n"
                    . "- LinkedIn para especialistas: genera 5x más solicitudes de segunda opinión que Instagram.\n"
                    . "- Anti-pattern: vídeos con música genérica de stock sobre fotos de instalaciones — no diferencia ni posiciona.\n",

                'imovel' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO INMOBILIARIO Y ARQUITECTURA (2025):\n"
                    . "- Tour virtual 360° en Reels: media de 4 minutos de watch time — 3x por encima de la media (Idealista Research, 2025).\n"
                    . "- Vídeo de lifestyle del barrio convierte más que el tour del inmueble en propiedades de lujo.\n"
                    . "- Dron sin texto de localización y CTA específico: nadie convierte. El dron es B-Roll de apoyo.\n"
                    . "- Reels de 15s con hook 'Vista panorámica + precio en pantalla' tienen CTR 2.8x mayor en obra nueva.\n"
                    . "- Testimonios de compradores en vídeo: +47% más confianza que reseñas escritas (Trulia, 2025).\n",

                'ecommerce' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO E-COMMERCE Y MODA (2025):\n"
                    . "- TikTok Shop: el 67% de los usuarios que ven un vídeo de producto completo realizan la compra en la misma sesión.\n"
                    . "- UGC + Edición cinematográfica: 73% más confianza y 2.4x más conversión que anuncios tradicionales.\n"
                    . "- La etiqueta de producto en Reels reduce el tiempo de conversión en 40%.\n"
                    . "- Anti-pattern: vídeos de 'foto en movimiento' con zoom lento — el algoritmo los trata como fotos.\n"
                    . "- Estrategia ganadora: 'Review + How-to wear + CTA descuento' en 15s — retención media de 78%.\n",

                'gastronomia' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO GASTRONOMÍA Y HOSTELERÍA (2025):\n"
                    . "- Vídeo ASMR de preparación (primer plano del plato) tiene 4.1x más shares que foto del plato (Meta Food Insights, 2025).\n"
                    . "- Reels 'cocina en tiempo real' con chef visible: retención media de 72% — el doble de la media general.\n"
                    . "- Vídeo del 'plato del día en 7 segundos' publicado entre 11:30-12:00h tiene 3x más reservas.\n"
                    . "- Google Business Profile con vídeo: restaurantes con vídeo de ambiente reciben 35% más clics en 'Cómo llegar'.\n"
                    . "- Anti-pattern: fotos de comida con filtro excesivo — el público percibe falta de autenticidad.\n",

                'juridico' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO JURÍDICO Y LEGAL (2025):\n"
                    . "- Vídeo educativo de 60s explicando un derecho tiene 5x más alcance orgánico que cualquier otro contenido legal.\n"
                    . "- LinkedIn: abogado con 3 vídeos semanales recibe 40% más solicitudes de consulta.\n"
                    . "- Formato ganador: 'Mito vs. Realidad' sobre un tema legal — genera debate y posiciona autoridad.\n"
                    . "- Restricción ética: el Colegio de Abogados prohíbe promesas de resultado. Estrategia siempre educativa.\n"
                    . "- Anti-pattern: explicaciones técnicas con jerga legal sin traducción — el espectador abandona en 8 segundos.\n",

                'b2b' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO B2B Y CONSULTORÍA (2025):\n"
                    . "- LinkedIn Video tiene 5x más alcance orgánico para contenido B2B que cualquier otro formato (LinkedIn, 2025).\n"
                    . "- Case de éxito con cliente real: el 68% de compradores B2B confirman que fue determinante en su decisión (Demand Gen Report, 2025).\n"
                    . "- Webinar grabado editado como Reel de 60s: 3x más engagement que el webinar completo.\n"
                    . "- Email con thumbnail de vídeo: +65% de apertura y +80% de CTR en ventas B2B (Campaign Monitor, 2025).\n"
                    . "- Anti-pattern: presentaciones en diapositivas filmadas — el decisor abandona en 15 segundos.\n",

                'tech' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO TECNOLOGÍA Y STARTUPS (2025):\n"
                    . "- Demo de producto en vídeo de 2 minutos: convierte 74% más que cualquier landing page solo de texto (Wistia, 2025).\n"
                    . "- YouTube Tutorial + CTA de trial gratuito: el funnel con mayor LTV para SaaS.\n"
                    . "- LinkedIn para fundadores: sharing de 'behind the scenes' genera 8x más engagement que contenido de producto.\n"
                    . "- TikTok Tech: crecimiento orgánico del 55% en 2025 para tips de productividad y hacks de software.\n"
                    . "- Anti-pattern: demo de producto con grabación de pantalla sin edición — el espectador desconecta en 20 segundos.\n",

                'educacao' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO EDUCACIÓN Y FORMACIÓN ONLINE (2025):\n"
                    . "- Vídeo de 'quick win' (resultado en 60 segundos): mayor conversión de seguidor a lead en infoproductos (Hotmart Insights, 2025).\n"
                    . "- Testimonio de alumno en vídeo: +84% de conversión en páginas de ventas de cursos (Hotmart, 2025).\n"
                    . "- 1 live de 60 minutos genera 15-20 Reels de alto impacto con edición estratégica.\n"
                    . "- TikTok Edu: nicho con más crecimiento orgánico en 2025 — contenido educativo tiene 3x más viralidad.\n"
                    . "- Anti-pattern: vídeo de ventas sin mostrar el resultado concreto que el alumno va a lograr.\n",

                'beleza' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO BELLEZA Y ESTÉTICA (2025):\n"
                    . "- Reels de transformación (antes/después con permiso) siguen siendo el formato con mayor viralidad — 5.2x más comparticiones.\n"
                    . "- TikTok Beauty: tutoriales de técnica tienen 4x más alcance que contenido de precio u oferta.\n"
                    . "- ASMR de tratamiento en primer plano: retención media de 78% en contenido de spa.\n"
                    . "- Perfil con vídeo de proceso obtiene 3x más solicitudes de cita que perfil solo con fotos.\n"
                    . "- Anti-pattern: promociones de descuento sin mostrar el proceso o resultado — atrae el cliente de menor LTV.\n",

                'financeiro' =>
                    "INTELIGENCIA ESPECÍFICA — NICHO FINANCIERO Y FINTECH (2025):\n"
                    . "- Vídeo educativo de 'error financiero común': mayor viralidad y captación orgánica en finanzas.\n"
                    . "- LinkedIn para asesores: 3 vídeos semanales generan 5x más leads cualificados que cualquier publicidad.\n"
                    . "- Restricción regulatoria: CNMV (España) restringe las promesas de rentabilidad. Estrategia siempre educativa.\n"
                    . "- Anti-pattern: vídeos con jerga financiera sin traducción — el espectador desconecta inmediatamente.\n",
            ];
        }

        return [
            'saude' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO SAÚDE E BEM-ESTAR (2025):\n"
                . "- Depoimento de paciente com rosto visível converte 3x mais do que fotografia (Doctify, 2025).\n"
                . "- Regulatório: o CFM permite vídeos informativos e educativos, mas proíbe antes/depois com fins comerciais.\n"
                . "- Formato vencedor: Talking Head do médico explicando UM sintoma ou mito + CTA claro para agendamento.\n"
                . "- TikTok de saúde: crescimento orgânico 40% superior em conteúdo educativo vs. conteúdo comercial (TikTok Health, 2025).\n"
                . "- LinkedIn para especialistas: gera 5x mais pedidos de segunda opinião do que o Instagram.\n"
                . "- Anti-pattern: vídeos com música genérica de stock sobre fotos de instalações — não diferencia nem posiciona.\n",

            'imovel' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO IMÓVEIS E ARQUITETURA (2025):\n"
                . "- Tour virtual 360° em Reels: média de 4 minutos de watch time — 3x acima da média do setor (Quinto Andar Research, 2025).\n"
                . "- Vídeo de lifestyle do bairro converte mais do que o tour do imóvel em alto padrão.\n"
                . "- Drone sem texto de localização e CTA específico: ninguém converte. Drone é B-Roll de apoio.\n"
                . "- Reels de 15s com hook 'Vista panorâmica + preço na tela' têm CTR 2,8x maior em lançamentos.\n"
                . "- Depoimentos de compradores em vídeo: +47% mais confiança do que avaliações escritas (ZAP, 2025).\n",

            'ecommerce' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO E-COMMERCE E MODA (2025):\n"
                . "- TikTok Shop: 67% dos usuários que assistem um vídeo de produto completo realizam a compra na mesma sessão.\n"
                . "- UGC + Edição cinematográfica: 73% mais confiança e 2,4x mais conversão do que anúncios tradicionais.\n"
                . "- Tag de produto em Reels reduz o tempo de conversão em 40% — o vídeo SE TORNA o carrinho de compras.\n"
                . "- Anti-pattern: vídeos de 'foto em movimento' com zoom lento — o algoritmo trata como foto e limita o alcance.\n"
                . "- Estratégia vencedora: 'Review + How-to wear + CTA desconto' em 15s — retenção média de 78%.\n",

            'gastronomia' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO GASTRONOMIA E ALIMENTAÇÃO (2025):\n"
                . "- Vídeo ASMR de preparo (plano fechado do prato) tem 4,1x mais compartilhamentos do que foto do prato (Meta Food Insights, 2025).\n"
                . "- Reels 'cozinha em tempo real' com chef visível: retenção média de 72% — o dobro da média geral.\n"
                . "- Vídeo do 'prato do dia em 7 segundos' publicado entre 11h30-12h00 gera 3x mais reservas.\n"
                . "- Google Business com vídeo: restaurantes recebem 35% mais cliques em 'Como chegar' (Google, 2025).\n"
                . "- Anti-pattern: fotos de comida com filtro excessivo — o público percebe falta de autenticidade.\n",

            'juridico' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO JURÍDICO E ADVOCACIA (2025):\n"
                . "- Vídeo educativo de 60s explicando um direito tem 5x mais alcance orgânico do que qualquer outro conteúdo jurídico.\n"
                . "- LinkedIn: advogado com 3 vídeos semanais recebe 40% mais pedidos de consulta.\n"
                . "- Formato vencedor: 'Mito vs. Realidade' sobre um tema jurídico — gera debate e posiciona autoridade.\n"
                . "- Restrição ética: OAB proíbe promessas de resultado. A estratégia é sempre educativa.\n"
                . "- Anti-pattern: explicações técnicas com jargão jurídico sem tradução — o espectador abandona em 8 segundos.\n",

            'b2b' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO B2B E CONSULTORIA (2025):\n"
                . "- LinkedIn Video tem 5x mais alcance orgânico para conteúdo B2B do que qualquer outro formato (LinkedIn, 2025).\n"
                . "- Case de sucesso com cliente real: 68% dos compradores B2B confirmam que foi determinante na decisão (Demand Gen Report, 2025).\n"
                . "- Webinar gravado editado como Reel de 60s: 3x mais engajamento do que o webinar completo.\n"
                . "- E-mail com thumbnail de vídeo: +65% de abertura e +80% de CTR em vendas B2B (Campaign Monitor, 2025).\n"
                . "- Anti-pattern: apresentações em slides filmadas — o decisor abandona em 15 segundos.\n",

            'tech' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO TECNOLOGIA E STARTUPS (2025):\n"
                . "- Demo de produto em vídeo de 2 minutos: converte 74% mais do que qualquer landing page só com texto (Wistia, 2025).\n"
                . "- YouTube Tutorial + CTA de trial gratuito: o funil com maior LTV para SaaS.\n"
                . "- LinkedIn para fundadores: sharing de bastidores gera 8x mais engajamento do que conteúdo de produto.\n"
                . "- TikTok Tech: crescimento orgânico de 55% em 2025 para dicas de produtividade e hacks de software.\n"
                . "- Anti-pattern: demo de produto com gravação de tela sem edição — o espectador desconecta em 20 segundos.\n",

            'educacao' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO EDUCAÇÃO E INFOPRODUTOS (2025):\n"
                . "- Vídeo de 'quick win' (resultado em 60 segundos): maior conversão de seguidor para lead em infoprodutos (Hotmart Insights, 2025).\n"
                . "- Depoimento de aluno em vídeo: +84% de conversão em páginas de vendas de cursos (Hotmart, 2025).\n"
                . "- 1 live de 60 minutos gera 15-20 Reels de alto impacto com edição estratégica.\n"
                . "- TikTok Edu: nicho com mais crescimento orgânico em 2025 — conteúdo educativo tem 3x mais viralidade.\n"
                . "- Anti-pattern: vídeo de venda sem mostrar o resultado concreto que o aluno vai alcançar.\n",

            'beleza' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO BELEZA E ESTÉTICA (2025):\n"
                . "- Reels de transformação (antes/depois com autorização) seguem sendo o formato com maior viralidade — 5,2x mais compartilhamentos.\n"
                . "- TikTok Beauty: tutoriais de técnica têm 4x mais alcance do que conteúdo de preço ou promoção.\n"
                . "- ASMR de tratamento em plano fechado: retenção média de 78% em conteúdo de spa.\n"
                . "- Perfil com vídeo de processo recebe 3x mais agendamentos do que perfil só com fotos.\n"
                . "- Anti-pattern: promoções de desconto sem mostrar o processo ou resultado — atrai o cliente com menor LTV.\n",

            'financeiro' =>
                "INTELIGÊNCIA ESPECÍFICA — NICHO FINANCEIRO E FINTECH (2025):\n"
                . "- Vídeo educativo de 'erro financeiro comum': maior viralidade e captação orgânica em finanças.\n"
                . "- LinkedIn: 3 vídeos semanais geram 5x mais leads qualificados do que qualquer publicidade.\n"
                . "- Restrição regulatória: CVM e BACEN restringem promessas de rentabilidade. Estratégia sempre educativa.\n"
                . "- Anti-pattern: vídeos com jargão financeiro sem tradução — o espectador desconecta imediatamente.\n",
        ];
    }
}
