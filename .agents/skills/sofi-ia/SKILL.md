---
name: sofi-ia
description: Conhecimento do motor Sofi IA & Copywriting — Integração com Google Gemini (gemini-1.5-flash/pro), geração automática de roteiros (SofiCopywriterService), frameworks de retenção (pattern_interrupt, authority_interview, direct_offer, storytelling_case), calibração por DNA do cliente e geração de metadados SEO virais. Carregar sempre que a tarefa envolver inteligência artificial, geração de conteúdo, copywriting ou otimização de SEO.
---

# Módulo Sofi IA & Copywriting

**Responsabilidade:** Atuar como o cérebro inteligente da plataforma Nume App. A Sofi automatiza o processo de ideação e escrita de roteiros cinematográficos de alta retenção, calibra o tom de voz com o perfil da empresa cliente e gera títulos e descrições otimizados para os algoritmos do YouTube, Instagram e TikTok.

---

## 1. O Serviço Central: `SofiCopywriterService`

Localizado em `app/Services/SofiCopywriterService.php`, o serviço processa as solicitações de geração de roteiro e SEO.

### Assinatura do Método `generate()`:
```php
public function generate(
    string $title,
    ?string $description = null,
    ?string $keywords = null,
    string $framework = 'pattern_interrupt',
    string $tone = 'persuasive',
    string $language = 'pt_BR',
    ?Cliente $cliente = null,
    ?VideoType $videoType = null
): array
```

### Roteiro por tipo de vídeo (blocos)

Quando recebe um `VideoType`, a Sofi **ignora o `$framework`** e escreve um texto para cada bloco do tipo, na ordem e nos tempos cadastrados pelo admin (Configurações > Tipos de vídeo). O tipo padrão é "Vendas (30 s)", com 6 blocos: gancho 0–3 s, problema 3–8, virada 8–15, prova 15–22, oferta 22–26 e CTA 26–30 (`VideoType::salesBlocks()`).

- O prompt leva os blocos, a `sofi_guidance` do tipo e o DNA do cliente, e exige o JSON `{"blocks": {"<key>": "..."}, "seo_title", "seo_description", "seo_hashtags", "b_roll_suggestions"}`.
- A resposta só vale se **todos** os blocos vierem preenchidos. Se não vierem, entra o fallback heurístico **por bloco**: ele reconhece a função pelo key ou pelo nome (gancho, dor, virada, prova, oferta, CTA, em PT/ES) e, para um bloco desconhecido, usa a função descrita pelo admin.
- O retorno inclui `blocks` (key, name, start, end, script), `structure` (a cópia do tipo), `video_type_id` e `source` (`ai` ou `heuristic`). Também mantém `hook`/`body`/`cta` para não quebrar quem lê o formato antigo.
- O roteiro salvo grava `video_type_id` e `structure` (`VideoType::snapshot()`). A estrutura só muda quando o tipo do roteiro muda, então editar ou desativar o tipo depois não altera roteiros já escritos.
- O tipo vem sempre do tenant da produção e precisa estar ativo (`RoteiroController::resolveVideoType`).
- Há **uma única chamada HTTP ao Gemini** no serviço: `requestGeminiJson($prompt, $isValid)`. Não crie outra.

### Retorno Estruturado:
```php
[
    'framework' => 'Padrão de Interrupção & Retenção',
    'estimated_time' => '50-55s',
    'client_niche' => 'Tecnologia / SaaS',
    'hook' => '...',       // Cena 1 (00:00 - 00:03)
    'body' => '...',       // Cena 2 (00:04 - 00:42)
    'cta' => '...',        // Cena 3 (00:43 - 00:55)
    'full_script' => '...',
    'seo_title' => '...',
    'seo_description' => '...',
    'seo_hashtags' => ['#VideoMarketing', '#SaaS', ...],
    'b_roll_suggestions' => [...],
]
```

---

## 2. Injeção Dinâmica do DNA da Marca

Quando um objeto `Cliente` é passado, o serviço extrai automaticamente os atributos e os incorpora no roteiro:
- `company_name`: Nome da empresa citado naturalmente na agitação.
- `brand_niche`: Contexto e vocabulário técnico do setor.
- `target_audience`: Personas a quem o gancho e o diferencial se dirigem.
- `key_differentials`: Argumentos de autoridade e quebra de objeções na Cena 2.
- `instagram_handle` & `website`: Call to Action (CTA) personalizada na Cena 3.

---

## 3. Endpoints Integrados

1. **Geração Instantânea na Produção:**
   - `POST /productions/{production}/generate-sofi-script` (`roteiros.generate-sofi`), com `framework`, `tone`, `language` e `video_type_id` (vazio = framework clássico).
   - Devolve o roteiro em JSON para preencher o editor; quem grava é o `store`/`update` do `RoteiroController`.
2. **Regeneração de SEO da Plataforma do Tenant:**
   - `POST /admin/settings/platform/regenerate-seo`
   - Atualiza `seo_meta_title`, `seo_meta_description`, `seo_schema_keywords` e `seo_last_generated_at` no model `Tenant`.

---

## 4. Engenharia de Prompts e Modelos Gemini
 
- **Modelo Padrão:** `gemini-3.5-flash-lite` para baixa latência, respostas em tempo real no chat da Sofi e geração rápida de roteiros/planos; fallbacks automáticos em cascata para `gemini-3.8-flash` e `gemini-3.7-flash` configuráveis via `GEMINI_MODEL`.
- **Fallback Heurístico:** Se a chave da API do Gemini estiver ausente, cota esgotada ou todos os modelos externos retornarem indisponibilidade (503/offline), os serviços acionam automaticamente motores heurísticos científicos calibrados em PT-BR e ES que geram respostas, planos de marketing e roteiros profissionais instantaneamente sem quebrar a experiência do usuário.
