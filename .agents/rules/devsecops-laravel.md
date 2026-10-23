# Padrões DevSecOps Laravel 11+

**Objetivo:** Estabelecer padrões rigorosos de segurança em código, autenticação, autorização via Policies, proteção de segredos e prevenção de vulnerabilidades comuns (OWASP Top 10).

---

## 1. Autorização Obrigatória (Policies & Gates)

Nenhuma ação de escrita (`update`, `delete`, `approve`, `publish`) ou leitura de dados sensíveis pode ser executada sem a verificação explícita de Policy:

```php
$this->authorize('update', $production);
// ou
Gate::authorize('approve', $roteiro);
```

### Matriz de Policies:
- `ProductionPolicy`: Valida se o usuário pertence ao tenant proprietário da produção e se o status permite edição.
- `RoteiroPolicy`: Valida permissões de submissão, aprovação interna (staff da produtora) ou aprovação externa (portal do cliente).
- `VideoPolicy`: Valida manipulação de arquivos de vídeo, status de publicação e inserção de metadados SEO.
- `PackPolicy` & `ClientePolicy`: Isolamento estrito de cadastros por tenant.

---

## 2. Proteção de Credenciais e Segredos de Gateways

1. **Criptografia AES-256 no Banco de Dados:**
   - As chaves de API (Stripe Secret Key, Webhook Secret, Gemini API Key) armazenadas no banco na tabela `settings` **devem** ser criptografadas usando o helper `Crypt::encryptString()` e descriptografadas via `Crypt::decryptString()`.
   - O Model `App\Models\Setting` já encapsula esse comportamento seguro.
2. **Nunca versionar `.env` no Git:**
   - Assegurar que `APP_KEY`, `DB_PASSWORD` e `STRIPE_SECRET` estejam no `.env` e que `.gitignore` contenha `.env`.

---

## 3. Prevenção de Ataques Comuns

- **IDOR (Insecure Direct Object References):** Prevenido pelo uso conjunto de `BelongsToTenant` + Route Model Binding com `uuid` + Policies.
- **Mass Assignment:** Todos os Models possuem `$fillable` estritamente declarado. Nunca usar `$guarded = []`.
- **Brute Force & Rate Limiting:**
  - Checkouts e endpoints de pagamento possuem `throttle:10,1` (máximo 10 requisições por minuto).
  - Rotas de autenticação (Login de Produtora e Login do Portal do Cliente) possuem limitação nativa por IP/e-mail.
- **CSRF:** Todas as rotas `POST`, `PUT`, `PATCH`, `DELETE` web exigem token `@csrf`. A única exceção é `/stripe/webhook`, que valida a assinatura criptográfica `Stripe-Signature`.
- **Validação de Inputs:** Utilizar sempre Form Requests ou `$request->validate([...])` com tipagem e limites de tamanho explícitos.

---

## 4. Frontend & Padrões Blade (Regra de Ouro)

- **Zero CSS Inline:** É estritamente proibido utilizar `style="..."` dentro dos arquivos Blade.
- **Painel Interno / Admin / Portal do Cliente:** Utilizar exclusivamente classes utilitárias do Tailwind CSS.
- **Temas Públicos / Vitrine:** Utilizar classes semânticas centralizadas no arquivo CSS do tema (ex: `public/themes/prism/templatemo-prism-flux.css`) ou Tailwind, preservando manutenibilidade, separação de responsabilidades e integridade visual de cache.

