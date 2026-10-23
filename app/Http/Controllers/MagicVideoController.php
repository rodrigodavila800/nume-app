<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MagicVideoController extends Controller
{
    /**
     * Autentica o cliente via Magic Link assinado e o redireciona diretamente
     * para a tela de revisão e aprovação do corte final no portal.
     */
    public function show(Request $request, Video $video): RedirectResponse
    {
        $production = $video->production;
        abort_unless($production, 404, __('Produção associada a este vídeo não foi encontrada.'));

        $cliente = $production->cliente;
        abort_unless($cliente, 404, __('Cliente associado a este vídeo não foi encontrado.'));

        // Se já houver um cliente logado diferente, efetua o logout antes de logar o cliente correto
        if (Auth::guard('cliente')->check() && Auth::guard('cliente')->id() !== $cliente->id) {
            Auth::guard('cliente')->logout();
        }

        // Autentica o cliente titular da produção sem necessidade de senha manual
        if (! Auth::guard('cliente')->check()) {
            Auth::guard('cliente')->login($cliente, remember: true);
        }

        return redirect()->route('portal.productions.show', $production->uuid)
            ->with('info', __('Acesso seguro validado via Sofi Link. Seu vídeo ":title" está pronto para revisão e aprovação!', [
                'title' => $video->title ?: $production->title,
            ]));
    }
}
