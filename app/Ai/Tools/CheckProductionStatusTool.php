<?php

namespace App\Ai\Tools;

use App\Models\Production;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckProductionStatusTool implements Tool
{
    public function __construct(
        protected int $tenantId,
        protected ?int $clienteId = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Consulta o status atual das produções de vídeo do cliente nesta produtora. Retorna o título, a fase atual e link do portal.';
    }

    public function handle(Request $request): Stringable|string
    {
        // Sem cliente identificado pelo telefone, nunca lista produções: antes, sem filtro,
        // qualquer número desconhecido recebia as produções mais recentes de outros clientes.
        if (! $this->clienteId) {
            return 'Contato não identificado como cliente: não informe status de produções. Peça para acessar o portal do cliente ou falar com a equipe.';
        }

        $query = Production::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('cliente_id', $this->clienteId);

        $term = trim($request['search'] ?? '');
        if ($term !== '') {
            $query->where('title', 'like', "%{$term}%");
        }

        $productions = $query->latest('id')->take(3)->get()->map(function (Production $p) {
            $statusMap = [
                'draft' => 'Planejamento / Rascunho',
                'script_ready' => 'Roteiro pronto aguardando aprovação',
                'approved' => 'Roteiro aprovado, pronto para gravação',
                'in_production' => 'Em gravação / Edição',
                'ready' => 'Corte final pronto para aprovação',
                'published' => 'Vídeo finalizado e publicado',
            ];

            // Link pelo uuid (regra 4: nunca expor id sequencial em link externo).
            return [
                'title' => $p->title,
                'status' => $statusMap[$p->status] ?? $p->status,
                'portal_url' => route('portal.productions.show', $p),
            ];
        });

        if ($productions->isEmpty()) {
            return 'Nenhuma produção encontrada com esses critérios.';
        }

        return json_encode($productions->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Termo opcional de busca ou título do vídeo/projeto.'),
        ];
    }
}
