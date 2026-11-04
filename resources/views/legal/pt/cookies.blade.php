@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; $th = 'text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 py-2 pr-3'; $td = 'py-2 pr-3 align-top'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. O que são e como usamos</h2>
    <p>Cookies são pequenos arquivos que o navegador guarda ao visitar um site. A {{ $identity['company'] }} <strong>não usa cookies de análise, publicidade nem rastreamento</strong>. Usamos apenas os cookies necessários para a plataforma funcionar e, se você aceitar, o conteúdo externo dos players de vídeo.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Cookies necessários (não exigem consentimento)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr><th class="{{ $th }}">Cookie</th><th class="{{ $th }}">Finalidade</th><th class="{{ $th }}">Duração</th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                <tr><td class="{{ $td }} font-mono">{{ $sessionCookie }}</td><td class="{{ $td }}">Mantém a sua sessão (login, idioma, conversa com a Sofi).</td><td class="{{ $td }}">{{ $sessionMinutes }} minutos de inatividade</td></tr>
                <tr><td class="{{ $td }} font-mono">XSRF-TOKEN</td><td class="{{ $td }}">Protege os formulários contra envios falsificados (segurança).</td><td class="{{ $td }}">Sessão</td></tr>
                <tr><td class="{{ $td }} font-mono">remember_*</td><td class="{{ $td }}">Mantém o seu acesso se você marcar "lembrar de mim" ao entrar no portal.</td><td class="{{ $td }}">Até você sair da conta</td></tr>
                <tr><td class="{{ $td }} font-mono">nume_cookie_consent</td><td class="{{ $td }}">Guarda a sua escolha sobre o conteúdo externo.</td><td class="{{ $td }}">12 meses</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Conteúdo externo (só com o seu consentimento)</h2>
    <p>Os vídeos do portfólio e das suas produções podem estar hospedados no <strong>YouTube</strong> (Google, modo de privacidade aprimorada youtube-nocookie.com) ou no <strong>Vimeo</strong>. Ao carregar o player, esses serviços podem guardar cookies ou dados no seu navegador e conhecer o seu endereço IP, segundo as políticas deles.</p>
    <p>Se você não aceitar, o player não é carregado e aparece um link para ver o vídeo direto na plataforma de origem.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Como mudar a sua escolha</h2>
    <p>Você pode mudar a sua escolha quando quiser:</p>
    <button type="button" data-open-cookie-settings class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-bold text-white bg-zinc-900 hover:bg-zinc-700 transition">Configurar cookies</button>
    <p>Você também pode apagar ou bloquear os cookies nas configurações do navegador. Se bloquear os cookies necessários, o portal não funcionará corretamente.</p>
</section>
