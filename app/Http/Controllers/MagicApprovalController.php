<?php

namespace App\Http\Controllers;

use App\Models\Roteiro;
use App\Models\Video;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MagicApprovalController extends Controller
{
    /**
     * Exibe a página mobile-first de aprovação sem senha de Roteiro.
     */
    public function showRoteiro(Request $request, Roteiro $roteiro): View
    {
        abort_unless($request->hasValidSignature(), 403, __('Este link de aprovação expirou ou é inválido.'));

        $production = $roteiro->production;
        $cliente = $production?->cliente;

        return view('magic.roteiro', compact('roteiro', 'production', 'cliente'));
    }

    /**
     * Aprova o roteiro com 1 clique através do link assinado.
     */
    public function approveRoteiro(Request $request, Roteiro $roteiro): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, __('Assinatura de aprovação inválida.'));

        if ($roteiro->status !== 'submitted') {
            return back()->with('info', __('Este roteiro já foi processado anteriormente (Status atual: :status).', [
                'status' => ucfirst($roteiro->status),
            ]));
        }

        $cliente = $roteiro->production->cliente;
        $roteiro->approveByCliente($cliente);

        return back()->with('success', __('Roteiro aprovado com sucesso! A equipe da produtora já foi notificada para iniciar as gravações.'));
    }

    /**
     * Solicita ajuste/rejeita o roteiro com observações através do link assinado.
     */
    public function rejectRoteiro(Request $request, Roteiro $roteiro): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, __('Assinatura de aprovação inválida.'));

        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:1000',
        ]);

        if ($roteiro->status !== 'submitted') {
            return back()->with('info', __('Este roteiro não está mais aguardando aprovação.'));
        }

        $cliente = $roteiro->production->cliente;
        $roteiro->rejectByCliente($cliente, $request->input('rejection_reason'));

        return back()->with('success', __('Seus apontamentos foram registrados! A equipe de roteiristas fará as revisões solicitadas.'));
    }

    /**
     * Exibe a página mobile-first de aprovação sem senha de Vídeo final.
     */
    public function showVideo(Request $request, Video $video): View
    {
        abort_unless($request->hasValidSignature(), 403, __('Este link de aprovação expirou ou é inválido.'));

        $production = $video->production;
        $cliente = $production?->cliente;

        return view('magic.video', compact('video', 'production', 'cliente'));
    }

    /**
     * Aprova o vídeo final com 1 clique através do link assinado.
     */
    public function approveVideo(Request $request, Video $video): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, __('Assinatura de aprovação inválida.'));

        $video->markAsReady();

        return back()->with('success', __('Vídeo final aprovado com sucesso! A produção está liberada para agendamento e postagem.'));
    }
}
