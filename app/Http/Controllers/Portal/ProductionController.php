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

        // TikTok conectado direto publica pela tela própria (regras do Direct Post), não pelo modal.
        $tiktokDirect = $request->user('cliente')->activeSocialConnection('tiktok') !== null
            && $router->routeFor('tiktok') === SocialPublisherRouter::NATIVE;

        return view('portal.productions.show', compact('production', 'roteiros', 'videos', 'tiktokDirect'));
    }
}
