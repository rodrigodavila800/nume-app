<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Services\SocialPublishing\SocialPublisherRouter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function show(Request $request, Production $production, SocialPublisherRouter $router): View
    {
        abort_unless($production->cliente_id === $request->user('cliente')->id, 403);

        $roteiros = $production->roteiros()->latest()->get();
        $videos = $production->videos()->latest()->get();

        // Redes conectadas direto que publicam por tela própria (TikTok: regras do Direct Post;
        // Pinterest: escolha da pasta), não pelo modal genérico.
        $composerPlatforms = collect(config('social.composer_platforms', []))
            ->filter(fn (string $platform) => $request->user('cliente')->activeSocialConnection($platform) !== null
                && $router->routeFor($platform) === SocialPublisherRouter::NATIVE)
            ->values()
            ->all();

        return view('portal.productions.show', compact('production', 'roteiros', 'videos', 'composerPlatforms'));
    }
}
