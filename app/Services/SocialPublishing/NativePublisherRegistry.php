<?php

namespace App\Services\SocialPublishing;

use Illuminate\Contracts\Container\Container;

/**
 * Qual publicador atende cada rede (config('social.native_publishers')).
 */
class NativePublisherRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(string $platform): ?NativePublisher
    {
        $class = config("social.native_publishers.{$platform}");

        return is_string($class) && is_a($class, NativePublisher::class, true) ? $this->container->make($class) : null;
    }

    public function supports(string $platform): bool
    {
        return $this->for($platform) !== null;
    }
}
