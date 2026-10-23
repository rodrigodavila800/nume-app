# Decisões de negócio — Nume App

Registro das decisões do dono do produto. Atualizar quando mudar.

| Data | Tema | Decisão | Consequência técnica |
| :--- | :--- | :--- | :--- |
| 23/10/2026 | Hospedagem | VPS Hostinger KVM 2 (Alemanha), CloudPanel, PHP 8.4 | PHP ≥ 8.3 obrigatório (laravel/ai) |
| 23/10/2026 | Domínio | Domínio `.es` em nome do dono do negócio (CIF ou DNI/NIE) | `APP_URL` e URLs de Stripe, Redsys e Google OAuth no deploy |
| 23/10/2026 | IA de teste | Gemini (nível gratuito) para testes reais; Anthropic só depois | `SOFI_AI_PROVIDER=gemini`; dados fictícios nos testes |
| 23/10/2026 | Impostos | IVA/IRPF **por produtora**; global vira só padrão | `tenant_settings` + `TenantFiscalSettings` |
| 23/10/2026 | Emissor da fatura | **A produtora** emite (CIF/NIF, série e numeração próprias) | Numeração atômica por tenant e série; `invoice_snapshot` |
| 23/10/2026 | Moeda | Padrão **EUR**; Redsys/Bizum/transferência só em EUR; BRL só via Stripe | `packs.currency` default `eur` |
| 23/10/2026 | Transferência bancária | Nova forma de pagamento: solicitud de pago com IBAN; factura só na confirmação manual do staff | `bank_transfer`, `PackPaymentConfirmer::confirmBankTransfer` |
| 23/10/2026 | Redsys | **TPV por produtora**; plataforma não recebe e repassa (risco PSD2) | Credenciais Redsys em `tenant_settings`; webhook resolve o tenant antes de validar |
| 23/10/2026 | Assinaturas de pacote | **Fora do lançamento**; voltam numa fase fiscal com fatura por ciclo | Checkout recusa pacote recorrente |
| 23/10/2026 | Comissão fora da Stripe | **Item de uso (metered) na assinatura SaaS** da produtora | Registrar comissão devida por compra agora; cobrança em fase própria |

## Pendente com a gestoria
- A retenção de IRPF se aplica a clientes particulares? (hoje: nunca retém para particular)
- IRPF em todos os ciclos de assinatura (quando as assinaturas voltarem).
- Emissão de factura no pedido vs. na confirmação (hoje: na confirmação).

## Regra de modelo para economizar tokens
- Opus 5.5: planejar, pagamentos, webhooks, segurança, migrations, fiscal.
- Sonnet 5.5: implementar plano aprovado com testes.
- Haiku 5.5: docs, rodar testes/commits, ajustes pequenos.
