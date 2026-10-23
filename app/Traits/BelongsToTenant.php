<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Evita recursão infinita: resolver auth()->user() consulta a própria tabela
     * de usuários, o que reaplicaria este mesmo scope indefinidamente.
     */
    protected static bool $resolvingTenantScope = false;

    /**
     * Bootable trait para aplicar automaticamente scoping de tenant
     */
    public static function bootBelongsToTenant(): void
    {
        // Ao criar um novo modelo, associa automaticamente o tenant do usuário autenticado
        static::creating(function (Model $model) {
            if (!$model->tenant_id) {
                $model->tenant_id = static::currentTenantId();
            }
        });

        // Ao consultar, filtra automaticamente pelo tenant do usuário autenticado
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = static::currentTenantId();

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Resolve o tenant_id do usuário autenticado sem reentrar no próprio scope
     */
    protected static function currentTenantId(): ?int
    {
        if (static::$resolvingTenantScope) {
            return null;
        }

        // Marca a flag antes de tocar em auth(), pois check()/user() já disparam a query
        static::$resolvingTenantScope = true;

        try {
            // Suporta tanto a equipe da produtora (guard 'web') quanto o portal do cliente (guard 'cliente')
            foreach (['web', 'cliente'] as $guard) {
                if (auth($guard)->check()) {
                    return auth($guard)->user()?->tenant_id;
                }
            }

            return null;
        } finally {
            static::$resolvingTenantScope = false;
        }
    }

    /**
     * Relacionamento: Modelo pertence a um Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Permite contornar o scoping de tenant quando necessário (apenas para admin)
     */
    public function withoutTenantScope()
    {
        return static::withoutGlobalScope('tenant');
    }
}
