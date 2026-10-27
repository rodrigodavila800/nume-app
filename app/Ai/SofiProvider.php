<?php

namespace App\Ai;

use App\Models\Setting;
use App\Models\Tenant;
use Laravel\Ai\Ai;

/**
 * Ponto único de montagem do provider do Laravel AI SDK para os agentes da Sofi.
 *
 * Devolve os argumentos nomeados de Agent::prompt() (provider, model, timeout), para
 * que nenhum service repita a chamada a Ai::build.
 *
 * O AiManager guarda os providers sob demanda num registro do processo, indexado
 * pelo nome, e o último build() com o mesmo nome vence. Por isso o nome leva o
 * tenant, e nunca nada derivado da chave.
 */
class SofiProvider
{
    public function __construct(
        protected AiBudget $budget = new AiBudget,
    ) {}

    /**
     * Driver configurado ('gemini', 'anthropic', ...).
     */
    public function driver(): string
    {
        return (string) config('sofi.provider', 'gemini');
    }

    /**
     * Argumentos para Agent::prompt(...$options), ou null quando não há chave configurada
     * ou o limite mensal de tokens (da produtora ou do cliente) foi atingido.
     *
     * @return array{provider: string, model?: string, timeout: int}|null
     */
    public function options(?Tenant $tenant = null, ?int $clienteId = null): ?array
    {
        if (! $this->budget->allows($tenant?->id, $clienteId)) {
            return null;
        }

        $driver = $this->driver();
        $apiKey = $this->apiKey($driver, $tenant);

        if (! $apiKey) {
            return null;
        }

        $name = $tenant ? "sofi_{$driver}_t{$tenant->id}" : "sofi_{$driver}";

        $provider = Ai::build([
            'name' => $name,
            'driver' => $driver,
            'key' => $apiKey,
        ]);

        // Provider como string: na forma de array o SDK ignora o argumento model.
        return array_filter([
            'provider' => $provider->name(),
            'model' => config('sofi.model'),
            'timeout' => (int) config('sofi.timeout', 15),
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function apiKey(string $driver, ?Tenant $tenant): ?string
    {
        // TODO(chave por tenant): quando cada produtora tiver a própria chave, ler aqui
        // a chave criptografada do tenant (escopo explícito pelo $tenant->id) antes da global.
        $key = config("sofi.keys.{$driver}");

        // Gemini também pode estar só na tela de configurações (Setting criptografado), como
        // nas chamadas antigas: sem isso, mover um serviço para o SDK desligaria a IA dele.
        if (! $key && $driver === 'gemini') {
            $key = Setting::get('gemini_api_key');
        }

        return $key ?: null;
    }
}
