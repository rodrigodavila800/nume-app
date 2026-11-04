@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. O serviço</h2>
    <p>A {{ $identity['company'] }} oferece uma plataforma para produtoras audiovisuais e seus clientes: briefing, roteiros, aprovação, entrega de vídeos e publicação nas redes sociais do cliente.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Conta e responsabilidades</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li>Você é responsável pelos dados de acesso e pelas ações feitas na sua conta.</li>
        <li>Você declara ter os direitos sobre o material que envia e sobre as contas de redes sociais que conecta.</li>
        <li>O conteúdo publicado deve respeitar a lei e as regras de cada rede. Podemos recusar ou suspender o envio de conteúdo que as viole.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Publicação nas redes sociais</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li>Só publicamos na sua conta quando você pede (ou agenda) e apenas os vídeos produzidos para você.</li>
        <li>Você pode desconectar uma rede a qualquer momento no portal ou nas configurações da própria rede.</li>
        <li>Ao conectar o YouTube, você também aceita os <a href="https://www.youtube.com/t/terms" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Termos de Serviço do YouTube</a>.</li>
        <li>As redes podem limitar, adiar ou recusar publicações (cotas, revisão de conteúdo, mudanças de política). Não controlamos essas decisões e não garantimos alcance nem resultados.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Propostas, pagamentos e entrega</h2>
    <p>Os valores, a forma de pagamento e as condições de entrega e download dos arquivos (incluindo pagamento com sinal e saldo na entrega) são os da proposta aprovada. Os preços mostrados no site ou pela Sofi, assistente de inteligência artificial, são orientativos até a proposta ser emitida.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">5. Dados pessoais</h2>
    <p>O tratamento de dados segue a nossa <a href="{{ route('legal.privacy') }}" class="text-orange-700 font-semibold underline">Política de Privacidade</a>. Para excluir dados, veja <a href="{{ route('legal.data-deletion') }}" class="text-orange-700 font-semibold underline">Exclusão de dados</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">6. Contato</h2>
    <p>Dúvidas sobre estes termos: <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a>.</p>
</section>
