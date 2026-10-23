<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    /**
     * Lista todas as chaves de API da produtora autenticada.
     */
    public function index(Request $request): View
    {
        $tenantId = auth()->user()->tenant_id;

        $apiKeys = ApiKey::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('settings.api_keys.index', compact('apiKeys'));
    }

    /**
     * Emite uma nova chave de API segura.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:10', 'max:600'],
        ]);

        $tenant = auth()->user()->tenant;

        $result = ApiKey::createToken(
            tenant: $tenant,
            name: $validated['name'],
            rateLimit: $validated['rate_limit_per_minute'] ?? 60
        );

        return redirect()->route('api_keys.index')->with([
            'success' => 'Chave de API gerada com sucesso!',
            'plainTextToken' => $result['plainTextToken'],
            'keyName' => $result['apiKey']->name,
        ]);
    }

    /**
     * Revoga a chave de API imediatamente.
     */
    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        if ($apiKey->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $apiKey->revoke();

        return redirect()->route('api_keys.index')->with('success', 'Chave de API revogada com sucesso. Qualquer requisição usando este token será rejeitada imediatamente.');
    }
}
