@extends('layouts.portal')

@section('title', __('Termos de Uso | NUME Creative'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
    <div class="border-b border-zinc-200 pb-5">
        <h1 class="text-3xl font-extrabold text-zinc-900 tracking-tight">
            {{ __('Termos de Uso da Plataforma NUME Creative') }}
        </h1>
        <p class="text-xs text-zinc-500 mt-2">
            Última atualização: 25/10/2026 · Plataforma NUME Creative (Espanha & Brasil).
        </p>
    </div>

    <div class="text-xs sm:text-sm text-zinc-700 leading-relaxed space-y-6">
        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">1. O serviço</h2>
            <p>
                A NUME Creative é uma plataforma para produtoras audiovisuais e seus clientes: briefing, roteiros, aprovação, entrega de vídeos e publicação nas redes sociais do cliente.
            </p>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">2. Conta e responsabilidades</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>Você é responsável pelos dados de acesso e pelas ações feitas na sua conta.</li>
                <li>Você declara ter os direitos sobre o material que envia e sobre as contas de redes sociais que conecta.</li>
                <li>O conteúdo publicado deve respeitar a lei e as regras de cada rede. A NUME pode recusar ou suspender o envio de conteúdo que as viole.</li>
            </ul>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">3. Publicação nas redes sociais</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>A NUME só publica na sua conta quando você pede (ou agenda) e apenas os vídeos produzidos para você.</li>
                <li>Você pode desconectar uma rede a qualquer momento no portal ou nas configurações da própria rede.</li>
                <li>Ao conectar o YouTube, você também aceita os <a href="https://www.youtube.com/t/terms" class="text-orange-600 underline font-bold" target="_blank" rel="noopener noreferrer">Termos de Serviço do YouTube</a>.</li>
                <li>As redes podem limitar, adiar ou recusar publicações (cotas, revisão de conteúdo, mudanças de política). A NUME não controla essas decisões e não garante alcance nem resultados.</li>
            </ul>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">4. Pagamentos e entrega</h2>
            <p>
                Valores, forma de pagamento e condições de entrega e download dos arquivos (incluindo pagamentos em duas parcelas) são os do pacote contratado e podem variar conforme a produtora responsável.
            </p>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">5. Dados pessoais</h2>
            <p>
                O tratamento de dados segue a nossa
                <a href="{{ route('legal.privacy') }}" class="text-orange-600 underline font-bold">Política de Privacidade</a>.
                Para excluir dados, veja
                <a href="{{ route('legal.data-deletion') }}" class="text-orange-600 underline font-bold">Exclusão de dados</a>.
            </p>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900">6. Contato</h2>
            <p>
                Dúvidas sobre estes termos: <a href="mailto:admin@numecreative.com" class="text-orange-600 underline font-bold">admin@numecreative.com</a>.
            </p>
        </section>
    </div>

    <div class="pt-4 text-center">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-zinc-900 hover:bg-orange-600 text-white text-xs font-bold transition">
            <span>←</span>
            <span>{{ __('Voltar para o Início') }}</span>
        </a>
    </div>
</div>
@endsection
