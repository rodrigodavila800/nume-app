<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function show(Request $request, Production $production): View
    {
        abort_unless($production->cliente_id === $request->user('cliente')->id, 403);

        $roteiros = $production->roteiros()->latest()->get();
        $videos = $production->videos()->latest()->get();

        return view('portal.productions.show', compact('production', 'roteiros', 'videos'));
    }
}
