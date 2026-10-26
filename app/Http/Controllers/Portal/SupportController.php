<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SupportTicket;
use App\Services\Crm\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suporte no portal: o cliente abre e acompanha os próprios chamados (nunca vê notas internas).
 */
class SupportController extends Controller
{
    public function __construct(private readonly SupportService $support) {}

    public function index(Request $request): View
    {
        $cliente = $request->user('cliente');

        $tickets = SupportTicket::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->latest('last_reply_at')
            ->paginate(15);

        return view('portal.support.index', compact('tickets'));
    }

    public function create(Request $request): View
    {
        return view('portal.support.create', [
            'subject' => mb_substr((string) $request->query('assunto', ''), 0, 200),
            'body' => mb_substr((string) $request->query('mensagem', ''), 0, 2000),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->user('cliente');

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $ticket = $this->support->open($cliente, $validated['subject'], $validated['body'], $validated['category'], $request->boolean('from_chat') ? 'chat' : 'portal');

        return redirect()->route('portal.support.show', $ticket->id)
            ->with('status', __('Chamado aberto. A equipe responde por aqui.'));
    }

    public function show(Request $request, int $ticket): View
    {
        $model = $this->own($request->user('cliente'), $ticket);
        $model->load('publicMessages');

        return view('portal.support.show', ['ticket' => $model]);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $cliente = $request->user('cliente');
        $model = $this->own($cliente, $ticket);

        if ($model->status === 'fechado') {
            return back()->with('error', __('Este chamado foi fechado. Abra um novo, se precisar.'));
        }

        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $this->support->clienteReply($model, $cliente, $validated['body']);

        return back()->with('status', __('Mensagem enviada.'));
    }

    private function own(Cliente $cliente, int $id): SupportTicket
    {
        return SupportTicket::forTenant($cliente->tenant_id)->where('cliente_id', $cliente->id)->findOrFail($id);
    }
}
