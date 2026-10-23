<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Roteiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoteiroController extends Controller
{
    public function show(Request $request, Roteiro $roteiro): View
    {
        $this->authorizeCliente($request, $roteiro);

        return view('portal.roteiros.show', compact('roteiro'));
    }

    public function approve(Request $request, Roteiro $roteiro): RedirectResponse
    {
        $this->authorizeCliente($request, $roteiro);

        if (!$roteiro->approveByCliente($request->user('cliente'))) {
            return back()->with('error', 'Este roteiro não está mais aguardando aprovação.');
        }

        return back()->with('success', 'Roteiro aprovado com sucesso!');
    }

    public function reject(Request $request, Roteiro $roteiro): RedirectResponse
    {
        $this->authorizeCliente($request, $roteiro);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        if (!$roteiro->rejectByCliente($request->user('cliente'), $validated['rejection_reason'])) {
            return back()->with('error', 'Este roteiro não está mais aguardando aprovação.');
        }

        return back()->with('success', 'Roteiro rejeitado. A produtora foi notificada.');
    }

    private function authorizeCliente(Request $request, Roteiro $roteiro): void
    {
        abort_unless($roteiro->production->cliente_id === $request->user('cliente')->id, 403);
    }
}
