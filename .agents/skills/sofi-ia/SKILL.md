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
    ?Cliente $cliente = null
): array
```

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
   - `POST /productions/{production}/generate-sofi-script`
   - Invoca o `SofiCopywriterService`, monta o roteiro em formato padronizado e cria ou atualiza o `Roteiro` em estado `draft`.
2. **Regeneração de SEO da Plataforma do Tenant:**
   - `POST /admin/settings/platform/regenerate-seo`
   - Atualiza `seo_meta_title`, `seo_meta_description`, `seo_schema_keywords` e `seo_last_generated_at` no model `Tenant`.

---

## 4. Engenharia de Prompts e Modelos Gemini
 
- **Modelo Padrão:** `gemini-3.5-flash-lite` para baixa latência, respostas em tempo real no chat da Sofi e geração rápida de roteiros/planos; fallbacks automáticos em cascata para `gemini-3.8-flash` e `gemini-3.7-flash` configuráveis via `GEMINI_MODEL`.
- **Fallback Heurístico:** Se a chave da API do Gemini estiver ausente, cota esgotada ou todos os modelos externos retornarem indisponibilidade (503/offline), os serviços acionam automaticamente motores heurísticos científicos calibrados em PT-BR e ES que geram respostas, planos de marketing e roteiros profissionais instantaneamente sem quebrar a experiência do usuário.
