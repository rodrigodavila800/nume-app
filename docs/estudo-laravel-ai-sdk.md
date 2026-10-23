# Estudo técnico — Laravel AI SDK no Nume App

Data: 23/10/2026 · Fontes: [laravel.com/ai](https://laravel.com/ai), [laravel.com/docs/ai-sdk](https://laravel.com/docs/ai-sdk), [laravel.com/ai/boost](https://laravel.com/ai/boost) e leitura do código do projeto.

Legenda: **[doc]** confirmado na documentação lida · **[código]** confirmado no código do projeto · **[a confirmar]** não consegui verificar; confirmar antes de decidir.

---

## 1. Resumo executivo

- O `laravel/ai` é o pacote oficial do Laravel para IA: texto, imagens, áudio, embeddings, reranking, arquivos, agentes, tools, streaming, filas, failover e testes, com uma API só para vários provedores, **incluindo Gemini** [doc].
- O Nume App hoje tem **seis services** que montam à mão a mesma chamada HTTP ao Gemini, cada um com sua própria cascata de modelos, timeout (3 s a 25 s) e parsing de JSON [código].
- **Recomendação: adotar de forma incremental**, começando por um serviço pequeno e de baixo risco, mantendo o fallback heurístico que já existe. Não reescrever o `SofiCopywriterService` (≈50 KB) de uma vez.
- Há um risco específico do projeto que o SDK não resolve sozinho: o scope de `BelongsToTenant` **não filtra nada fora de uma requisição autenticada** (fila, comando, webhook). Agentes e tools com acesso a dados precisam receber o tenant explicitamente (seção 5).

---

## 2. O que o SDK oferece [doc]

| Recurso | Como aparece | Relevância para o Nume |
| :--- | :--- | :--- |
| Agents | Classe com `instructions()`, `tools()`, `schema()`, `messages()`; gerada por `php artisan make:agent` | Alta: cada Sofi vira um agente |
| Saída estruturada | `HasStructuredOutput` + `schema()` com `$schema->string()->required()` etc.; resposta acessível como array | Alta: elimina o "retorne JSON" no texto e o `preg_replace` de ```` ```json ```` |
| Tools | `php artisan make:tool`; `description()`, `schema()`, `handle()` com validação | Alta: dá à Sofi dados reais do tenant |
| Provedores | Gemini, Anthropic, OpenAI, Azure, Bedrock, Groq, xAI, DeepSeek, Mistral, Ollama, OpenRouter, Cohere e compatíveis com OpenAI | Média: permite trocar de provedor sem reescrever |
| Provedor sob demanda | `Ai::build(['driver' => 'gemini', 'key' => $chave])` | Alta: chave por tenant vinda de `Setting` |
| Atributos | `#[Provider]`, `#[Model]`, `#[MaxSteps]`, `#[MaxTokens]`, `#[Temperature]`, `#[Timeout]`, `#[TopP]`; também `#[UseCheapestModel]`/`#[UseSmartestModel]` | Alta: substitui os timeouts soltos |
| Conversas | Trait `RemembersConversations`, tabelas `agent_conversations` e `agent_conversation_messages` | Média: ver riscos (seção 5) |
| Streaming | `->stream()`, protocolo Vercel AI SDK, AG-UI, iteração manual | Média: chat da Sofi com resposta progressiva |
| Fila e broadcast | `->queue()->then()->catch()`, `broadcastOnQueue`, atributo `#[WithoutBroadcasting]` | Alta: plano editorial em lote |
| Middleware de agente | Roda a cada step; recebe `PendingStep` com modelo e `usage`; pode encerrar com `StepResult::stop()` | Alta: custo/cota por tenant |
| Imagens | `Laravel\Ai\Image` com OpenAI, Gemini, xAI; `store()`, `queue()` | Baixa/Média: thumbnails e capas |
| Áudio | `Laravel\Ai\Audio` (TTS), transcrição (STT) | Média: prévia de locução, transcrição de podcast |
| Anexos | `Files\Document`, `Files\Image` no `prompt()` | Média: analisar briefing em PDF |
| Provider tools | `WebSearch`, `WebFetch`, `FileSearch`, `CodeExecution` | Média: comando `/research` com fonte real |
| Aprovação humana de tools | `Approvable`, `Approval::required()`, `Decision::approve()/reject()` | Alta para tools que publicam ou cobram |
| Sub-agentes | Agente retornado em `tools()` de outro, via `CanActAsTool` | Média |
| Failover | Troca automática de provedor/modelo em indisponibilidade ou rate limit (descrito na página institucional) | Alta: substitui as cascatas manuais |
| Testes | Fakes para agentes, imagem, áudio, embeddings, reranking e file stores (descritos na página institucional) | Alta |
| RAG | `SimilaritySearch::usingModel(Model::class, 'embedding', minSimilarity:, limit:)`, vector stores | Depende do banco (seção 6) |

Instalação [doc]:

```bash
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate   # cria agent_conversations e agent_conversation_messages
```

Chave no `.env` como `GEMINI_API_KEY` (o nome coincide com o que o projeto já usa).

**[a confirmar]** Requisitos de versão de PHP/Laravel, nome exato dos fakes de teste, detalhes de configuração do failover e a versão estável atual do pacote. As páginas lidas não trazem esses pontos. O projeto está em PHP `^8.2` e Laravel `^12.0`; o `composer require` vai acusar incompatibilidade, se houver. O `composer.json` usa `minimum-stability: dev` com `prefer-stable`, então confira a versão que o Composer escolheu e **fixe a versão** no `composer.json`.

---

## 3. Diagnóstico do código atual [código]

1. **Seis implementações da mesma chamada** ao Gemini: `SofiCopywriterService`, `SofiCopilotChatService`, `SofiBrandAnalystService`, `SofiSalesConsultantService`, `SofiTranslatorService`, `Quotes/SofiQuoteEstimatorService`. Cada uma repete: resolução de chave, cascata de modelos, timeout, tratamento de erro, fallback.
2. **Chave de API na query string** (`...:generateContent?key={$apiKey}`). Se o HTTP client lançar exceção (timeout, DNS), a mensagem costuma incluir a URL, e o código faz `Log::warning(... $e->getMessage())`. Resultado provável: **chave gravada em `storage/logs`** [a confirmar abrindo um log real, sem copiar a chave]. O correto é o header `x-goog-api-key` (o SDK cuida disso).
3. **Resolução de chave** `config(...) ?: Setting::get('gemini_api_key') ?: env('GEMINI_API_KEY')`. O `env()` fora de `config/` devolve `null` com `config:cache`; é código morto em produção.
4. **JSON por instrução de texto.** O prompt pede "retorne ESTRITAMENTE um objeto JSON", o código remove cercas ```` ``` ```` com regex e valida só `hook` e `body`. Não usa `responseSchema`. Qualquer variação do modelo vira fallback silencioso.
5. **Prompt montado por concatenação** com dados do cliente e do **dossiê extraído do site do cliente** (`BrandDnaCrawlerService`). Conteúdo de terceiros misturado às instruções é vetor de *prompt injection*.
6. **Cascata de modelos** em `config/services.php`: `gemini-3.5-flash-lite`, `gemini-3.8-flash`, `gemini-3.7-flash`, `gemini-3.5-flash`. **[a confirmar]** Não consegui verificar esses nomes. Se algum não existir, a requisição falha, o erro vai para `Log::warning` e o usuário recebe o texto heurístico sem perceber. Vale checar `storage/logs/laravel.log` por "AI response error".
7. **Divergência de fallback:** `SofiCopilotChatService` usa `gemini-2.5-flash` como default e `['gemini-2.0-flash','gemini-1.5-flash']` se a config faltar; os demais usam a família 3.x. Dois conjuntos de nomes convivendo.
8. **Sem custo/cota por tenant.** O manifesto do agente promete "gerenciar cotas da API do Gemini", mas não há contagem de tokens nem limite por produtora.
9. **Sem testes** de IA (não há fakes). **[a confirmar]** conteúdo de `tests/`.

---

## 4. Mapeamento: código atual → SDK

| Hoje | Com o SDK | Ganho |
| :--- | :--- | :--- |
| `callGemini()` repetido ×6 | Um `Agent` por caso de uso | Remove ~6 cascatas e ~6 timeouts soltos |
| "Retorne JSON" + regex | `HasStructuredOutput` + `schema()` | Contrato validado; menos fallback por JSON torto |
| Cascata manual de modelos | Failover do SDK | Menos código; **[a confirmar]** como configurar |
| `Setting::get('gemini_api_key')` | `Ai::build([... 'key' => $chaveDoTenant])` | Chave por produtora, criptografada, sem URL |
| Prompt em string única | `instructions()` (regras da Sofi) + `prompt()` (dados) | Separa instrução de dado não confiável |
| `SofiConversation`/`SofiMessage` | Manter e alimentar `messages()` | Mantém `tenant_id` e `cliente_id` |
| `generateBatchEditorialPlan()` síncrono | `->queue()->then()->catch()` | Sem estourar timeout de requisição |
| Nada | Middleware de agente com `usage` | Tokens e custo por tenant |
| Nada | Tool `TelemetryTool` somente leitura | Sofi responde com números reais do tenant |
| Nada | Fakes do SDK | Testes sem chamar a API |

### Candidatos por ordem de risco

1. **`SofiTranslatorService`** (≈11 KB, timeout 3 s). Escopo pequeno, saída simples. Melhor *spike*.
2. **`Quotes/SofiQuoteEstimatorService`** (≈9 KB). Saída naturalmente estruturada (itens, quantidades). Bom caso para `schema()`.
3. **`SofiBrandAnalystService`** (≈15 KB). Saída estruturada (dossiê) e entrada não confiável (site). Bom para validar a separação instrução/dado.
4. **`SofiCopilotChatService`** (≈11 KB). Streaming e histórico; tratar conversas e tenant com cuidado.
5. **`SofiSalesConsultantService`** (≈34 KB). Usa lista de packs do tenant: candidato a Tool.
6. **`SofiCopywriterService`** (≈50 KB). Por último, depois de o padrão estar provado. Quebrar em Agents menores (roteiro, SEO de vídeo, SEO de tenant, conteúdo de pack, plano editorial em lote).

---

## 5. Riscos e como mitigar

| Risco | Detalhe | Mitigação |
| :--- | :--- | :--- |
| **Vazamento entre tenants em fila** | `BelongsToTenant::currentTenantId()` só olha `auth('web')`/`auth('cliente')`. Em job, comando ou webhook devolve `null` e **o scope não é aplicado**; `creating` grava `tenant_id` nulo | Todo Agent/Tool/Job de IA recebe `tenant_id` no construtor e filtra explicitamente. Teste: dois tenants, job sem login, nenhuma linha do outro tenant |
| **`continue()` sem checar dono** [doc] | O SDK avisa que `continue`/`continueOrStart` não verificam propriedade da conversa | Usar as conversas do projeto (`SofiConversation`, com tenant e cliente) ou autorizar antes de continuar |
| **Tabelas do SDK sem tenant** | `agent_conversations` não nasce com `tenant_id` (a documentação lida não menciona) | Não usar `RemembersConversations` no multi-tenant sem estender a tabela |
| **Prompt injection via site do cliente** | Texto rastreado entra no prompt | Dados no `prompt()`, regras em `instructions()`; saída estruturada; nenhuma Tool de escrita acessível a esse agente |
| **Tool com efeito colateral** | Agente pode publicar, cobrar ou emitir fatura | Tools de escrita com `Approvable`; Tools de leitura sem acesso a `Setting` ou segredos |
| **Deriva de modelo** [doc] | `#[UseCheapestModel]`/`#[UseSmartestModel]` mudam entre releases | Usar `#[Model]` fixo vindo de `config('services.gemini.model')` |
| **Pacote jovem / mudanças de API** | Não verifiquei versão nem política de estabilidade | Fixar versão, ler *release notes* a cada update, manter camada fina entre controllers e Agents |
| **Perda do fallback heurístico** | O SDK faz failover entre provedores/modelos, não entrega texto de template | Manter try/catch com o motor heurístico atual em volta da chamada ao Agent |
| **Custo descontrolado** | Loops de tools e `MaxSteps` altos | `#[MaxSteps]`, `#[MaxTokens]`, middleware com `Stop::unless(...)` por tokens, limite por tenant |
| **LGPD/GDPR** | Dados de clientes (UE) vão a um provedor externo | Registrar provedor no aviso de privacidade; evitar enviar NIF/CIF, e-mails e dados de fatura em prompts |

---

## 6. RAG e embeddings: não decidir ainda

Ideia: guardar roteiros aprovados e o Brand DNA de cada cliente como embeddings e recuperar exemplos parecidos para calibrar novos roteiros (`SimilaritySearch`).

**[a confirmar]** O banco é MariaDB/MySQL via XAMPP. A ferramenta de similaridade lê uma coluna de embedding; a documentação lida não diz quais bancos suporta. Antes de qualquer trabalho:

1. Descobrir a versão do MariaDB/MySQL em uso e se há tipo vetorial nela.
2. Ler a seção de embeddings e *vector stores* da documentação do SDK (não veio nas páginas que consegui ler).
3. Comparar com a alternativa de *vector store* hospedado no provedor (`FileSearch`), que não exige mudar o banco, mas leva dado de cliente para fora.

Até lá, o ganho maior e mais barato está em Agents + saída estruturada + Tools de leitura.

---

## 6.1 Resultado da Fase 0 (23/10/2026)

Análise de `storage/logs/laravel.log` (1.034 entradas, 01/09 a 10/10/2026) e de `.phpunit.result.cache` (última execução em 10/10/2026).

- **Chave vazada: confirmado.** A chave do Gemini aparece em texto puro em **117 entradas** do log, todas da mesma chave, entre 25/09 e 06/10. Origem: `?key=` na URL e `$e->getMessage()` no `Log::warning` de 5 services. 69 dessas entradas vieram de **execuções de teste**. O log não é versionado (fica em `storage/logs`), mas a chave deve ser rotacionada.
- **Os testes chamam a API real.** 129 das entradas de IA são do ambiente `testing`; nenhum teste usa `Http::fake` para o Gemini. Isso consome cota, deixa a suíte lenta (391 s no total; três testes de lote/Brand DNA somam ~348 s) e vaza a chave. **Corrigido:** `phpunit.xml` agora força `GEMINI_API_KEY` vazia, e os seis services caem direto no fallback heurístico nos testes.
- **Nomes de modelo: aceitos pela API.** Em 339 entradas de erro de IA não há nenhum `404 NOT_FOUND` nem `INVALID_ARGUMENT`. Os erros são `429 RESOURCE_EXHAUSTED` (cota da conta esgotada: "You exceeded your current quota, please check your plan and billing details"), `503 UNAVAILABLE` e timeouts de cURL.
- **Causa real do fallback:** cota esgotada + timeouts curtos. `SofiTranslatorService` e `SofiSalesConsultantService` usam 3 s de timeout e concentram a maioria dos timeouts.
- **Linha de base de testes:** o cache listava 217 entradas, mas 6 eram de testes renomeados. O total real é **211 testes, nenhuma falha**; 83 *deprecations* e 38 *notices*.

Pendências do dono do projeto: rotacionar a chave no Google AI Studio, atualizar o `.env`, depois limpar `storage/logs/laravel.log`; rodar `php artisan test` para validar a mudança do `phpunit.xml`.

---

## 6.2 Resultado da Fase 1 (23/10/2026)

- PHP local atualizado para **8.4.27** (`C:\php84`, antes do XAMPP no PATH do sistema); `composer.json` em `"php": "^8.3"`. Todas as versões do `laravel/ai` exigem PHP ≥ 8.3.
- **`laravel/ai` v1.3.0** instalado, sem dependências novas. `config/ai.php` publicado com default `SOFI_AI_PROVIDER`. Migrations de conversa **não** publicadas.
- Divergência encontrada e corrigida: com provider em array, o SDK ignora `model`. O piloto passa o provider por nome (`sofi_<driver>`), coberto por teste.
- Testes: 211 → **216**, 0 falhas; suíte de 73 s (PHP 8.2) para **13 s** (PHP 8.4 + `GEMINI_API_KEY` vazia nos testes).
- Nenhuma chave no log após os testes.
- **Pendente: passo 5 (teste com a API real), aguardando a chave da Anthropic.** O `.env` já tem `SOFI_USE_AI_SDK=true`, `SOFI_AI_PROVIDER=anthropic` e `SOFI_AI_MODEL=claude-haiku-5-5`, mas a `ANTHROPIC_API_KEY` atual não é uma chave válida (não começa com `sk-ant-`). Com o interruptor ligado e chave inválida, o tradutor cai no fallback léxico.
- Resolvido depois da Fase 1: chave do Gemini no header `x-goog-api-key` e fora dos logs nos 6 services (`6d42f9b`) e mascarada no `laravel.log`; `composer audit` sem alertas (`league/commonmark` 2.10.4, `symfony/http-foundation` 7.4.21); teste do Brand DNA sem rede real; 0 deprecations do PHP 8.4 na suíte.
- Para a Fase 2: `Ai::build` com `name` fixo usa um registro compartilhado do processo (último `build()` vence). Usar nome por tenant e reconstruir o provider dentro de jobs.
- O app passou a rodar só via `composer run dev`/`php artisan serve` (PHP 8.4). **Produção deve usar PHP 8.4.**

## 6.3 Resultado da Fase 2 (23/10/2026)

- `App\Ai\SofiProvider`: único ponto de `Ai::build`, nome `sofi_{driver}` ou `sofi_{driver}_t{tenantId}`. Chave ainda global (`config('sofi.keys.*')`); chave por tenant via `Setting` marcada para depois.
- Tabela `ai_usage_logs` (migrada): `tenant_id` (FK `nullOnDelete`), `agent`, `provider` (driver), `model`, `input_tokens`, `output_tokens`, `cache_read_input_tokens`, `cache_write_input_tokens`, `invocation_id`, `created_at`. Sem texto de prompt ou resposta. Model `AiUsageLog` sem `BelongsToTenant` (exceção registrada no `AGENTS.md`), consulta por `forTenant()`.
- Middleware `RecordAiUsage` por step, com tenant e agente pelo construtor; falha ao gravar só gera log do tipo do erro. Ligado no `SofiTranslatorAgent` (tenant nulo) e no `SofiQuoteEstimatorAgent`.
- `SofiQuoteEstimatorService::estimate($text, ?Tenant)` migrado: `SofiQuoteEstimatorAgent` com `HasStructuredOutput` espelhando o array atual, saída por `normalizeParsedData()`, fallback heurístico em qualquer falha. Removido o parsing de ```` ```json ```` no caminho do SDK (o caminho antigo continua igual).
- Testes: 216 → **227**, 0 falhas, ~13 s. Inclui uso gravado no tenant certo com dois tenants.
- **Teste real com o Gemini (23/10/2026), em vez da Anthropic:** `gemini-3.5-flash-lite`, 2 chamadas, ambas HTTP 200 e sem retry (o script bloqueava qualquer requisição extra).
  - Tradutor: tradução natural e completa para `es` em ~6,4 s, sem fallback.
  - Orçamento (tenant 1, dados fictícios): saída estruturada aceita pelo flash-lite em ~5,4 s, sem fallback; todos os campos de `pedido` com o tipo esperado.
  - `ai_usage_logs`: tradutor com `tenant_id` nulo (216 de entrada / 31 de saída); orçamento com `tenant_id` 1 (318 / 342). Cache 0 nos dois.
  - Nenhuma chave em `storage/logs` (`key=`, `x-goog-api-key` e os 30 primeiros caracteres da chave: 0 ocorrências).
- O teste com a Anthropic ficou dispensado por ora (evitar tokens pagos). `SofiBrandAnalystService` (previsto na seção 7) ficou para a próxima fase.
- Detalhe herdado do caminho antigo: `normalizeParsedData()` devolve `motion` como inteiro `0` quando o valor é zero (`max(0, 0.0)`); os demais números saem como `float`.

---

## 7. Plano em fases

**Fase 0 — Preparação (½ dia)**
- Ver o log por "AI response error" e confirmar que os modelos de `config/services.php` existem.
- Verificar se a chave aparece em `storage/logs` e, se aparecer, **rotacioná-la**.
- Rodar `php artisan test` e anotar a linha de base.
- Backup do banco antes de migrar.

**Fase 1 — Spike (1 dia), branch separada**
- `composer require laravel/ai`, publicar config, migrar.
- Criar `app/Ai/Agents/TranslatorAgent.php` com `#[Provider(Lab::Gemini)]`, `#[Model(config)]`, `#[Timeout]`.
- `SofiTranslatorService` chama o Agent dentro do try/catch existente; o fallback heurístico fica.
- Teste com fake do SDK + teste do caminho de fallback.
- Critério de saída: mesma qualidade, menos código, nenhum teste quebrado, sem chave em log.

**Fase 2 — Núcleo reaproveitável (2–3 dias)**
- Middleware `RecordAiUsage` (tokens por tenant, agente, modelo) + tabela `ai_usage_logs` com `tenant_id` indexado.
- Config única `config/ai.php` + `services.gemini` como fonte do nome do modelo.
- Migrar `SofiQuoteEstimatorService` (primeiro `schema()` real) e `SofiBrandAnalystService` (separação instrução/dado).

**Fase 3 — Tools do tenant (3–5 dias)**
- `TelemetryTool` (somente leitura) sobre `TelemetryService`, com `tenant_id` explícito.
- `ListPacksTool` para o `SofiSalesConsultantService`.
- Migrar `SofiSalesConsultantService` e `SofiCopilotChatService` (mantendo `SofiConversation`).

**Fase 4 — Copywriter e lote (1–2 semanas)**
- Quebrar `SofiCopywriterService` em Agents; mover `generateBatchEditorialPlan` para `->queue()`.
- Remover as cascatas manuais quando o failover do SDK estiver configurado e testado.

**Fase 5 — Extras opcionais**
- Streaming no chat da Sofi; geração de imagem para capas; transcrição de podcast; RAG (depende da seção 6).

---

## 8. Laravel Boost (ferramenta de desenvolvimento, não de produção)

O Laravel Boost [doc: laravel.com/ai/boost] é um pacote `--dev` que dá ao agente (Claude Code, Gemini CLI, Cursor, Codex, Copilot) 15 ferramentas por MCP, entre elas: informações da aplicação, schema do banco, queries, inspetor de rotas, comandos Artisan, Tinker, configuração, **busca na documentação do Laravel ajustada aos pacotes instalados** e leitura de erros.

```bash
composer require laravel/boost --dev
php artisan boost:install
```

**[a confirmar]** Quais arquivos o `boost:install` gera/altera (a página não diz). Como já existem `AGENTS.md`, `CLAUDE.md` e `GEMINI.md` curados à mão, **faça backup antes**, rode o instalador e revise o diff. Se ele sobrescrever, restaure e copie só o que interessa.

Também existe o diretório de skills do Laravel em [skills.laravel.cloud](https://skills.laravel.cloud/) (citado na página do SDK). Não avaliei o conteúdo; vale olhar antes de criar skills próprias.

---

## 9. Decisões que precisam do dono do projeto

1. Aprovar a **Fase 0** agora? (verificar log, modelos e vazamento de chave).
2. Provedor único (Gemini) ou manter abertura para um segundo (por exemplo, Claude) como failover?
3. Chave de IA **por tenant** (cada produtora paga a sua) ou **da plataforma** com cota por plano?
4. Instalar o **Laravel Boost** nesta máquina de desenvolvimento?

---

## Fontes

- [Laravel AI SDK (página oficial)](https://laravel.com/ai)
- [Documentação do AI SDK](https://laravel.com/docs/ai-sdk)
- [Laravel Boost](https://laravel.com/ai/boost)
- [Guia de regras do Antigravity (AGENTS.md/GEMINI.md)](https://thepromptshelf.dev/blog/google-antigravity-agents-md-rules-guide-2026)
