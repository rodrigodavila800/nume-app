# CLAUDE.md — Nume App

@AGENTS.md

As instruções acima (`AGENTS.md`) valem para todos os agentes do projeto. Abaixo, apenas o que é específico do Claude Code.

## Carregamento de contexto

- Skills e regras do projeto ficam em `.agents/skills/*/SKILL.md` e `.agents/rules/*.md`. **Leia a skill do módulo antes de editar** (tabela no `AGENTS.md`). Elas são lidas sob demanda, não importadas aqui, para não gastar contexto à toa.
- Para trabalho visual (Blade/Tailwind), leia `.agents/rules/web-design-claude.md` antes de começar e confira o checklist ao final.
- Para qualquer feature de IA, leia `.agents/skills/laravel-ai-sdk/SKILL.md` e `docs/estudo-laravel-ai-sdk.md`.
- Para instalar o SDK e validar o piloto, siga `.agents/workflows/spike-laravel-ai.md` passo a passo, inclusive as paradas marcadas com ⛔.

## Como o Claude deve operar aqui

- Use **Plan Mode** (ou apresente o plano e espere) para: migrations, `BelongsToTenant`, Policies, pagamentos (Stripe/Redsys), webhooks e qualquer refatoração que toque mais de 5 arquivos.
- Antes de dar uma tarefa por concluída: `php artisan test` e `vendor\bin\pint` nos arquivos alterados. Se não conseguir executar, diga isso em vez de afirmar que passou.
- Antes de criar um service/trait/helper, procure se já existe (`app/Services`, `app/Traits`). Seis services já repetem a chamada HTTP ao Gemini: **não crie uma sétima cópia**.
- Ao mexer em view Blade, rode uma busca por `style="` nos arquivos alterados antes de finalizar.
- Nunca leia, exiba ou copie `.env`, `.env.backup` ou `.env.production` (para bloquear também no nível da ferramenta, copie `docs/claude-settings.sugerido.json` para `.claude/settings.json`). Para saber quais variáveis existem, use `.env.example`.
- Arquivos soltos na raiz (`seed_*_test.php`, `test_*.ps1`, `OrçamentosNume.html`, `agente.md`, `instrucoes.md`, `pacotes.txt`) são rascunhos/material de referência. Não os trate como código de produção.

## Skills do Claude (nível usuário) úteis neste projeto

- `frontend-design` e `web-design-guidelines`: ao criar ou revisar telas e checar acessibilidade.
- `searchfit-seo:*` (ex.: `on-page-seo`, `schema-markup`): para as páginas públicas da vitrine e para o SEO que a Sofi gera.
- Para documentação oficial do Laravel dentro do agente, instale o **Laravel Boost** (`composer require laravel/boost --dev` e `php artisan boost:install`). Ele expõe busca de docs versionada, leitura de schema, inspeção de rotas e tinker por MCP. Veja o passo a passo no estudo técnico.

## Comunicação

Responda em português do Brasil. Siga a regra final do `AGENTS.md`: nunca terminar de forma passiva; feche com o próximo passo ou uma decisão binária.
