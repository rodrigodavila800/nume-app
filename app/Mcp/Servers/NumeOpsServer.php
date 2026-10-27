<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\LeadsByStageTool;
use App\Mcp\Tools\OpenSupportTicketsTool;
use App\Mcp\Tools\OverviewTool;
use App\Mcp\Tools\PendingApprovalsTool;
use App\Mcp\Tools\QuoteRequestsTool;
use App\Mcp\Tools\ScheduleTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Nume Ops MCP Server')]
#[Version('1.0.0')]
#[Instructions('Servidor MCP somente leitura da plataforma NUME Creative. Todas as ferramentas consultam apenas os dados da produtora autenticada pela API Key.')]
class NumeOpsServer extends Server
{
    protected array $tools = [
        OverviewTool::class,
        PendingApprovalsTool::class,
        ScheduleTool::class,
        QuoteRequestsTool::class,
        LeadsByStageTool::class,
        OpenSupportTicketsTool::class,
    ];
}
