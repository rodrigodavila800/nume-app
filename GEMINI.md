# GEMINI.md — Nume App (Antigravity / Gemini)

O Antigravity também lê o `AGENTS.md` da raiz. **Todas as regras do projeto estão lá**; este arquivo só traz o que é específico do Gemini e não repete nada de lá, para não conflitar nem gastar contexto.

## Onde está o contexto

- Regras globais: `.agents/rules/*.md`
- Skills por módulo: `.agents/skills/*/SKILL.md` (descoberta pela descrição; carregue a do módulo em que vai trabalhar)
- Estudo técnico do SDK de IA: `docs/estudo-laravel-ai-sdk.md`

## Comportamento esperado no Antigravity

- Responda sempre em **português do Brasil**.
- Ambiente é **Windows + XAMPP + PowerShell**. Use `npm.cmd` e barras invertidas nos comandos. Não use comandos bash que não existam no PowerShell.
- Antes de escrever código, diga em 3–5 linhas o plano e quais arquivos vai tocar. Mudanças em migrations, `BelongsToTenant`, Policies, pagamentos e webhooks exigem confirmação do usuário antes de gravar.
- Não abra, imprima nem copie `.env`, `.env.backup` ou `.env.production`. Use `.env.example`.
- Não invente nomes de modelo Gemini. Os modelos usados estão em `config/services.php` (`services.gemini.model` e `fallback_models`). Se precisar trocar, peça ao usuário para confirmar o nome atual na documentação do Google AI.
- Ao terminar, rode `php artisan test` e informe o resultado real. Se não puder rodar, diga que não rodou.
- Não mude arquivos fora do escopo pedido (por exemplo, não reformate o projeto inteiro).

## Workflows (`.agents/workflows/`)

Workflows são procedimentos acionados por comando no chat do Antigravity.

- `/spike-laravel-ai`: instala o Laravel AI SDK e valida o piloto do `SofiTranslatorAgent` (Fase 1 do estudo).

Outras sugestões, a criar só com aprovação do usuário: `/auditar-css-inline`, `/auditar-tenant` (models sem `BelongsToTenant`, controllers sem `authorize`) e `/novo-agent-ia`.

## Modelo Claude dentro do Antigravity

Se o modelo selecionado no Antigravity for o Claude, as regras continuam vindo daqui e do `AGENTS.md`. O `CLAUDE.md` só é lido pelo Claude Code (extensão ou CLI). Por isso as regras do projeto ficam no `AGENTS.md`, comum a todos.
