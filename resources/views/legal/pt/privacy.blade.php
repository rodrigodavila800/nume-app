@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Responsável pelo tratamento</h2>
    <ul class="space-y-1">
        <li><strong>Titular:</strong> {{ $identity['company'] }}</li>
        <li><strong>NIF/CIF:</strong> {{ $identity['cif'] ?? $pending }}</li>
        <li><strong>Endereço:</strong> {{ $identity['address'] ?? $pending }}</li>
        <li><strong>Contato de privacidade:</strong> <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a></li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Quais dados tratamos</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Identificação e contato:</strong> nome, empresa, e-mail e telefone que você nos informa (formulários, portal ou WhatsApp).</li>
        <li><strong>Faturação:</strong> razão social, NIF/CIF e endereço para emitir a fatura. Ficam guardados criptografados.</li>
        <li><strong>Conversas:</strong> mensagens que você envia à Sofi no site ou pelo WhatsApp, e as respostas.</li>
        <li><strong>Projeto:</strong> informações da sua marca (site público, redes que você indicar), roteiros, vídeos e aprovações.</li>
        <li><strong>Redes sociais que você conectar:</strong> identificador da conta e credenciais de acesso da rede (criptografadas), só para publicar os vídeos que você aprovar.</li>
        <li><strong>Pagamentos:</strong> status e referência do pagamento. Os dados do cartão são tratados diretamente pelo gateway; nós não os vemos nem guardamos.</li>
        <li><strong>Dados técnicos:</strong> registros de segurança e de uso necessários para o funcionamento do serviço.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Para quê e com qual base legal</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Atender você, preparar orçamentos e prestar o serviço contratado</strong> (produção, portal, publicação): execução de contrato ou medidas pré-contratuais a seu pedido (art. 6.1.b RGPD).</li>
        <li><strong>Faturar e cumprir obrigações fiscais e contábeis:</strong> obrigação legal (art. 6.1.c RGPD).</li>
        <li><strong>Acompanhamento comercial do seu pedido</strong> (CRM) e segurança da plataforma: interesse legítimo (art. 6.1.f RGPD). Você pode se opor a qualquer momento.</li>
        <li><strong>Conteúdo externo</strong> (players do YouTube e do Vimeo): seu consentimento, que você pode retirar na <a href="{{ route('legal.cookies') }}" class="text-orange-700 font-semibold underline">Política de cookies</a>.</li>
    </ul>
    <p>Não enviamos comunicações comerciais por e-mail sem o seu consentimento prévio.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Inteligência artificial</h2>
    <p>A <strong>Sofi é uma assistente de inteligência artificial</strong>, não uma pessoa. Ela responde no site e no WhatsApp, recomenda pacotes do catálogo e prepara propostas com os preços configurados pela produtora. Ela não toma decisões com efeitos jurídicos sobre você: a contratação e o pagamento são sempre confirmados por você, e você pode pedir para falar com uma pessoa da equipe a qualquer momento.</p>
    <p>Para gerar as respostas, o texto da conversa é enviado ao fornecedor do modelo de IA (Google, modelos Gemini).</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">5. Quem recebe os dados (operadores)</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Google:</strong> modelo de IA (Gemini) e envio dos e-mails do sistema.</li>
        <li><strong>Meta Platforms:</strong> WhatsApp Business (mensagens) e, se você conectar, Instagram e Facebook.</li>
        <li><strong>Gateways de pagamento:</strong> Redsys (cartão e Bizum) e, quando ativo, Stripe.</li>
        <li><strong>Redes sociais que você conectar</strong> para publicar seus vídeos (YouTube, TikTok, LinkedIn, Pinterest, X, Threads).</li>
        <li><strong>Fornecedor de hospedagem</strong> do servidor da plataforma.</li>
        <li>Órgãos públicos quando a lei exigir (por exemplo, a Agência Tributária).</li>
    </ul>
    <p><strong>Transferências internacionais:</strong> Google, Meta e Stripe podem tratar dados fora do Espaço Econômico Europeu (EUA), com as garantias do Quadro de Privacidade de Dados UE-EUA ou das cláusulas contratuais-tipo da Comissão Europeia.</p>
