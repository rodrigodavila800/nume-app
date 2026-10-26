---
name: crm
description: CRM e suporte do Nume App — funil de leads por produtora (Lead, LeadActivity, LeadService, LeadAutomation), regras automáticas por cadastro e orçamento, chamados de suporte (SupportTicket, SupportService) no portal e na equipe, e direitos RGPD (exportar, anonimizar, prazo de conservação). Carregar sempre que a tarefa envolver leads, funil de vendas, suporte, chamados ou dados pessoais de contatos.
---

# CRM & Suporte

Decisão de 26/10/2026: o CRM é **da NUME e das produtoras** (por tenant) e inclui o **suporte**.

## 1. Funil (`leads`, `lead_activities`)
- Etapas: `novo → contato → qualificado → proposta → negociacao → ganho | perdido` (`Lead::STAGES`). Perdido exige motivo.
- Mudança de etapa **só** por `LeadService::moveTo()` (grava no histórico). `findOrCreate()` não duplica: reaproveita lead do mesmo e-mail ou cliente no tenant.
- Contato (`call`, `email`, `meeting`, `whatsapp`) atualiza `last_contact_at`; aberto sem contato há `Lead::STALE_DAYS` (7) dias aparece sinalizado.
- Telas: `crm.leads.*` (funil em colunas, ficha, histórico). `LeadPolicy`: editor vê e edita; dados pessoais só admin. Responsável só da mesma produtora.

## 2. Regras automáticas (`LeadAutomation`)
- Cadastro (portal, Google, vitrine, equipe) → lead `novo` com a origem. Base legal: `contract` para autocadastro, `legitimate_interest` para cadastro manual.
- `Quote::saved` (criação ou mudança de status): `requested → qualificado`, `sent → proposta` (valor estimado = total), `accepted → ganho`, `rejected/expired → perdido`.
- Regra automática **nunca reabre** lead ganho/perdido. Qualquer falha só vai para o log (`crm.automation_failed`), nunca quebra cadastro ou orçamento.
- O chat de vendas da vitrine ainda não captura contato, então não cria lead.

## 3. Suporte (`support_tickets`, `support_ticket_messages`)
- `SupportService`: `open()`, `clienteReply()` (reabre), `teamReply()` (deixa `aguardando_cliente` e assume o chamado), `changeStatus()`. Nota interna (`internal = true`) não muda status e **nunca** aparece no portal (`publicMessages()`).
- Equipe: `support.*` (fila por prioridade). Portal: `portal.support.*`, preso a tenant + cliente (404 para os outros). Chamado fechado não aceita resposta.
- O chat da Sofi no portal é a porta do suporte: atalho "Abrir chamado" (`?origem=chat` grava `source = chat`).

## 4. RGPD
- Ficha guarda `legal_basis` e `consent_at` (consentimento só com data e hora).
- Admin: `crm.leads.export` (JSON com dados e histórico) e `crm.leads.anonymize` (exige confirmação; apaga nome, contato, notas e o texto do histórico; mantém etapa, origem e valor).
- `crm:anonymize-lost` (diário, 03:30): perdidos há mais de `crm.anonymize_lost_after_months` (24) meses.
