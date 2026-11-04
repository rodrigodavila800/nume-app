<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\ClosesWhatsAppSale;
use App\Jobs\SendQuoteProposalDocuments;
use App\Models\Tenant;
use App\Services\Crm\LeadService;
use App\Services\Quotes\PackBuilderService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * "Monte seu pacote" na conversa do WhatsApp: tipos de vídeo e extras pelo NOME (o modelo nunca manda
 * preço nem id), preço do PackBuilderService (banco, tenant do construtor).
 *
 * Sem confirm: devolve o catálogo ou a prévia da montagem, sem criar nada. Com confirm e os dados de
 * fechamento: cria a proposta (mesmo fluxo do pacote pronto: PDF por e-mail e por este WhatsApp,
 * cadastro ou login no portal para pagar) e registra no CRM.
 */
class BuildCustomPackageTool implements Tool
{
    use ClosesWhatsAppSale;

    public function __construct(
        protected int $tenantId,
        protected ?int $clienteId = null,
        protected ?string $phone = null,
        protected ?int $leadId = null,
        protected bool $isSpanish = false,
    ) {}

    public function description(): Stringable|string
    {
        return 'Monta um pacote personalizado: tipos de vídeo com quantidades e extras, pelo nome. Use quando nenhum pacote pronto servir ou o cliente quiser escolher. Sem confirm devolve o catálogo ou o preço da montagem (não cria nada); com confirm=true e os dados de fechamento (responsável, empresa, e-mail, nicho) cria a proposta e envia o PDF.';
    }

    public function handle(Request $request): Stringable|string
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $builder = app(PackBuilderService::class);
        $catalog = $builder->catalog($tenant);

        if ($catalog['video_types'] === []) {
            return 'A produtora ainda não liberou o montador de pacotes. Ofereça os pacotes prontos (ListTenantPacksTool) ou chame a equipe (EscalateToTeamTool).';
        }

        $types = collect($catalog['video_types'])->keyBy(fn (array $t) => mb_strtolower($t['name']));
        $addons = collect($catalog['addons'])->keyBy(fn (array $a) => mb_strtolower($a['name']));

        $items = [];
        $unknown = [];
        foreach ((array) ($request['items'] ?? []) as $item) {
            $name = mb_strtolower(trim((string) ($item['video_type'] ?? '')));
            $type = $types->get($name);
            if ($type) {
                $items[$type['id']] = ($items[$type['id']] ?? 0) + (int) ($item['quantity'] ?? 0);
            } else {
                $unknown[] = (string) ($item['video_type'] ?? '');
            }
        }

        $addonIds = [];
        foreach ((array) ($request['addon_names'] ?? []) as $wanted) {
            $addon = $addons->get(mb_strtolower(trim((string) $wanted)));
            if ($addon) {
                $addonIds[] = $addon['id'];
            } else {
                $unknown[] = (string) $wanted;
            }
        }

        if ($unknown !== []) {
            return 'Não existe no montador: '.implode(', ', array_filter($unknown)).'. '.$this->catalogText($catalog);
        }

        $priced = $builder->price($tenant, ['items' => $items, 'addons' => $addonIds]);

        if ($priced === null) {
            return 'Nenhum vídeo escolhido ainda. '.$this->catalogText($catalog);
        }

        $money = fn (float $value): string => number_format($value, 2, ',', '.').' €';
        $summary = implode('; ', array_map(
            fn (array $l) => ($l['kind'] === 'video' ? "{$l['quantity']}× {$l['name']}" : $l['name']).' = '.$money($l['total']),
            $priced['lines'],
        ));
        $totals = "Subtotal {$money($priced['subtotal'])} + IVA = total {$money($priced['total'])}. Sinal de {$money($priced['deposit'])} para começar e o restante na entrega.";