</section>

<section id="redes-sociais" class="{{ $box }}">
    <h2 class="{{ $h2 }}">5.1 Contas de Redes Sociais Conectadas (YouTube, Instagram, Facebook, TikTok e outras)</h2>
    <p>Quando você conecta uma rede social no portal, a autorização é feita na página oficial da própria rede. Nunca recebemos nem guardamos a sua senha.</p>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Dados que recebemos:</strong> identificador, nome, @ e foto de perfil do canal ou da conta conectada, e as chaves de acesso (tokens) que a rede fornece.</li>
        <li><strong>Para que usamos:</strong> exclusivamente para publicar na sua conta, a seu pedido, os vídeos produzidos para você, e para mostrar no portal qual conta está conectada.</li>
        <li><strong>O que não fazemos:</strong> não lemos mensagens nem seus outros vídeos ou posts, não vendemos nem compartilhamos esses dados e não os usamos para publicidade nem para treinar modelos de IA.</li>
        <li><strong>Como guardamos:</strong> os tokens ficam criptografados no nosso servidor e só são usados na publicação ou na renovação do acesso.</li>
    </ul>
    <p><strong>YouTube e Google.</strong> Usamos a YouTube API Services. Ao conectar o YouTube, você também concorda com os
        <a href="https://www.youtube.com/t/terms" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Termos de Serviço do YouTube</a> e com a
        <a href="https://policies.google.com/privacy" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Política de Privacidade do Google</a>.
        Você pode revogar o acesso a qualquer momento em
        <a href="https://myaccount.google.com/permissions" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a>.</p>
    <p class="text-xs text-zinc-600">
        {{ $identity['company'] }}'s use and transfer to any other app of information received from Google APIs will adhere to the
        <a href="https://developers.google.com/terms/api-services-user-data-policy" class="text-orange-700 font-semibold underline" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>,
        including the Limited Use requirements.
    </p>
    <p><strong>Como desconectar e apagar:</strong> no portal, em Redes Sociais, use o botão <em>Desconectar</em>: o acesso é revogado na rede e os tokens são apagados na hora. Para apagar também o histórico de publicações e demais dados, veja <a href="{{ route('legal.data-deletion') }}" class="text-orange-700 font-semibold underline">Exclusão de dados</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">6. Por quanto tempo guardamos</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li><strong>Clientes:</strong> enquanto durar a relação e, depois, pelo prazo exigido por lei (faturação e contabilidade: até 6 anos, Código de Comércio espanhol).</li>
        <li><strong>Pedidos que não viram contrato:</strong> são anonimizados automaticamente após {{ (int) config('crm.anonymize_lost_after_months', 24) }} meses.</li>
        <li><strong>Conversas:</strong> enquanto forem necessárias para atender você ou até você pedir a exclusão.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">7. Seus direitos</h2>
    <p>Você pode exercer os direitos de <strong>acesso, retificação, exclusão, oposição, limitação do tratamento e portabilidade</strong>, e retirar seu consentimento quando quiser, escrevendo para <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a>. Respondemos em até um mês.</p>
    <p>Se considerar que seu pedido não foi bem atendido, você pode apresentar reclamação à <strong>Agência Espanhola de Proteção de Dados</strong> (Agencia Española de Protección de Datos, AEPD) (<a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer" class="text-orange-700 font-semibold underline">www.aepd.es</a>).</p>
    <p>Para pedir a exclusão dos dados de uma rede social conectada, veja também <a href="{{ route('legal.data-deletion') }}" class="text-orange-700 font-semibold underline">exclusão de dados</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">8. Segurança</h2>
    <p>Usamos conexão criptografada (HTTPS), criptografia dos dados de faturação e das credenciais de redes sociais, controle de acesso por empresa e registros de segurança sem dados sensíveis. Nossos serviços são voltados a empresas e profissionais maiores de idade.</p>
</section>
