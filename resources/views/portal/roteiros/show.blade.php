@extends('layouts.portal')

@section('title', 'Revisão de Roteiro #' . $roteiro->id . ' | NUME Portal')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-6">
    
    <!-- Top Bar Navigation -->
    <div class="flex items-center justify-between">
        <a href="{{ route('portal.productions.show', $roteiro->production) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-orange-600 transition">
            ← {{ __('Voltar para a Produção') }}
        </a>
        <span class="text-xs font-mono font-semibold text-zinc-500 bg-zinc-100 px-2.5 py-1 rounded-lg border border-zinc-200">REF: {{ strtoupper(substr($roteiro->uuid, 0, 8)) }}</span>
    </div>

    <!-- Feedback Alerts -->
    @if (session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-xl text-sm font-semibold flex items-center gap-2">
            <span>✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-xl text-sm font-semibold flex items-center gap-2">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Card Principal de Roteiro -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-zinc-100">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-orange-600">Revisão de Conteúdo</span>
                <h1 class="text-2xl font-extrabold text-zinc-900 mt-1">
                    Estrutura & Roteiro de Vídeo
                </h1>
                <p class="text-xs text-zinc-500 mt-1">
                    Desenvolvido com padrão de retenção de 3 segundos, quebra de padrão e CTA de alta conversão.
                </p>
            </div>
            
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider
                @class([
                    'bg-zinc-100 text-zinc-700' => $roteiro->status === 'draft',
                    'bg-amber-100 text-amber-800 border border-amber-200' => $roteiro->status === 'submitted',
                    'bg-emerald-100 text-emerald-800 border border-emerald-200' => in_array($roteiro->status, ['approved', 'in_production']),
                    'bg-rose-100 text-rose-800 border border-rose-200' => $roteiro->status === 'rejected',
                ])">
                @if($roteiro->status === 'submitted')
                    ⚠️ Aguardando Sua Aprovação
                @elseif($roteiro->status === 'approved')
                    ✅ Roteiro Aprovado
                @elseif($roteiro->status === 'in_production')
                    🎬 Em Produção
                @elseif($roteiro->status === 'rejected')
                    🔄 Ajustes Solicitados
                @else
                    {{ $roteiro->status }}
                @endif
            </span>
        </div>

        <!-- Box do Conteúdo do Roteiro -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-zinc-600">
                <span>Texto do Roteiro / Cenas</span>
                <span>Visualização Oficial</span>
            </div>
            <div class="bg-zinc-950 text-zinc-100 p-6 rounded-xl border border-zinc-800 font-mono text-sm leading-relaxed overflow-x-auto max-h-[60vh] overflow-y-auto whitespace-pre-wrap shadow-inner selection:bg-orange-500 selection:text-white">
{{ $roteiro->content }}
            </div>
        </div>

        @if ($roteiro->status === 'rejected' && $roteiro->rejection_reason)
            <div class="bg-rose-50 border border-rose-200 rounded-xl p-5 space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-rose-800">Histórico de Ajustes Solicitados:</p>
                <p class="text-sm text-rose-900 leading-relaxed">{{ $roteiro->rejection_reason }}</p>
            </div>
        @endif

        <!-- CTA & Ações de Decisão -->
        @if ($roteiro->status === 'submitted')
            <div class="pt-4 border-t border-zinc-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-zinc-500">
                    Aprovar este roteiro libera imediatamente o agendamento de gravação e captação.
                </p>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="button" 
                            onclick="document.getElementById('reject-modal').classList.remove('hidden')"
                            class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl text-xs font-bold text-zinc-700 bg-zinc-100 hover:bg-zinc-200 border border-zinc-300 transition">
                        Solicitar Ajustes
                    </button>

                    <form action="{{ route('portal.roteiros.approve', $roteiro) }}" method="POST" class="flex-1 sm:flex-initial">
                        @csrf
                        <button type="submit" 
                                class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 shadow-md shadow-orange-900/20 transition-all transform active:scale-95">
                            Aprovar Roteiro & Liberar Produção ✅
                        </button>
                    </form>
                </div>
            </div>

            <!-- Modal de Ajustes -->
            <div id="reject-modal" class="hidden fixed inset-0 z-50 bg-zinc-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl p-6 sm:p-8 max-w-lg w-full border border-zinc-200 shadow-2xl space-y-4">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-orange-600">Ajustes Criativos</span>
                        <h3 class="text-lg font-bold text-zinc-900 mt-1">O que gostaria de ajustar no roteiro?</h3>
                        <p class="text-xs text-zinc-500 mt-1">
                            Indique detalhes como ritmo do gancho inicial, termos técnicos ou a chamada para ação final.
                        </p>
                    </div>

                    <form action="{{ route('portal.roteiros.reject', $roteiro) }}" method="POST" class="space-y-4">
                        @csrf
                        <textarea name="rejection_reason" rows="4" required minlength="10" maxlength="500"
                            class="w-full rounded-xl border border-zinc-300 p-3.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 focus:outline-none"
                            placeholder="Exemplo: Gostaria de deixar o gancho dos primeiros 3 segundos mais provocativo para o público de empresários..."></textarea>
                        
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" 
                                    onclick="document.getElementById('reject-modal').classList.add('hidden')"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100 transition">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    class="px-5 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 transition">
                                Enviar Ajustes para a Produtora
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
