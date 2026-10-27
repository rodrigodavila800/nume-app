<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\Roteiro;
use App\Models\VideoType;
use App\Services\Roteiros\RoteiroPlanGate;
use App\Services\SofiCopywriterService;
use App\Services\VideoTypes\VideoTypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoteiroController extends Controller
{
    public function __construct(
        protected SofiCopywriterService $copywriterService,
        protected VideoTypeCatalog $videoTypes,
        protected RoteiroPlanGate $planGate
    ) {}

    /**
     * Tipo de vídeo ativo da produtora da produção. Vazio = sem tipo (frameworks clássicos da Sofi).
     */
    protected function resolveVideoType(mixed $id, Production $production): ?VideoType
    {
        if ($id === null || $id === '') {
            return null;
        }

        $type = VideoType::forTenant($production->tenant_id)->active()->find((int) $id);

        if (! $type) {
            throw ValidationException::withMessages(['video_type_id' => __('Tipo de vídeo inválido ou inativo.')]);
        }

        return $type;
    }

    protected function adoptTypeOnProduction(Production $production, ?VideoType $videoType): void
    {
        if ($videoType && ! $production->video_type_id) {
            $production->forceFill(['video_type_id' => $videoType->id])->save();
        }
    }

    /**
     * Gera roteiro estruturado com Sofi AI Copilot (/copywriter)
     */
    public function generateSofiScript(Request $request, Production $production): JsonResponse
    {
        $this->authorize('view', $production);

        $request->validate([
            'video_type_id' => 'nullable|integer',
            'revision_notes' => 'nullable|string|max:1000',
        ]);

        // Roteiro só depois do plano aprovado (admin pode gerar para liberar com justificativa ao salvar).
        // Produção que já tem roteiro passou pela trava: regenerar na edição continua liberado.
        if (! $this->planGate->clienteHasApprovedPlan($production->tenant_id, $production->cliente_id)
            && ! $request->user()->can('createWithoutPlan', Roteiro::class)
            && ! $production->roteiros()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => __('O cliente ainda não aprovou o plano de marketing. Só um admin pode criar roteiro antes disso.'),
            ], 422);
        }

        $framework = $request->input('framework', 'pattern_interrupt');
        $tone = $request->input('tone', 'persuasive');
        $language = $request->input('language', session('locale', 'pt_BR'));
        $videoType = $this->resolveVideoType($request->input('video_type_id'), $production);
        $revisionNotes = $request->input('revision_notes');

        $generated = $this->copywriterService->generate(
            title: $production->title,
            description: $production->description,
            keywords: $production->seo_keywords,
            framework: $framework,
            tone: $tone,
            language: $language,
            cliente: $production->cliente,
            videoType: $videoType,
            revisionNotes: $revisionNotes
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

        $videoTypes = $this->videoTypes->active($production->tenant_id);
        $planApproved = $this->planGate->clienteHasApprovedPlan($production->tenant_id, $production->cliente_id);

        return view('roteiros.create', compact('production', 'videoTypes', 'planApproved'));
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
            'video_type_id' => 'nullable|integer',
            'plan_override_reason' => 'nullable|string|max:1000',
        ]);

        $override = $this->planGate->authorizeCreation($production->tenant_id, $production->cliente_id, $request->user(), $validated['plan_override_reason'] ?? null);
        $videoType = $this->resolveVideoType($validated['video_type_id'] ?? null, $production);

        $roteiro = $production->roteiros()->make([
            'content' => $validated['content'],
            'video_type_id' => $videoType?->id,
            'structure' => $videoType?->snapshot(),
            'created_by' => auth()->id(),
        ]);
        $roteiro->forceFill($override)->save();

        $this->adoptTypeOnProduction($production, $videoType);

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

        if (in_array($roteiro->status, ['submitted', 'approved', 'in_production'])) {
            return redirect()->route('roteiros.show', [$production, $roteiro])
                ->with('error', __('Roteiros em revisão pelo cliente, já aprovados ou em produção não podem ser editados. Aguarde a aprovação ou sugestão de mudanças do cliente.'));
        }

        $videoTypes = $this->videoTypes->active($production->tenant_id);
        if ($roteiro->videoType && ! $videoTypes->contains('id', $roteiro->video_type_id)) {
            $videoTypes->push($roteiro->videoType);
        }

        return view('roteiros.edit', compact('production', 'roteiro', 'videoTypes'));
    }

    /**
     * Atualizar roteiro
     */
    public function update(Request $request, Production $production, Roteiro $roteiro): RedirectResponse
    {
        $this->authorize('update', $roteiro);

        if (in_array($roteiro->status, ['submitted', 'approved', 'in_production'])) {
            return redirect()->route('roteiros.show', [$production, $roteiro])
                ->with('error', __('Roteiros em revisão pelo cliente, já aprovados ou em produção não podem ser editados. Aguarde a aprovação ou sugestão de mudanças do cliente.'));
        }

        $validated = $request->validate([
            'content' => 'required|string|min:50',
            'video_type_id' => 'nullable|integer',
        ]);

        if ($roteiro->status === 'rejected') {
            $roteiro->status = 'draft';
            $roteiro->rejection_reason = null;
        }

        $roteiro->content = $validated['content'];

        // Só troca a estrutura quando o tipo muda: manter o mesmo tipo preserva a cópia original,
        // mesmo que o admin tenha editado ou desativado o tipo depois.
        if (array_key_exists('video_type_id', $validated) && (int) $validated['video_type_id'] !== (int) $roteiro->video_type_id) {
            $videoType = $this->resolveVideoType($validated['video_type_id'], $production);
            $roteiro->video_type_id = $videoType?->id;
            $roteiro->structure = $videoType?->snapshot();
            $this->adoptTypeOnProduction($production, $videoType);
        }

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

        if (! $roteiro->submit()) {
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

        if (! $roteiro->approve(auth()->user())) {
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

        if (! $roteiro->reject(auth()->user(), $validated['rejection_reason'])) {
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

        if (! $roteiro->markAsInProduction()) {
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
