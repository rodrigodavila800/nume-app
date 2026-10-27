<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Tenant;
use RuntimeException;

trait ResolvesMcpTenant
{
    protected function tenantId(): int
    {
        $tenant = app()->bound('current_tenant')
            ? app('current_tenant')
            : request()->attributes->get('tenant');

        if ($tenant instanceof Tenant) {
            return (int) $tenant->id;
        }

        throw new RuntimeException('Produtora não autenticada para consulta MCP.');
    }
}
