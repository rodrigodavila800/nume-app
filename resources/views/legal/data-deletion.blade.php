@extends('layouts.portal')

@section('title', __('Exclusão de Dados | NUME Creative'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
    <div class="border-b border-zinc-200 pb-5">
        <h1 class="text-3xl font-extrabold text-zinc-900 tracking-tight">
            {{ __('Como excluir os seus dados da NUME Creative') }}
        </h1>
        <p class="text-xs text-zinc-500 mt-2">
            Última atualização: 25/10/2026 · Vale para a conta do portal e para as redes sociais conectadas (YouTube, Instagram, Facebook, TikTok e outras).
        </p>
    </div>

    <div class="text-xs sm:text-sm text-zinc-700 leading-relaxed space-y-6">
        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">1. Só desconectar uma rede social</h2>
            <ol class="list-decimal pl-5 space-y-1">
                <li>Entre no portal e abra <strong>Redes Sociais</strong>.</li>
                <li>No cartão da rede, clique em <strong>Desconectar</strong>.</li>
            </ol>
            <p>
                O acesso é revogado na própria rede e os tokens são apagados do nosso servidor na hora. Envios ainda não feitos para essa conta são cancelados.
            </p>
            <p>
                Você também pode revogar pelo lado da rede, sem passar por nós: no Google em
                <a href="https://myaccount.google.com/permissions" class="text-orange-600 underline font-bold" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a>;
                no Facebook e no Instagram, em Configurações > Apps e sites; no TikTok, em Configurações > Segurança e permissões > Apps e serviços.
            </p>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">2. Excluir a sua conta e todos os dados</h2>
            <p>
                Envie um e-mail para <a href="mailto:admin@numecreative.com?subject=Exclus%C3%A3o%20de%20dados" class="text-orange-600 underline font-bold">admin@numecreative.com</a>
                com o assunto <strong>Exclusão de dados</strong>, a partir do e-mail cadastrado no portal.
            </p>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Prazo:</strong> respondemos em até 15 dias úteis, conforme o RGPD (Espanha) e a LGPD (Brasil).</li>
                <li><strong>O que apagamos:</strong> tokens e dados das redes conectadas, histórico de publicações, vídeos enviados e dados de perfil.</li>
                <li><strong>O que podemos ter de manter:</strong> faturas e registros exigidos pela lei fiscal, pelo prazo legal.</li>
            </ul>
        </section>

        <p class="text-xs text-zinc-500">
            Mais detalhes sobre os dados que tratamos em nossa
            <a href="{{ route('legal.privacy') }}#redes-sociais" class="text-orange-600 underline font-bold">Política de Privacidade</a>.
        </p>
    </div>

    <div class="pt-4 text-center">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-zinc-900 hover:bg-orange-600 text-white text-xs font-bold transition">
            <span>←</span>
            <span>{{ __('Voltar para o Início') }}</span>
        </a>
    </div>
</div>
@endsection
