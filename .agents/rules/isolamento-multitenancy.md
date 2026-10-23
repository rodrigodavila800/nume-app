# Diretriz de Isolamento Multi-tenant

**Objetivo:** Garantir a separação estrita e hermética de dados entre produtoras (Tenants) e seus respectivos clientes e produções no banco de dados, prevenindo qualquer tipo de vazamento de dados ou IDOR.

---

## 1. O Trait `BelongsToTenant`

Todos os models que pertencem a uma produtora **devem** utilizar o trait `App\Traits\BelongsToTenant`:

```php
use App\Traits\BelongsToTenant;

class Production extends Model
{
    use HasFactory, BelongsToTenant;
    ...
}
```

### Comportamento do Trait:
- **Global Scope:** Injeta automaticamente `where('tenant_id', $currentTenantId)` em todas as queries `select`, `update` e `delete`.
- **Auto-atribuição no `creating`:** Preenche automaticamente `$model->tenant_id = auth()->user()->tenant_id` ou do contexto ativo na criação.
- **Relacionamento:** Disponibiliza o método `$model->tenant()`.

---

## 2. Regras de Ouro de Tenancy

1. **Toda tabela de negócio possui `tenant_id` com índice de banco de dados:**
   - Tabelas: `users`, `clientes`, `productions`, `roteiros`, `videos`, `packs`, `pack_purchases`, `showcase_videos`, `settings`.
2. **Nunca desativar o Global Scope em rotas públicas ou autenticadas sem validação prévia:**
   - O uso de `withoutGlobalScope(BelongsToTenant::class)` é restrito a comandos CLI internos de manutenção ou Webhooks de pagamento (onde o Tenant é resolvido via payload seguro do Stripe).
3. **Validação de Propriedade Cruzada:**
   - Ao vincular um `Cliente` a uma `Production`, garanta que `cliente.tenant_id == production.tenant_id`.
   - Ao vincular um `PackPurchase` a uma `Production`, garanta que a compra pertença ao mesmo tenant e cliente.
4. **Resolução de Rotas com Route Model Binding e Scoped Bindings:**
   - Nas rotas aninhadas, utilize sempre `Route::scopeBindings()` para assegurar que recursos filhos (como `roteiros` e `videos`) pertençam legitimamente à `production` pai na URL.

---

## 3. Identificadores Públicos: UUIDs vs IDs Sequenciais

- **Entidades com visualização pública ou compartilhada** (`Production`, `Roteiro`) utilizam `uuid` gerado automaticamente no evento `creating` e implementam `getRouteKeyName() => 'uuid'`.
- IDs inteiros sequenciais nunca devem ser expostos em links externos de aprovação ou portais para evitar enumeração e ataques de força bruta.