        if (! filter_var($request['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return "Prévia do pacote montado: {$summary}. {$totals} Mostre ao cliente e pergunte se fecha. Para fechar, chame de novo com confirm=true e os dados de fechamento.";
        }

        ['cliente' => $cliente, 'email' => $email, 'responsible' => $responsible, 'company' => $company, 'niche' => $niche, 'missing' => $missing] = $this->closingData($request);

        if ($missing !== []) {
            return 'Faltam dados para a proposta formal: '.implode(', ', $missing).'. Peça ao cliente numa mensagem só e chame esta ferramenta de novo com confirm=true.';
        }

        if ($this->recentRequests() >= CreateQuoteFromPackTool::DAILY_LIMIT) {
            return 'Limite de propostas por dia atingido para este contato. Diga ao cliente que a equipe retorna com a proposta.';
        }

        $lead = $this->syncLead($email, ['responsible' => $responsible, 'company' => $company]);
        $quote = $builder->createQuote($tenant, $priced, ['responsible' => $responsible, 'company' => $company, 'email' => $email], 'whatsapp', $cliente, $this->phone, $this->isSpanish);

        $sendDocuments = $quote->wasRecentlyCreated || empty($quote->request_details['documents_sent_at']);
        if ($sendDocuments) {
            SendQuoteProposalDocuments::dispatch($quote->id, $this->tenantId, $this->phone);
        }

        if ($lead && $quote->wasRecentlyCreated) {
            app(LeadService::class)->log(
                $lead,
                'whatsapp',
                "Proposta {$quote->number} (pacote montado: {$summary}) enviada pela Sofi no WhatsApp."
                ."\n• Responsável: {$responsible}\n• Empresa: {$company}".($niche !== '' ? "\n• Nicho: {$niche}" : ''),
                null,
                ['quote_id' => $quote->id, 'channel' => 'whatsapp', 'niche' => $niche],
            );
        }

        return "Proposta {$quote->number} pronta para {$company} ({$responsible}): {$summary}. {$totals} "
            .'Válida até '.$quote->valid_until->format('d/m/Y').'. '
            .($sendDocuments ? 'O PDF formal da proposta chega em seguida por e-mail e por este WhatsApp. ' : 'O PDF desta proposta já foi enviado antes; se o cliente pedir de novo, use ResendProposalPdfTool. ')
            .$this->accountAndPaymentText($quote, $email);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items($schema->object([
                'video_type' => $schema->string()->description('Nome exato do tipo de vídeo do montador.')->required(),
                'quantity' => $schema->integer()->description('Quantidade de vídeos desse tipo.')->required(),
            ]))->description('Tipos de vídeo e quantidades escolhidos. Vazio = só ver o catálogo.'),
            'addon_names' => $schema->array()->items($schema->string())->description('Nomes exatos dos extras do montador, se houver.'),
            'confirm' => $schema->boolean()->description('true só depois que o cliente aceitou a montagem e passou os dados de fechamento.'),
            'client_email' => $schema->string()->description('E-mail do cliente (obrigatório para confirmar).'),
            'responsible_name' => $schema->string()->description('Nome da pessoa responsável (obrigatório para confirmar).'),
            'company_name' => $schema->string()->description('Nome da empresa ou marca (obrigatório para confirmar).'),
            'niche' => $schema->string()->description('Nicho de atuação da empresa.'),
        ];
    }

    /**
     * @param  array{video_types: list<array{name: string, price: float, duration_seconds: int}>, addons: list<array{name: string, price: float}>}  $catalog
     */
    private function catalogText(array $catalog): string
    {
        $money = fn (float $value): string => number_format($value, 2, ',', '.').' €';

        return 'Tipos de vídeo do montador: '.implode('; ', array_map(fn ($t) => "{$t['name']} ({$t['duration_seconds']} s, {$money($t['price'])} por vídeo)", $catalog['video_types'])).'. '
            .($catalog['addons'] === [] ? '' : 'Extras: '.implode('; ', array_map(fn ($a) => "{$a['name']} (+{$money($a['price'])})", $catalog['addons'])).'. ')
            .'Preços sem IVA.';
    }
}
