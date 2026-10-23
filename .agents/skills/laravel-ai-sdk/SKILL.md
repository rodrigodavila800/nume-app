---
name: laravel-ai-sdk
description: Como criar ou migrar funcionalidades de IA do Nume App para o pacote oficial laravel/ai (Agents, Tools, saída estruturada, filas, failover, testes), preservando isolamento por tenant e fallback heurístico da Sofi. Carregar sempre que a tarefa criar ou alterar chamada a modelo de IA, Gemini, prompts, agentes ou tools.
---

# Skill: Laravel AI SDK no Nume App

Estudo completo, com riscos e plano em fases: `docs/estudo-laravel-ai-sdk.md`. Esta skill é o resumo operacional.

## Estado atual (Fase 3 concluída, 23/10/2026)

- `laravel/ai` **v1.3.0** instalado (`^1.3`), exige PHP ≥ 8.3. Config em `config/ai.php` com `'default' => env('SOFI_AI_PROVIDER', 'gemini')`.
- Migrations de conversa do SDK **não** foram publicadas (sem `tenant_id`; decisão na Fase 3).
- Interruptor `SOFI_USE_AI_SDK` (`config/sofi.php`). Migrados para o SDK, com fallback heurístico preservado:
  - `SofiTranslatorService` → `app/Ai/Agents/SofiTranslatorAgent.php` (sem tenant).
  - `Quotes/SofiQuoteEstimatorService::estimate($text, ?Tenant)` → `app/Ai/Agents/SofiQuoteEstimatorAgent.php` (`HasStructuredOutput`, saída passa por `normalizeParsedData()`).
  - `SofiSalesConsultantService::consult()` (chat **público** da vitrine) → `app/Ai/Agents/SofiSalesConsultantAgent.php` (`HasTools` + `HasStructuredOutput` + `Conversational`, `#[MaxSteps(3)]`). Regras comerciais vêm de `salesPromptParts()` (mesmo texto do prompt antigo); histórico da sessão entra por `messages()`; saída passa por `formatResult()`, que aceita só `call_to_action` `open_pack_modal`/`register_dna`/`null`.
- **Tools:** `app/Ai/Tools/ListTenantPacksTool` (somente leitura): pacotes ativos, preço, vídeos e adicionais do tenant do construtor, com `withoutGlobalScope('tenant')` + `where('tenant_id', ...)` em pacotes **e** adicionais. Modelo para toda tool de contexto público.
- **Rate limit do chat público:** limiters `sofi-chat` (por IP/min, por IP/hora, por vitrine/hora) e `sofi-chat-reset` no `AppServiceProvider`, números em `config('sofi.chat_rate_limits')`.
- **Provider único:** `App\Ai\SofiProvider::options(?Tenant)` devolve `provider`/`model`/`timeout` para `prompt(...$options)` ou `null` sem chave. Nunca chame `Ai::build` direto num service. Chave ainda vem de `config('sofi.keys.*')`; o ponto da chave por tenant (via `Setting`) está marcado em `SofiProvider::apiKey()`.
- **Uso por tenant:** middleware `App\Ai\Middleware\RecordAiUsage($tenantId, static::class)` em `middleware()` de todo agente (`HasMiddleware`), uma linha por step em `ai_usage_logs` (tokens, cache, driver, modelo, `invocation_id`, sem conteúdo). Model `AiUsageLog` **sem** `BelongsToTenant`; consulte sempre com `AiUsageLog::forTenant($id)`.
- Ainda chamam o Gemini direto (chave no header `x-goog-api-key`, logs só com `get_class($e)`): `SofiCopywriterService`, `SofiCopilotChatService` e `SofiBrandAnalystService`, além do caminho antigo dos três migrados. `MarketingPlanGeneratorService` e `BrandDnaCrawlerService` não chamam o Gemini diretamente (o crawler só faz HTTP no site do cliente).
- Config em `config/services.php` → `services.gemini.{key,model,fallback_models}`; chave também em `Setting::get('gemini_api_key')`.
- Quando todos os modelos falham, o service devolve um **fallback heurístico** (templates PT-BR/ES). Esse comportamento deve ser mantido.

## Comportamentos do SDK v1.3 já verificados no código (vendor)

- `prompt($texto, provider:, model:, timeout:)` existe; `(string) $response` devolve o texto.
- **Provider em array (`provider: [Ai::build(...)]`) faz o SDK IGNORAR o argumento `model`.** Passe o provider como **string** (nome).
- `Ai::build([...])` sem `'name'` gera `ondemand_<md5 da config, incluindo a chave>`. Sempre dê `'name'`.
- O driver Gemini manda a chave no header `x-goog-api-key` (nada de `?key=`).
- Testes: `preventStrayPrompts()` do fake do SDK + `Http::preventStrayRequests()`.
- **Registro compartilhado (confirmado):** `Ai::build` guarda o provider no singleton `AiManager` (`$onDemandProviders[$name]`); o último `build()` com o mesmo nome vence. É limpo entre jobs (`Looping`) e no fim de operações do Octane. Por isso o nome é `sofi_{driver}_t{tenantId}` (via `SofiProvider`). Em `->queue()`, reconstrua o provider dentro do job: a string do nome sozinha não sobrevive ao `flushState()`.
- **Uso de tokens:** `$response->usage` (`TextUsage`: `inputTokens`, `outputTokens`, `cacheReadInputTokens`, `cacheWriteInputTokens`, `reasoningTokens`) soma os steps. O middleware de agente (`HasMiddleware::middleware()`) roda **por step** (`PendingStep` → `$next($step)->then(fn (StepResponse $r) => ...)`), com `provider` (nome), `model` e `invocationId`. `Provider::driver()` devolve o driver real.
- **Fake:** o `FakeTextGateway` passa pelo `TextGenerationLoop`, então o middleware do agente roda nos testes. Para simular tokens, devolva `new TextResponse(...)`/`new StructuredTextResponse($data, json_encode($data), new TextUsage($in, $out), new Meta)` no array do `fake()`. Arrays viram saída estruturada; closures com `throw` simulam falha.
- Atributos de geração: `#[Temperature]`, `#[MaxTokens]`, `#[MaxSteps]`, `#[Timeout]` (classe do agente).
- **Tools no fake:** devolva `new ToolCall('id', 'NomeDaClasseDaTool', [])` como um passo do `fake([...])`; o loop executa a tool real e usa o próximo item como resposta. Nome da tool = `class_basename` (ou `name()`). Tool inexistente → `NoSuchToolException` (o service cai no fallback). Eventos `InvokingTool`/`ToolInvoked` (com `result`) servem para asserts.
- **Schema:** `->enum([...])->nullable()` gera `null` FORA do `enum`, e o Gemini só aceita enum de strings. Para campo anulável com valores fixos, use string anulável com `description` e valide no service.
- Cada passo de tool reenvia as instruções: no chat da vitrine, ~3,5 mil tokens de entrada por mensagem (2 requisições).
- **Rate limit:** o `ThrottleRequests` roda antes do `SubstituteBindings`; dentro de `RateLimiter::for`, `$request->route('tenant')` pode ser a string do slug. Trate os dois casos.
- **Scope em contexto público:** com usuário logado de outra produtora, o `BelongsToTenant` filtra pelo tenant DELE. Em vitrine, webhook, fila e tools, consulte com `withoutGlobalScope('tenant')` + `where('tenant_id', ...)`, inclusive em relações carregadas (`with([... => fn ($q) => ...])`).

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
