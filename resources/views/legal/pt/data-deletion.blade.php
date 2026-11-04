@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<p class="text-xs text-zinc-500">Vale para a conta do portal e para as redes sociais conectadas (YouTube, Instagram, Facebook, TikTok e outras).</p>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Só desconectar uma rede social</h2>
    <ol class="list-decimal pl-5 space-y-1">
        <li>Entre no portal e abra <strong>Redes Sociais</strong>.</li>
        <li>No cartão da rede, clique em <strong>Desconectar</strong>.</li>
    </ol>
    <p>Os tokens de acesso são apagados do nosso servidor na hora (no YouTube/Google, no TikTok e no X o acesso também é revogado na própria rede). Envios ainda não feitos para essa conta são cancelados.</p>
    <p>Você também pode revogar pelo lado da rede, sem passar por nós: no Google em
        <a href="https://myaccount.google.com/permissions" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a>;
        no Facebook e no Instagram, em Configurações &gt; Apps e sites; no Threads, nas configurações de apps e sites da conta; no LinkedIn, em Configurações &gt; Privacidade de dados &gt; Serviços permitidos; no Pinterest, nas configurações de segurança, em apps com acesso à conta; no TikTok, em Configurações &gt; Segurança e permissões &gt; Apps e serviços; no X, em Configurações &gt; Segurança e acesso à conta &gt; Apps e sessões.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Excluir a sua conta e todos os dados</h2>
    <p>Envie um e-mail para <a href="mailto:{{ $identity['email'] }}?subject=Exclus%C3%A3o%20de%20dados" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a> com o assunto <strong>Exclusão de dados</strong>, a partir do e-mail cadastrado no portal.</p>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Prazo:</strong> respondemos em até 15 dias úteis, dentro do prazo do RGPD e da LOPDGDD (Espanha).</li>
        <li><strong>O que apagamos:</strong> tokens e dados das redes conectadas, histórico de publicações, vídeos enviados e dados de perfil.</li>
        <li><strong>O que podemos ter de manter:</strong> faturas e registros exigidos pela lei fiscal, pelo prazo legal.</li>
    </ul>
</section>

<p class="text-xs text-zinc-500">Mais detalhes sobre os dados que tratamos na nossa <a href="{{ route('legal.privacy') }}#redes-sociais" class="text-orange-700 font-semibold underline">Política de Privacidade</a>.</p>
