---
name: orcamentos
description: Módulo de orçamentos do Nume App — pedido do cliente no portal a partir do plano aprovado, calculadora da equipe (QuoteCalculatorService), leitura da Sofi (SofiQuoteEstimatorService), orçamento de anúncios, PDF, link público de aprovação, dados de faturação criptografados e fatura. Carregar sempre que a tarefa envolver Quote, calculadora, proposta, aprovação pública ou faturação de orçamento.
---

# Módulo de Orçamentos

## 1. Fluxo (decisão do analista, 26/10/2026)

1. Plano de marketing aprovado pelo cliente (skill `portal-cliente`).
2. **Pedido no portal** (`portal.quotes.request`): vem preenchido com o plano e a anamnese (vídeos, tipo de vídeo, redes, anúncios). O `QuoteRequestService::createFromPortal()` cria o `Quote` com status `requested`:
   - **a calculadora recebe a solicitação**: `videos` = pedido; `edicao` = vídeos × `video_types.edit_hours_per_video` (ou o padrão `quote_edit_hours_per_video`); pré, gravação, motion, filmmakers e drone vêm da leitura da Sofi (`SofiQuoteEstimatorService`);
   - `request_details` guarda o pedido estruturado; `marketing_plan_id` liga ao plano;
   - não duplica pedido em aberto do mesmo plano e tipo (`openRequest()`).
3. **Equipe** (`quotes.*`, `QuotePolicy`): aba "Pedidos de clientes"; a tela do orçamento mostra o pedido; envia a proposta (`send`), que gera `approval_token` e fixa `valid_until` se estiver vazio.
4. **Aprovação pública** (`/orcamento/{token}`): o cliente informa os **dados de faturação** antes de escolher o pagamento. Depois, `PackCheckoutDispatcher` → gateway → confirmação só por webhook ou confirmação validada (`PackPaymentConfirmer`, intocado).
5. **Fatura**: emitida na confirmação (`InvoiceService`), com os `billing_*` copiados para a compra; o portal lista o PDF em Orçamentos.

## 2. Tipos de orçamento (`quotes.kind`)

- `production` (padrão): `QuoteCalculatorService::calculate()`, sinal 50% e saldo na entrega.
- `ads` (`Quote::KIND_ADS`): serviço da NUME (criativos + gestão mensal) por `calculateAds()`; **pagamento integral**; a verba de anúncio (`verba_mensal`) é **só informativa** (`verba_total_info`), paga pelo cliente direto à plataforma, fora do total, do PDF de cobrança e da fatura. Não gera cota de vídeos (`video_quantity = 0`). Plataformas válidas: `QuoteCalculatorService::AD_PLATFORMS`.
- Pedido do portal com "quero anúncios" cria um segundo `Quote` `ads` em `requested`.

## 3. Dados de faturação

- `App\Support\BillingProfile`: regras (NIF, endereço, CP e cidade obrigatórios para `empresa`/`autonomo`; `particular` só nome), `save()` e `purchaseAttributes()`.
- `App\Support\SpanishTaxId`: NIF/DNI, NIE e CIF com dígito de controle (só para país `ES`).
- Em `clientes`, nome fiscal, NIF e endereço usam cast `encrypted` e estão em `$hidden`. A compra recebe cópia em claro, porque a fatura é documento fiscal.
- A página pública abre só com o token: **nunca** mostra os dados salvos (só "NIF terminado em …", com a opção `use_saved_billing`). O cliente logado edita em `portal.billing.*`.

## 4. Regras

- `cliente_id` do orçamento: `Rule::exists('clientes','id')->where('tenant_id', ...)`.
- Status: `requested`, `draft`, `sent`, `accepted`, `rejected`, `expired`. Telas usam `match` com `default`.
- Testes: Gemini desligado (`services.gemini.key`/`sofi.keys.gemini` = null) ou pelo fake do SDK; PDF de fatura só com `Storage::fake('local')`.
