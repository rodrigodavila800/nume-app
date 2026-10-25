<?php

namespace App\Services\SocialPublishing;

use App\Models\Cliente;
use App\Models\SocialConnection;

/**
 * Decide, rede a rede, quem publica: o app próprio da NUME (conta conectada por OAuth) ou o
 * Upload-Post. A rede só vai para o nativo se estiver liberada em config('social.routing') e o
 * cliente tiver uma SocialConnection ativa nela; caso contrário, segue pelo Upload-Post.
 */
class SocialPublisherRouter
{
    public const NATIVE = 'native';

    public const UPLOAD_POST = 'upload_post';

    public function __construct(private readonly NativePublisherRegistry $publishers) {}

    /**
     * @param  list<string>  $platforms
     * @return array{native: array<string, SocialConnection>, upload_post: list<string>}
     */
    public function route(Cliente $cliente, array $platforms): array
    {
        $platforms = array_values(array_unique($platforms));
        $nativeCandidates = array_values(array_filter($platforms, fn (string $platform) => $this->routeFor($platform) === self::NATIVE));

        $connections = $nativeCandidates === [] ? collect() : SocialConnection::forTenant($cliente->tenant_id)
            ->where('cliente_id', $cliente->id)
            ->whereIn('platform', $nativeCandidates)
            ->active()
            ->latest('id')
            ->get()
            ->unique('platform')
            ->keyBy('platform');

        $native = [];
        $uploadPost = [];
        foreach ($platforms as $platform) {
            if ($connections->has($platform)) {
                $native[$platform] = $connections->get($platform);
            } else {
                $uploadPost[] = $platform;
            }
        }

        return ['native' => $native, 'upload_post' => $uploadPost];
    }

    /**
     * Nativo só se a rede estiver liberada E tiver publicador pronto (config('social.native_publishers')).
     */
    public function routeFor(string $platform): string
    {
        return config("social.routing.{$platform}") === self::NATIVE && $this->publishers->supports($platform)
            ? self::NATIVE
            : self::UPLOAD_POST;
    }
}
