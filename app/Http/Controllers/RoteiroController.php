<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Roteiro;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Services\SofiCopywriterService;
use Illuminate\Http\JsonResponse;

class RoteiroController extends Controller
{
    public function __construct(
        protected SofiCopywriterService $copywriterService
    ) {}

    /**
     * Gera roteiro estruturado com Sofi AI Copilot (/copywriter)
     */
    public function generateSofiScript(Request $request, Production $production): JsonResponse
    {
        $this->authorize('view', $production);

        $framework = $request->input('framework', 'pattern_interrupt');
        $tone = $request->input('tone', 'persuasive');
        $language = $request->input('language', session('locale', 'pt_BR'));

        $generated = $this->copywriterService->generate(
            title: $production->title,
            description: $production->description,
            keywords: $production->seo_keywords,
            framework: $framework,
            tone: $tone,
            language: $language,
            cliente: $production->cliente
        );

        return response()->json([
            'status' => 'success',
            'agent' => 'Sofi AI /copywriter',
            'data' => $generated,
        ]);
    }

    /**
     * Listar roteiros de uma production
     */
    public function index(Production $production): View
    {
        $this->authorize('view', $production);

        $roteiros = $production->roteiros()
            ->with(['creator', 'approver'])
            ->paginate(15);

        return view('roteiros.index', compact('production', 'roteiros'));
    }

    /**
     * Exibir formulário para criar novo roteiro
     */
    public function create(Production $production): View
    {
        $this->authorize('create', Roteiro::class);
        $this->authorize('view', $production);

        return view('roteiros.create', compact('production'));
    }

    /**
     * Armazenar novo roteiro
     */
    public function store(Request $request, Production $production): RedirectResponse
    {
        $this->authorize('create', Roteiro::class);
        $this->authorize('view', $production);

        $validated = $request->validate([
            'content' => 'required|string|min:50',
        ]);

        $roteiro = $production->roteiros()->create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('roteiros.show', [$production, $roteiro])
            ->with('success', 'Roteiro criado com sucesso!');
    }

    /**
     * Exibir roteiro completo
     */
    public function show(Production $production, Roteiro $roteiro): View
    {
        $this->authorize('view', $production);
        $this->authorize('view', $roteiro);

        return view('roteiros.show', compact('production', 'roteiro'));
    }

    /**
     * Exibir formulário para editar roteiro
     */
    public function edit(Production $production, Roteiro $roteiro): View|RedirectResponse
    {
        $this->authorize('update', $roteiro);

        if (in_array($roteiro->status, ['approved', 'in_production'])) {
            return redirect()->route('roteiros.show', [$production, $roteiro])
                ->with('error', __('Roteiros já aprovados ou em produção não podem ser editados diretamente.'));
        }

        return view('roteiros.edit', compact('production', 'roteiro'));
    }

    /**
     * Atualizar roteiro
     */
    public function update(Request $request, Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('update', $roteiro);

        if (in_array($roteiro->status, ['approved', 'in_production'])) {
            return redirect()->route('roteiros.show', [$production, $roteiro])
                ->with('error', __('Roteiros já aprovados ou em produção não podem ser editados diretamente.'));
        }

        $validated = $request->validate([
            'content' => 'required|string|min:50',
        ]);

        if ($roteiro->status === 'rejected') {
            $roteiro->status = 'draft';
            $roteiro->rejection_reason = null;
        }

        $roteiro->content = $validated['content'];
        $roteiro->save();

        return redirect()->route('roteiros.show', [$production, $roteiro])
            ->with('success', __('Roteiro atualizado com sucesso!'));
    }

    /**
     * Submeter roteiro para aprovação
     */
    public function submit(Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('update', $roteiro);

        if (!$roteiro->submit()) {
            return redirect()->back()
                ->with('error', 'Roteiro já foi submetido ou aprovado.');
        }

        return redirect()->back()
            ->with('success', 'Roteiro submetido para aprovação!');
    }

    /**
     * Aprovar roteiro (apenas admin/editor)
     */
    public function approve(Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('approve', $roteiro);

        if (!$roteiro->approve(auth()->user())) {
            return redirect()->back()
                ->with('error', 'Roteiro deve estar em "submitted" para ser aprovado.');
        }

        return redirect()->back()
            ->with('success', 'Roteiro aprovado com sucesso!');
    }

    /**
     * Rejeitar roteiro com motivo (apenas admin/editor)
     */
    public function reject(Request $request, Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('approve', $roteiro);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        if (!$roteiro->reject(auth()->user(), $validated['rejection_reason'])) {
            return redirect()->back()
                ->with('error', 'Roteiro deve estar em "submitted" para ser rejeitado.');
        }

        return redirect()->back()
            ->with('success', 'Roteiro rejeitado. O criador foi notificado.');
    }

    /**
     * Marcar roteiro como em produção
     */
    public function markAsInProduction(Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('update', $roteiro);

        if (!$roteiro->markAsInProduction()) {
            return redirect()->back()
                ->with('error', 'Roteiro deve estar aprovado para entrar em produção.');
        }

        return redirect()->back()
            ->with('success', 'Roteiro marcado como em produção!');
    }

    /**
     * Deletar roteiro (apenas rascunho)
     */
    public function destroy(Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('delete', $roteiro);

        if ($roteiro->status !== 'draft') {
            return redirect()->back()
                ->with('error', 'Apenas roteiros em rascunho podem ser deletados.');
        }

        $roteiro->delete();

        return redirect()->route('productions.show', $production)
            ->with('success', 'Roteiro deletado com sucesso!');
    }
}
