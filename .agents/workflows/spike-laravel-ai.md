---
description: Instalar o Laravel AI SDK e validar o piloto do SofiTranslatorAgent (Fase 1 do estudo)
---

# Workflow: piloto do Laravel AI SDK

Contexto: `docs/estudo-laravel-ai-sdk.md` (seções 6.1 e 7) e `.agents/skills/laravel-ai-sdk/SKILL.md`.
O código do piloto **já está escrito** e desligado por padrão:

- `config/sofi.php`: interruptor `SOFI_USE_AI_SDK`, provedor, modelo, timeout e chaves.
- `app/Ai/Agents/SofiTranslatorAgent.php`: o agente.
- `app/Services/SofiTranslatorService.php`: método `translateViaSdk()`, chamado só com o interruptor ligado, com fallback léxico em qualquer falha.
- `phpunit.xml`: força chaves vazias e `SOFI_USE_AI_SDK=false` nos testes.

Ambiente: Windows + XAMPP + PowerShell. Responda em português do Brasil. **Pare e pergunte ao usuário** nos pontos marcados com ⛔.

## 0. Rede de segurança

1. Verifique se existe `.git` na raiz. Se não existir: ⛔ peça permissão para rodar `git init`, `git add -A` e `git commit -m "Estado antes do Laravel AI SDK"`. Confirme antes que `.env` está no `.gitignore` (está).
2. Rode `php artisan test` e anote o total de testes, falhas e o tempo. Esta é a linha de base. Se algo já falhar, ⛔ relate e pergunte se segue.

## 1. Instalar o pacote

1. `composer require laravel/ai`
2. Se o Composer acusar conflito de versão (PHP, Laravel ou dependência), ⛔ **não force**: relate a mensagem e pare.
3. Anote a versão instalada (`composer show laravel/ai`) e fixe-a no `composer.json` (por exemplo `"laravel/ai": "^X.Y"`), porque o projeto usa `minimum-stability: dev`.

## 2. Publicar configuração e migrations

1. `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`
2. Abra `config/ai.php` e confira: nome do driver do Gemini e da Anthropic, variáveis de ambiente esperadas e modelo padrão de cada provedor.
3. ⛔ Antes de `php artisan migrate`: mostre ao usuário quais tabelas serão criadas (esperado: `agent_conversations` e `agent_conversation_messages`) e lembre que elas **não têm `tenant_id`** e não serão usadas pela Sofi neste piloto. Só migre com autorização.

## 3. Conferir as assinaturas reais (o código do piloto foi escrito pela documentação)

Leia em `vendor/laravel/ai/src` e confirme, ajustando o piloto se precisar:

- [ ] Namespaces de `Laravel\Ai\Contracts\Agent` e `Laravel\Ai\Promptable`.
- [ ] Assinatura de `prompt()`: aceita os argumentos nomeados `provider`, `model` e `timeout`?
- [ ] `Laravel\Ai\Ai::build([...])` aceita `['driver' => 'gemini' | 'anthropic', 'key' => ...]`, e o resultado pode ir em `provider: [ ... ]`?
- [ ] `(string) $response` devolve o texto da resposta?
- [ ] Como fazer fake de um agente nos testes (procure por `fake` na classe/trait do agente e na documentação de *Testing*).

Se algo divergir, corrija **somente** `app/Ai/Agents/SofiTranslatorAgent.php` e `translateViaSdk()` em `app/Services/SofiTranslatorService.php`. Não altere outros services.

## 4. Testes do piloto

Crie `tests/Feature/SofiTranslatorAgentTest.php` usando o fake real do SDK (passo 3), com estes casos:

1. Interruptor ligado + chave de teste + fake respondendo `"Hola mundo"` → `translate('Olá mundo, texto fora do dicionário', 'es')` devolve `"Hola mundo"`.
2. Interruptor ligado + fake lançando exceção → devolve o resultado do fallback léxico (não lança erro).
3. Interruptor ligado sem chave → não chama o agente e cai no fallback.
4. Interruptor desligado → o agente nunca é chamado.

Use `config(['sofi.use_ai_sdk' => true, 'sofi.keys.gemini' => 'chave-de-teste'])` dentro do teste e limpe o cache (`Cache::flush()`), porque o service guarda traduções por 60 dias. Nenhum teste pode chamar a API real.

Depois rode:

```powershell
php artisan test
vendor\bin\pint app\Ai app\Services\SofiTranslatorService.php config\sofi.php tests\Feature\SofiTranslatorAgentTest.php
```

Compare com a linha de base do passo 0. Os 217 testes antigos precisam continuar passando.

## 5. Teste manual com a API real (opcional)

1. ⛔ Peça ao usuário para colocar no `.env` (você **não** lê nem edita o `.env`):
   - `SOFI_USE_AI_SDK=true`
   - `SOFI_AI_PROVIDER=anthropic` e `ANTHROPIC_API_KEY=...`, **ou** `SOFI_AI_PROVIDER=gemini` com a chave nova do Gemini
   - `SOFI_AI_MODEL=` com o nome exato do modelo, conferido na documentação do provedor (não invente)
2. `php artisan optimize:clear`
3. `php artisan tinker` → `app(\App\Services\SofiTranslatorService::class)->translate('Nossa produtora entrega roteiros com retenção acima da média', 'es')`
4. Confira em `storage/logs/laravel.log` que **nenhuma chave** foi gravada.

## 6. Relatório final

Entregue ao usuário: versão instalada, ajustes feitos no piloto (com diff), resultado dos testes antes/depois e tempo da suíte. Termine com a decisão binária: **seguir para a Fase 2** (middleware de uso por tenant + `SofiQuoteEstimatorService`) **ou ajustar o piloto**.
