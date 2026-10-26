<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredClientController extends Controller
{
    /**
     * Exibe o formulário de cadastro de novo cliente/empresa.
     */
    public function create(Request $request): View
    {
        $packIdentifier = $request->query('pack') ?? $request->query('pack_id');

        if ($packIdentifier) {
            $pack = Pack::where('slug', $packIdentifier)->orWhere('id', is_numeric($packIdentifier) ? $packIdentifier : 0)->first();
            if ($pack) {
                session(['intended_pack_slug' => $pack->slug]);
            }
        }

        $intendedPack = null;
        if (session('intended_pack_slug')) {
            $intendedPack = Pack::where('slug', session('intended_pack_slug'))->first();
        }

        return view('portal.auth.register', compact('intendedPack'));
    }

    /**
     * Processa a criação da conta de cliente e autentica no portal.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenant = Tenant::where('slug', 'nume')->first() ?? Tenant::first();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $email = Str::lower($validated['email']);

        // Verifica se já existe cliente cadastrado com este e-mail
        $existingCliente = Cliente::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if ($existingCliente) {
            throw ValidationException::withMessages([
                'email' => __('Este e-mail já possui cadastro de cliente. Faça login para continuar.'),
            ]);
        }

        $cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $email,
            'company_name' => $validated['company_name'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        app(\App\Services\Crm\LeadAutomation::class)->clienteRegistered($cliente, 'portal_register');

        // Autentica o novo cliente no guard cliente
        Auth::guard('cliente')->login($cliente);

        $request->session()->regenerate();

        // Se houver pacote pretendido, redireciona para a esteira de contratação
        if (session('intended_pack_slug')) {
            $packSlug = session()->pull('intended_pack_slug');
            $pack = Pack::where('slug', $packSlug)->first();
            if ($pack && $pack->isActive()) {
                return redirect()->route('portal.packs.index')
                    ->with('status', "Conta criada com sucesso! Selecione o pacote '{$pack->name}' para concluir a contratação.");
            }
        }

        return redirect()->route('portal.dashboard')
            ->with('status', __('Bem-vindo ao seu Portal do Cliente! Sua esteira de produção está pronta.'));
    }
}
