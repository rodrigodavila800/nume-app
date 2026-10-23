---
name: core-saas
description: Conhecimento do módulo Core SaaS & Plataforma — Multi-tenancy (Tenants, Users), isolamento por BelongsToTenant, autenticação de produtoras, RBAC/Policies, configurações de gateway criptografadas (Setting) e I18n (pt_BR, es). Carregar sempre que a tarefa envolver autenticação de staff, gerenciamento de tenants, configurações globais ou isolamento de banco.
---

# Módulo Core SaaS & Plataforma

**Responsabilidade:** Identity, Tenancy, Authorization (Policies), Platform Settings com criptografia AES-256 e Internacionalização. É a espinha dorsal sobre a qual todos os outros módulos de negócio operam.

---

## 1. Dicionário de Dados

- **`tenants`**: `id`, `name`, `slug`, `description`, `status` (`active`, `suspended`, etc.), `primary_contact_email`, `company_cnpj`, `subscription_plan`, `subscribed_at`, `subscription_expires_at`, `stripe_account_id`, `stripe_account_status`, `charges_enabled`, `payouts_enabled`, `details_submitted`, `social_*` (instagram, youtube, linkedin, tiktok, whatsapp, facebook), `seo_*` (meta_title, meta_description, og_image, canonical_url, last_generated_at).
- **`users`**: `id`, `tenant_id`, `name`, `email`, `password`, `role` (`admin`, `producer`, `editor`), `remember_token`, `created_at`, `updated_at`.
- **`settings`**: `id`, `tenant_id` (nullable se global), `key`, `value` (criptografado em banco com `Crypt::encryptString`), `group`, `is_encrypted`.

---

## 2. Helpers e Métodos de Domínio (`Tenant.php`)

| Método | Assinatura / Retorno | Descrição |
| :--- | :--- | :--- |
| `isActive()` | `bool` | Retorna se o tenant está com status ativo. |
| `isSubscriptionValid()` | `bool` | Retorna se a assinatura SaaS da produtora está dentro do prazo de validade. |
| `canReceivePayments()` | `bool` | Retorna se o Stripe Connect está configurado e apto a receber cobranças (`charges_enabled`). |
| `socialLinks()` | `array` | Retorna array filtrado de redes sociais cadastradas. |
| `hasSeoGenerated()` | `bool` | Retorna se o SEO da plataforma já foi processado pela Sofi. |

---

## 3. Configurações Criptografadas (`Setting.php`)

Para gerenciar credenciais de terceiros (ex: chaves de gateway, tokens de IA), o model `Setting` realiza a criptografia automática:

```php
// Obter chave descriptografada
$stripeKey = Setting::get('stripe_secret', $tenantId);

// Gravar chave com criptografia AES-256
Setting::set('stripe_secret', $valor, $tenantId, $group = 'gateways');
```

---

## 4. Internacionalização (I18n)

- Suporte aos idiomas **Português (`pt_BR`)** e **Espanhol (`es`)**.
- Rotas de troca de idioma: `/lang/{locale}` e `/locale/{locale}` com persistência em sessão (`session()->put('locale', $locale)`).

---

## 5. Regras Específicas

1. Qualquer novo model que armazene dados vinculados a uma produtora deve usar `App\Traits\BelongsToTenant`.
2. A exclusão de um `Tenant` utiliza `SoftDeletes` para proteção jurídica e integridade de histórico financeiro.
3. Rotas de administração de plataforma (`/admin/settings/*`) exigem autenticação e permissão de administrador.
