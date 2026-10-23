---
name: financeiro-stripe
description: Conhecimento do módulo Financeiro, Multi-gateway & Motor Fiscal — Assinatura SaaS para produtoras (Billing), Stripe Connect Express (repasse), TPV Virtual BBVA Redsys (Bizum e Cartão de crédito/débito), Motor Fiscal (IVA 21%, IRPF, NIF/CIF), Pacotes de Vídeos (Packs, PackPurchases) e Webhooks idempotentes. Carregar sempre que a tarefa envolver pagamentos, Stripe, Redsys, Bizum, impostos, checkout ou pacotes.
---

# Módulo Financeiro, Multi-gateway & Motor Fiscal

**Responsabilidade:** Gerenciar todos os fluxos monetários e fiscais da plataforma:
1. **Monetização SaaS:** cobrança de assinaturas de produtoras (Stripe Billing).
2. **E-commerce de Serviços Audiovisuais (Storefront):** vitrine pública com checkout direto em 1 passo, upsell de add-ons e split/recebimento.
3. **Multi-gateway & Comissões:**
   - **BBVA TPV Virtual (Redsys) por Produtora:** Credenciais criptografadas por tenant em `tenant_settings`. Foco na Espanha/União Europeia via **Bizum** (celular) e **Cartão** com taxas interbancárias ultrabaixas.
   - **Stripe Connect Express:** Foco global, split na fonte com comissão retida imediatamente (`platform_fee_status = 'collected_at_gateway'`).
   - **Comissões fora da Stripe (Decisão B):** Vendas via Redsys próprio da produtora geram `application_fee_amount` com `platform_fee_status = 'pending_billing'`, faturadas posteriormente no SaaS.
4. **Motor Fiscal por Produtora (`TenantFiscalSettings` & `TaxCalculationService`):**
   - Dados fiscais próprios da produtora (emissor legal da fatura perante o cliente final).
   - Cálculo automático de **IVA geral espanhol (21%)**, regimes especiais (Canárias 0%, Ceuta/Melilla 0%), operações intracomunitárias B2B (Inversión del sujeto pasivo 0%) e exportações fora da UE.
   - Numeração atômica sequencial por produtora e série (`invoice_sequences` com `lockForUpdate`).
   - Armazenamento isolado de PDFs de faturas em `invoices/{tenant_id}/{invoice_number}.pdf` com `invoice_snapshot` imutável.

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
  - `invoice_number`, `invoice_series`, `invoice_generated_at`, `invoice_path`, `invoice_snapshot`.
- **Comissão da Plataforma:**
  - `application_fee_amount` (valor em EUR da comissão de uso).
  - `platform_fee_status` (`not_applicable`, `collected_at_gateway`, `pending_billing`, `billed`, `waived`).
- `status` (`pending`, `paid`, `failed`, `refunded`), `video_quantity`, `videos_used`, `addons_purchased`, `addons_snapshot`, `paid_at`.

---

## 2. BBVA TPV Virtual (Redsys / Bizum) Multi-tenant & Segurança DevSecOps

- **Disponibilidade:** Checada exclusivamente via [`App\Services\Payment\PaymentMethodAvailability::forTenant($tenant)`](file:///c:/xampp/htdocs/nume_app/app/Services/Payment/PaymentMethodAvailability.php). Nunca na classe `Setting`.
- **Service:** [`App\Services\Payment\RedsysService::forTenant($tenant)`](file:///c:/xampp/htdocs/nume_app/app/Services/Payment/RedsysService.php). Credenciais próprias em `tenant_settings` (`redsys_merchant_code`, `redsys_terminal`, `redsys_secret_key`, `redsys_environment`).
- **Segurança do Webhook (`POST /webhooks/redsys`):**
  1. Extração segura de `Ds_Order` dos parâmetros sem confiar no payload.
  2. Localização da compra e tenant dono no banco (`PackPurchase::withoutGlobalScope('tenant')`).
  3. Instanciação de `RedsysService::forTenant($tenant)`.
  4. Validação criptográfica da assinatura HMAC SHA-256 usando estritamente a chave daquele tenant.
  5. Validação de `Ds_MerchantCode` contra o FUC cadastrado daquele tenant.
  6. Confirmação segura no `PackPaymentConfirmer`.
- **Compliance PCI-DSS:** Redirecionamento seguro (**PCI SAQ-A** via [`redsys_redirect.blade.php`](file:///c:/xampp/htdocs/nume_app/resources/views/storefront/redsys_redirect.blade.php)).
- **Configuração no Admin:** `GET|POST /admin/settings/redsys` ([`TenantRedsysSettingsController`](file:///c:/xampp/htdocs/nume_app/app/Http/Controllers/Admin/TenantRedsysSettingsController.php)), restrito a admin do mesmo tenant, sem CSS inline.

---

## 3. Motor Fiscal & Faturas por Produtora (`TenantFiscalSettings`)

- **Service:** [`App\Services\Billing\TenantFiscalSettings`](file:///c:/xampp/htdocs/nume_app/app/Services/Billing/TenantFiscalSettings.php)
- **Configuração no Admin:** `GET|POST /admin/settings/fiscal` ([`TenantFiscalSettingsController`](file:///c:/xampp/htdocs/nume_app/app/Http/Controllers/Admin/TenantFiscalSettingsController.php)), restrito a admin do mesmo tenant.
- **Numeração Atômica:** [`App\Services\Billing\InvoiceService`](file:///c:/xampp/htdocs/nume_app/app/Services/Billing/InvoiceService.php) usando model `InvoiceSequence` com `lockForUpdate` por tenant e série fiscal.
- **Snapshot Imutável:** Todo faturamento gera `invoice_snapshot` gravado em JSON no `pack_purchases` para garantir integridade histórica perante auditorias da AEAT.

---

## 4. Stripe Connect & Assinaturas SaaS

- Onboarding express de produtoras via Stripe Connect.
- Checkout global Stripe integrado com linha de impostos.
- Webhook `POST /stripe/webhook` com validação de assinatura e idempotência.
- Marcação atômica de `platform_fee_status = 'collected_at_gateway'` na confirmação do split.

---

## 5. Painel Fiscal & Exportação para Gestoría (AEAT)

- **Libro de Facturas Emitidas:** `GET /admin/invoices` com consolidação de base imponible, cuotas de IVA repercutido (Modelo 303), retenciones IRPF (Modelo 111/130) e saldo retido em custódia.
- **Exportação CSV para Asesorías Españolas:** `GET /admin/invoices/export` com encoding UTF-8 BOM, delimitador `;` e colunas fiscais completas.
- **Emissão e Download Oficial de PDF:** `GET /admin/invoices/{purchase}/download` e `GET /portal/purchases/{purchase}/factura` (Real Decreto 1619/2012).
