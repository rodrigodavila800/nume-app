<?php

namespace App\Services\SocialPublishing\Connectors;

use Illuminate\Contracts\Container\Container;

/**
 * Conectores disponíveis no portal (config('social.connectors'): chave da rota => classe).
 */
class ConnectorRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(string $key): ?SocialConnector
    {
        $class = config("social.connectors.{$key}");

        return is_string($class) && is_a($class, SocialConnector::class, true) ? $this->container->make($class) : null;
    }

    /**
     * Conector dono das contas de um provider (google, meta, threads).
     */
    public function forProvider(string $provider): ?SocialConnector
    {
        foreach (array_keys(config('social.connectors', [])) as $key) {
            $connector = $this->for($key);
            if ($connector?->provider() === $provider) {
                return $connector;
            }
        }

        return null;
    }
}
