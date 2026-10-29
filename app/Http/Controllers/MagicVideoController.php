<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MagicVideoController extends Controller
{
    /**
     * Exibe a página isolada e autocontida de aprovação do corte final.
     * NUNCA autentica a sessão nem concede acesso ao portal ou dados do cliente.
     */
    public function show(Request $request, Video $video): View
    {
        abort_unless($request->hasValidSignature(), 403, __('Este link de aprovação expirou ou é inválido.'));

        $production = $video->production;
        abort_unless($production, 404, __('Produção associada a este vídeo não foi encontrada.'));

        $cliente = $production->cliente;

        return view('magic.video', compact('video', 'production', 'cliente'));
    }
}
