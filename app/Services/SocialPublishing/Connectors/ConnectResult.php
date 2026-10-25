<?php

namespace App\Services\SocialPublishing\Connectors;

use App\Models\SocialConnection;

/**
 * Resultado do retorno OAuth: contas conectadas, ou a escolha de conta pendente
 * (ex.: o cliente administra mais de uma Página do Facebook).
 */
final class ConnectResult
{
    /**
     * @param  list<SocialConnection>  $connections
     */
    private function __construct(
        public readonly array $connections,
        public readonly ?string $selectionRoute,
    ) {}

    /** @param  list<SocialConnection>  $connections */
    public static function connected(array $connections): self
    {
        return new self($connections, null);
    }

    public static function selection(string $routeName): self
    {
        return new self([], $routeName);
    }

    public function needsSelection(): bool
    {
        return $this->selectionRoute !== null;
    }
}
