---
name: laravel-ai-sdk
description: Como criar ou migrar funcionalidades de IA do Nume App para o pacote oficial laravel/ai (Agents, Tools, saída estruturada, filas, failover, testes), preservando isolamento por tenant e fallback heurístico da Sofi. Carregar sempre que a tarefa criar ou alterar chamada a modelo de IA, Gemini, prompts, agentes ou tools.
---

# Skill: Laravel AI SDK no Nume App

Estudo completo, com riscos e plano em fases: `docs/estudo-laravel-ai-sdk.md`. Esta skill é o resumo operacional.

## Estado atual (antes da migração)

- `laravel/ai` **v1.3.0** instalado (`^1.3`), exige PHP ≥ 8.3. Config em `config/ai.php` com `'default' => env('SOFI_AI_PROVIDER', 'gemini')`.
- Migrations de conversa do SDK **não** foram publicadas (sem `tenant_id`; decisão na Fase 3).
- Piloto pronto: `app/Ai/Agents/SofiTranslatorAgent.php` + `SofiTranslatorService::translateViaSdk()`, ligado por `SOFI_USE_AI_SDK`; testes em `tests/Feature/SofiTranslatorAgentTest.php`.
- **Seis** services chamam o Gemini direto por `Http::post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}")`, cada um com sua própria cascata de modelos e seu próprio timeout (3 s a 25 s): `SofiCopywriterService`, `SofiCopilotChatService`, `SofiBrandAnalystService`, `SofiSalesConsultantService`, `SofiTranslatorService` e `Quotes/SofiQuoteEstimatorService`. `MarketingPlanGeneratorService` e `BrandDnaCrawlerService` não chamam o Gemini diretamente (o crawler só faz HTTP no site do cliente).
- Config em `config/services.php` → `services.gemini.{key,model,fallback_models}`; chave também em `Setting::get('gemini_api_key')`.
- Quando todos os modelos falham, o service devolve um **fallback heurístico** (templates PT-BR/ES). Esse comportamento deve ser mantido.

## Comportamentos do SDK v1.3 já verificados no código (vendor)

- `prompt($texto, provider:, model:, timeout:)` existe; `(string) $response` devolve o texto.
- **Provider em array (`provider: [Ai::build(...)]`) faz o SDK IGNORAR o argumento `model`.** Passe o provider como **string** (nome).
- `Ai::build([...])` sem `'name'` gera `ondemand_<md5 da config, incluindo a chave>`. Sempre dê `'name'`.
- O driver Gemini manda a chave no header `x-goog-api-key` (nada de `?key=`).
- Testes: `preventStrayPrompts()` do fake do SDK + `Http::preventStrayRequests()`.
- **A confirmar antes da chave por tenant (Fase 2):** se `Ai::build` com `'name'` fixo registra o provider num registro compartilhado do processo. Se registrar, um worker de fila reutilizaria a chave do primeiro tenant para os seguintes. Nesse caso o nome precisa incluir o tenant (ex.: `sofi_anthropic_t{$tenantId}`) ou o registro precisa ser limpo.

## Regras ao usar o SDK

1. **Uma classe `Agent` por caso de uso** em `app/Ai/Agents/` (`php artisan make:agent NomeAgent --structured`). Tools em `app/Ai/Tools/` (`php artisan make:tool`). Nada de prompt gigante dentro de controller.
2. **Instruções (system) separadas de dados do usuário.** Regras da Sofi vão em `instructions()`. Texto do cliente, conteúdo de site rastreado e briefing entram no `prompt()` como dados, nunca misturados com as instruções. Trate-os como não confiáveis.
3. **Saída estruturada em vez de "retorne JSON" no texto.** Use `HasStructuredOutput` + `schema()`. Remova o `preg_replace` de blocos ```` ```json ```` quando migrar.
4. **Modelo fixo por atributo:** `#[Provider(Lab::Gemini)]` e `#[Model('...')]` lendo o nome de `config('services.gemini.model')`. Não use `#[UseCheapestModel]`/`#[UseSmartestModel]` em produção (o modelo muda entre releases).
5. **Tenant explícito.** O scope de `BelongsToTenant` só funciona com usuário autenticado. Todo Agent/Tool recebe `Tenant`/`tenant_id` no construtor e filtra `where('tenant_id', ...)` nas suas queries. Em `->queue()` não há usuário autenticado.
6. **Conversas:** `RemembersConversations` do SDK não grava `tenant_id` e `continue()` **não verifica dono**. Para o chat da Sofi, mantenha `SofiConversation`/`SofiMessage` (com tenant e cliente) e alimente o agente via `messages()` ou `withMessages()`. Se usar as tabelas do SDK, autorize antes de `continue()`.
7. **Chave por tenant:** use provedor sob demanda (`Ai::build(['driver' => 'gemini', 'key' => ...])`) com a chave vinda de `Setting` (criptografada). Nunca logue a chave.
8. **Fallback heurístico preservado:** envolva a chamada ao Agent em try/catch e devolva o motor heurístico existente se falhar. O failover do SDK cobre modelo/provedor, não substitui isso.
9. **Timeout, passos e tokens explícitos** (`#[Timeout]`, `#[MaxSteps]`, `#[MaxTokens]`). Tarefas longas (plano editorial em lote) vão para `->queue()`.
10. **Custo por tenant:** registre `usage` (tokens de entrada/saída) via middleware de agente, com `tenant_id`, agente e modelo.
11. **Tools que gravam ou custam dinheiro** (publicar, emitir fatura, cobrar) exigem aprovação humana (`Approvable`). Tools só de leitura devem ser read-only.

## Testes (obrigatório para todo Agent novo)

- Use os fakes do SDK para não chamar a API real (nome exato e assinatura: conferir na documentação da versão instalada).
- Cubra: resposta estruturada válida, falha do provedor → fallback heurístico, isolamento de tenant em Tool.
- `php artisan test` precisa passar sem chave do Gemini no ambiente.

## Checklist rápido

- [ ] Existe fallback heurístico?
- [ ] Tenant é passado explicitamente e testado?
- [ ] Nenhuma chave em URL, log ou `env()` fora de `config/`?
- [ ] Schema define todos os campos que a view usa?
- [ ] Teste com fake do SDK?
