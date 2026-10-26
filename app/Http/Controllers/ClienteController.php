<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = Cliente::withCount('productions')
            ->orderBy('name')
            ->paginate(15);

        return view('clientes.index', compact('clientes'));
    }

    public function create(): View
    {
        $this->authorize('create', Cliente::class);

        return view('clientes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Cliente::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $cliente = Cliente::create([
            ...$validated,
            'password' => Hash::make(Str::random(32)),
        ]);

        Password::broker('clientes')->sendResetLink(['email' => $cliente->email, 'tenant_id' => $cliente->tenant_id]);

        app(\App\Services\Crm\LeadAutomation::class)->clienteRegistered($cliente, 'manual');

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente cadastrado! Um link de definição de senha foi enviado por e-mail.');
    }

    public function edit(Cliente $cliente): View
    {
        $this->authorize('update', $cliente);

        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $cliente->update($validated);

        // Limites de publicação: só o admin da produtora ajusta (vazio = segue o contratado).
        if ($request->user()->isAdmin()) {
            $limits = $request->validate([
                'social_networks_limit_override' => 'nullable|integer|min:1|max:8',
                'monthly_post_limit_override' => 'nullable|integer|min:1|max:1000',
            ]);
            $cliente->forceFill([
                'social_networks_limit_override' => $limits['social_networks_limit_override'] ?? null,
                'monthly_post_limit_override' => $limits['monthly_post_limit_override'] ?? null,
            ])->save();
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $cliente->delete();

        return redirect()->route('clientes.index')->with('success', 'Cliente removido com sucesso!');
    }
}
