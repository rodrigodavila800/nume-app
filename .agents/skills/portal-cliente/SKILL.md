---
name: portal-cliente
description: Conhecimento do módulo Portal do Cliente — Autenticação do cliente final (guard cliente), Google OAuth, perfil/DNA de marca (brand_profile: nicho, público, diferenciais), middleware brand.profile.completed, visualização de produções e aprovação/rejeição de roteiros. Carregar sempre que a tarefa envolver a experiência do cliente, cadastro de cliente ou fluxo de revisão externa de roteiros.
---

# Módulo Portal do Cliente

**Responsabilidade:** Fornecer a área externa e personalizada para os clientes das produtoras visualizarem o andamento dos seus vídeos, aprovarem ou solicitarem ajustes em roteiros, completarem seu DNA de marca e comprarem novos pacotes de vídeos.

---

## 1. Dicionário de Dados (`clientes`)

- **Campos Principais:** `id`, `tenant_id`, `name`, `email`, `password`, `company_name`, `phone`, `stripe_customer_id`.
- **DNA de Marca & Presença Digital:**
  - `website`: URL do site institucional.
  - `instagram_handle`, `linkedin_url`, `youtube_channel`, `tiktok_handle`: Redes sociais.
  - `brand_niche`: Nicho e segmento de atuação da empresa.
  - `target_audience`: Descrição do cliente ideal e público-alvo.
  - `key_differentials`: Proposta única de valor e diferenciais competitivos.

---

## 2. Autenticação & Guard Separado

- **Guard:** `cliente` (configurado em `config/auth.php` como provedor `clientes`).
- **Rotas Prefixadas:** `/portal/*` (definidas em `routes/portal.php` com o nome `portal.*`).
- **Google OAuth:** Implementado via `GooglePortalController` (`portal.auth.google` e `portal.auth.google.callback`), permitindo login instantâneo com a conta Google corporativa.

---

## 3. Middleware `brand.profile.completed`

Para que a inteligência da Sofi e os editores criem roteiros assertivos, o cliente é obrigado a preencher seu perfil de marca antes de acessar produções:

```php
// Helper no model Cliente.php
public function hasCompletedBrandProfile(): bool
{
    $hasNiche = ! empty(trim($this->brand_niche ?? ''));
    $hasPresenceOrAudience = ! empty(trim($this->website ?? ''))
        || ! empty(trim($this->instagram_handle ?? ''))
        || ! empty(trim($this->linkedin_url ?? ''))
        || ! empty(trim($this->youtube_channel ?? ''))
        || ! empty(trim($this->tiktok_handle ?? ''))
        || ! empty(trim($this->target_audience ?? ''));

    return $hasNiche && $hasPresenceOrAudience;
}
```

Se `hasCompletedBrandProfile()` retornar `false`, o middleware redireciona para `portal.profile.edit` com aviso explicativo.

---

## 4. Fluxo de Aprovação de Roteiros pelo Cliente

1. Cliente acessa a tela de revisão: `GET /portal/roteiros/{roteiro}`.
2. **Aprovação:** `POST /portal/roteiros/{roteiro}/approve` -> invoca `$roteiro->approveByCliente($cliente)`.
   - Atualiza `status = 'approved'`, `approved_by_cliente_id = $cliente->id`, `approved_at = now()`.
3. **Rejeição com Feedback:** `POST /portal/roteiros/{roteiro}/reject` (com campo `rejection_reason` obrigatório) -> invoca `$roteiro->rejectByCliente($cliente, $reason)`.
   - Atualiza `status = 'rejected'`, grava a justificativa para que o time de edição/copywriter faça os ajustes.

---

## 5. Regras Específicas

- Clientes nunca acessam painéis internos da produtora (`/productions`, `/billing`, `/admin`).
- Toda ação no portal valida automaticamente se o recurso pertence ao `tenant_id` e ao `cliente_id` autenticado.
