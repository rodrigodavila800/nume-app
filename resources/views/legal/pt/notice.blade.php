@php $box = 'bg-white p-5 sm:p-6 rounded-2xl border border-zinc-200 space-y-3'; $h2 = 'text-base font-bold text-zinc-900'; @endphp

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">1. Titular do site</h2>
    <p>Em cumprimento do artigo 10 da Lei espanhola 34/2002, de Serviços da Sociedade da Informação e de Comércio Eletrônico (LSSI-CE):</p>
    <ul class="space-y-1">
        <li><strong>Titular:</strong> {{ $identity['company'] }}</li>
        <li><strong>NIF/CIF:</strong> {{ $identity['cif'] ?? $pending }}</li>
        <li><strong>Endereço:</strong> {{ $identity['address'] ?? $pending }}</li>
        <li><strong>E-mail:</strong> <a href="mailto:{{ $identity['email'] }}" class="text-orange-700 font-semibold underline">{{ $identity['email'] }}</a></li>
        <li><strong>Atividade:</strong> produção audiovisual, estratégia de conteúdo em vídeo e plataforma de gestão para produtoras.</li>
    </ul>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">2. Condições de uso</h2>
    <p>O acesso a este site é gratuito e implica a aceitação deste aviso legal. Você se compromete a usar o site e o portal de forma lícita, sem prejudicar o funcionamento nem acessar contas ou dados de terceiros. As condições de cada contratação estão na proposta e nos <a href="{{ route('legal.terms') }}" class="text-orange-700 font-semibold underline">termos do serviço</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">3. Propriedade intelectual</h2>
    <p>Os textos, designs, marcas e vídeos deste site pertencem ao titular ou aos seus clientes, que autorizaram a exibição. Não é permitida a reprodução nem a distribuição sem autorização. Os direitos sobre os vídeos produzidos para cada cliente seguem o que foi combinado na contratação.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">4. Responsabilidade</h2>
    <p>Procuramos manter as informações corretas e atualizadas. Os preços mostrados no site e pela Sofi são orientativos até a emissão da proposta, que é o documento que vale para a contratação. Não respondemos pelo conteúdo de sites de terceiros com link a partir daqui.</p>
    <p>A Sofi é uma assistente de inteligência artificial: as respostas dela podem conter erros e não substituem a proposta formal nem o atendimento de uma pessoa da equipe.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">5. Proteção de dados e cookies</h2>
    <p>O tratamento dos seus dados está explicado na <a href="{{ route('legal.privacy') }}" class="text-orange-700 font-semibold underline">política de privacidade</a> e o uso de cookies na <a href="{{ route('legal.cookies') }}" class="text-orange-700 font-semibold underline">política de cookies</a>.</p>
</section>

<section class="{{ $box }}">
    <h2 class="{{ $h2 }}">6. Legislação aplicável</h2>
    <p>Este aviso legal é regido pela legislação espanhola. Se você for consumidor, poderá recorrer aos tribunais do seu domicílio.</p>
</section>
