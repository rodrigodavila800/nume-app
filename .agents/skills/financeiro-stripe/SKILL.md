---
name: financeiro-stripe
description: Conhecimento do módulo Financeiro, Multi-gateway & Motor Fiscal — Assinatura SaaS para produtoras (Billing), Stripe Connect Express (repasse), TPV Virtual BBVA Redsys (Bizum e Cartão de crédito/débito), Motor Fiscal (IVA 21%, IRPF, NIF/CIF), Pacotes de Vídeos (Packs, PackPurchases) e Webhooks idempotentes. Carregar sempre que a tarefa envolver pagamentos, Stripe, Redsys, Bizum, impostos, checkout ou pacotes.
---

# Módulo Financeiro, Multi-gateway & Motor Fiscal

**Responsabilidade:** Gerenciar todos os fluxos monetários e fiscais da plataforma:
1. **Monetização SaaS:** cobrança de assinaturas de produtoras (Stripe Billing).
2. **E-commerce de Serviços Audiovisuais (Storefront):** vitrine pública com checkout direto em 1 passo, upsell de add-ons e split/recebimento.
3. **Multi-gateway:**
   - **BBVA TPV Virtual (Redsys):** Foco na Espanha/União Europeia via **Bizum** (instantâneo no celular) e **Cartão** com taxas interbancárias ultrabaixas (0,3% a 0,8%).
   - **Stripe:** Foco global, Apple Pay e cartões internacionais.
4. **Motor Fiscal (TaxCalculationService):** cálculo automático de **IVA geral espanhol (21%)**, regimes especiais (Canárias 0%, Ceuta/Melilla 0%), operações intracomunitárias B2B (Inversión del sujeto pasivo 0%) e exportações fora da UE.

---

## 1. Dicionário de Dados Atualizado

### `pack_purchases`
- `id`, `tenant_id`, `cliente_id`, `pack_id`.
- `payment_gateway` (`bizum`, `redsys_card`, `stripe`).
- `gateway_order_reference` (código de 12 caracteres do pedido Redsys).
- `gateway_metadata` (json da resposta bancária).
- `stripe_checkout_session_id`, `stripe_payment_intent_id`.
- **Desmembramento Fiscal:**
  - `subtotal` (Base imponible: pack + add-ons).
  - `tax_rate` (alíquota IVA, ex: 21.00).
  - `tax_amount` (cota tributária do IVA).
  - `irpf_rate`, `irpf_amount` (retenções IRPF se autônomo).
  - `total_amount` / `amount` (total líquido cobrado no gateway com IVA).
- **Faturamento Legal:**
  - `billing_type` (`particular`, `empresa_autonomo`).
  - `billing_tax_id` (NIF/CIF/DNI/NIE).
  - `billing_legal_name` (Razão Social / Nome Fiscal).
  - `billing_address`, `billing_postal_code`, `billing_city`, `billing_country`.
- `application_fee_amount`, `status` (`pending`, `paid`, `failed`, `refunded`), `video_quantity`, `videos_used`, `addons_purchased`, `paid_at`.

---

## 2. BBVA TPV Virtual (Redsys / Bizum) & Segurança DevSecOps

- **Service:** [`App\Services\Payment\RedsysService`](file:///c:/xampp/htdocs/nume_app/app/Services/Payment/RedsysService.php)
- **Protocolo Criptográfico:** HMAC SHA-256 com derivação de chave 3DES (`openssl_encrypt` DES-EDE3-CBC com IV de 8 zeros).
- **Compliance PCI-DSS:** Redirecionamento seguro (**PCI SAQ-A** via [`redsys_redirect.blade.php`](file:///c:/xampp/htdocs/nume_app/resources/views/storefront/redsys_redirect.blade.php)). Dados de cartão nunca tocam nossos servidores.
- **Webhook Notificação Assíncrona:** `POST /webhooks/redsys` ([`RedsysWebhookController`](file:///c:/xampp/htdocs/nume_app/app/Http/Controllers/Webhooks/RedsysWebhookController.php)).
  - Isento de CSRF em `bootstrap/app.php`.
  - Validação estrita de assinatura anti-tampering com `hash_equals`.
  - Baixa automática do pedido e provisionamento imediato de créditos.
- **Métodos no Redsys:**
  - `Ds_Merchant_PayMethods = 'z'` -> Ativa **Bizum**.
  - `Ds_Merchant_PayMethods = 'T'` -> Ativa **Cartão**.

---

## 3. Motor Fiscal (`TaxCalculationService`)

- **Service:** [`App\Services\Billing\TaxCalculationService`](file:///c:/xampp/htdocs/nume_app/app/Services/Billing/TaxCalculationService.php)
- **Regras:**
  - Espanha Peninsular: 21% IVA.
  - Canárias (CP 35xxx, 38xxx), Ceuta (51xxx), Melilla (52xxx): 0% IVA.
  - UE Intra-comunitário B2B (com NIF-IVA/VIES): 0% IVA (Reverse Charge).
  - Exportação fora da UE (Brasil, EUA): 0% IVA.

---

## 4. Stripe Connect & Assinaturas SaaS

- Onboarding express de produtoras via Stripe Connect.
- Checkout global Stripe integrado com linha de impostos.
- Webhook `POST /stripe/webhook` com validação de assinatura e idempotência.

---

## 5. Painel Fiscal & Exportação para Gestoría (AEAT)

- **Libro de Facturas Emitidas:** `GET /admin/invoices` com consolidação de base imponible, cuotas de IVA repercutido (Modelo 303), retenciones IRPF (Modelo 111/130) e **Saldo Retido em Custódia Ativa (50/50)** para previsão de receita.
- **Filtros Contábeis & Modalidade:** Por exercício fiscal, trimestre (1T, 2T, 3T, 4T), modalidade de cobrança (`split_50_50`, `pending_escrow`, `paid_escrow`, `full_upfront`, `subscription`), pasarela (Bizum, Tarjeta, Stripe) e busca textual por NIF/CIF.
- **Exportação CSV para Asesorías Españolas:** `GET /admin/invoices/export` com encoding UTF-8 BOM, delimitador `;` e colunas fiscais completas incluindo `Modalidad_Cobro`, `Deposito_Cobrado_EUR`, `Saldo_Entrega_EUR` e `Estado_Saldo`.
- **Emissão e Download Oficial de PDF:** `GET /admin/invoices/{purchase}/download` e `GET /portal/purchases/{purchase}/factura` (Real Decreto 1619/2012).
