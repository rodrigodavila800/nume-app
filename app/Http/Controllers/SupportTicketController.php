<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Crm\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Caixa de suporte da equipe (dentro do CRM).
 */
class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportService $support) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupportTicket::class);

        $status = $request->input('status', 'abertos');

        $tickets = SupportTicket::query()
            ->with(['cliente', 'assignee'])
            ->when($status === 'abertos', fn ($q) => $q->whereIn('status', SupportTicket::OPEN_STATUSES))
            ->when(in_array($status, SupportTicket::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($request->input('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->orderByRaw("case priority when 'urgente' then 0 when 'alta' then 1 when 'normal' then 2 else 3 end")
            ->orderByDesc('last_reply_at')
            ->paginate(25)
            ->withQueryString();

        return view('support.index', compact('tickets', 'status'));
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['messages', 'cliente', 'assignee']);
        $team = User::query()->where('tenant_id', $ticket->tenant_id)->whereIn('role', ['admin', 'editor'])->orderBy('name')->get(['id', 'name']);

        return view('support.show', compact('ticket', 'team'));
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['nullable', 'boolean'],
        ]);

        $this->support->teamReply($ticket, $request->user(), $validated['body'], $request->boolean('internal'));

        return back()->with('success', $request->boolean('internal') ? __('Nota interna registrada.') : __('Resposta enviada ao cliente.'));
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $ticket->tenant_id)],
        ]);

        $ticket->forceFill(['priority' => $validated['priority'], 'assigned_to' => $validated['assigned_to'] ?? null])->save();
        $this->support->changeStatus($ticket, $validated['status'], $request->user());

        return back()->with('success', __('Chamado atualizado.'));
    }
}
