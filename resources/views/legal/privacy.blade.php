@extends('layouts.portal')

@section('title', __('Política de Privacidade & Cookies | NUME Creative'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
    <div class="border-b border-zinc-200 pb-5">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-100 text-orange-800 border border-orange-200 mb-2">
            Compliance Legal Espanha (RGPD / LOPDGDD) & Brasil (LGPD)
        </span>
        <h1 class="text-3xl font-extrabold text-zinc-900 tracking-tight">
            {{ __('Política de Privacidade, Proteção de Dados e Gestão de Cookies') }}
        </h1>
        <p class="text-xs text-zinc-500 mt-2">
            Última atualização: 25/10/2026 · Aplicável à plataforma NUME Creative (Espanha & Brasil).
        </p>
    </div>

    <div class="prose prose-zinc max-w-none text-xs sm:text-sm text-zinc-700 leading-relaxed space-y-6">
        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900 flex items-center gap-2">
                <span>1.</span>
                <span>Responsável pelo Tratamento de Dados</span>
            </h2>
            <p>
                A <strong>NUME Creative</strong>, com base em Zaragoza (Espanha) e atuação integrada no Brasil, atua como controladora dos dados coletados para fornecimento de serviços de produção audiovisual estratégica, gestão de roteiros e faturamento.
            </p>
            <p class="text-xs text-zinc-500">
                Contato de Encarregado de Dados (DPO / Responsável RGPD): <a href="mailto:admin@numecreative.com" class="text-orange-600 font-bold underline">admin@numecreative.com</a>.
            </p>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900 flex items-center gap-2">
                <span>2.</span>
                <span>Bases Legais e Finalidades (RGPD e LGPD)</span>
            </h2>
            <ul class="list-disc pl-5 space-y-1 text-xs sm:text-sm">
                <li><strong>Execução de Contrato:</strong> Coleta de dados corporativos (nome, empresa, e-mail, telefone) para prestação dos serviços contratados nos pacotes audiovisuais.</li>
                <li><strong>Obrigação Legal e Fiscal:</strong> Emissão de faturas em conformidade com as leis fiscais da Espanha (Hacienda) e do Brasil (Receita Federal).</li>
                <li><strong>Legítimo Interesse & Segurança:</strong> Proteção contra fraudes, tentativas de invasão e auditoria de logs de sessão.</li>
                <li><strong>Consentimento:</strong> Utilização de cookies analíticos e de preferências de navegação.</li>
            </ul>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900 flex items-center gap-2">
                <span>3.</span>
                <span>Política de Cookies e Armazenamento Local</span>
            </h2>
            <p>
                Utilizamos cookies estritamente necessários para permitir a autenticação segura do cliente no portal e cookies analíticos para mensuração de retenção na vitrine.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border border-zinc-200 rounded-lg overflow-hidden">
                    <thead class="bg-zinc-100 text-zinc-800 font-bold">
                        <tr>
                            <th class="p-2.5 border-b border-zinc-200">Tipo de Cookie</th>
                            <th class="p-2.5 border-b border-zinc-200">Finalidade</th>
                            <th class="p-2.5 border-b border-zinc-200">Duração</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        <tr>
                            <td class="p-2.5 font-semibold text-zinc-900">Sessão & CSRF</td>
                            <td class="p-2.5 text-zinc-600">Autenticação e segurança contra requisições forjadas.</td>
                            <td class="p-2.5 text-zinc-500">2 horas / Sessão</td>
                        </tr>
                        <tr>
                            <td class="p-2.5 font-semibold text-zinc-900">Consentimento</td>
                            <td class="p-2.5 text-zinc-600">Armazena a decisão de aceite de cookies do usuário.</td>
                            <td class="p-2.5 text-zinc-500">1 ano</td>
                        </tr>
                        <tr>
                            <td class="p-2.5 font-semibold text-zinc-900">Idioma Preferido</td>
                            <td class="p-2.5 text-zinc-600">Salva a preferência entre Português e Espanhol.</td>
                            <td class="p-2.5 text-zinc-500">1 ano</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900 flex items-center gap-2">
                <span>4.</span>
                <span>Direitos do Titular de Dados (ARCO / LGPD)</span>
            </h2>
            <p>
                Em conformidade com o <strong>Regulamento Geral de Proteção de Dados da UE (RGPD - Arts. 15 a 22)</strong> e com a <strong>Lei Geral de Proteção de Dados do Brasil (LGPD - Art. 18)</strong>, você tem o direito de:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Acesso & Confirmação:</strong> Saber quais dados temos sobre você.
                </div>
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Retificação:</strong> Corrigir dados incompletos ou inexatos.
                </div>
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Eliminação & Esquecimento:</strong> Solicitar a exclusão de seus dados.
                </div>
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Portabilidade:</strong> Exportar seus roteiros e histórico de vídeos.
                </div>
            </div>
            <p class="text-xs text-zinc-500">
                Para exercer qualquer direito, envie solicitação para <a href="mailto:admin@numecreative.com" class="text-orange-600 underline font-bold">admin@numecreative.com</a> com resposta garantida em até 15 dias úteis.
            </p>
        </section>

        <section id="redes-sociais" class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900 flex items-center gap-2">
                <span>5.</span>
                <span>Contas de Redes Sociais Conectadas (YouTube, Instagram, Facebook, TikTok e outras)</span>
            </h2>
            <p>
                Quando você conecta uma rede social no portal, a autorização é feita na página da própria rede (login oficial). A NUME nunca recebe nem guarda a sua senha.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Dados que recebemos:</strong> identificador, nome, @ e foto de perfil do canal ou da conta conectada, e as chaves de acesso (tokens) que a rede fornece.
                </div>
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Para que usamos:</strong> exclusivamente para publicar, na sua conta e por pedido seu, os vídeos produzidos pela NUME, e para mostrar no portal qual conta está conectada.
                </div>
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>O que não fazemos:</strong> não lemos mensagens, não lemos seus outros vídeos ou posts, não vendemos nem compartilhamos esses dados e não os usamos para publicidade ou para treinar modelos de IA.
                </div>
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <strong>Como guardamos:</strong> os tokens ficam criptografados no nosso servidor e só são usados no momento da publicação ou da renovação do acesso.
                </div>
            </div>
            <p>
                <strong>YouTube e Google.</strong> Usamos a YouTube API Services. Ao conectar o YouTube, você também concorda com os
                <a href="https://www.youtube.com/t/terms" class="text-orange-600 underline font-bold" target="_blank" rel="noopener noreferrer">Termos de Serviço do YouTube</a>
                e com a
                <a href="https://policies.google.com/privacy" class="text-orange-600 underline font-bold" target="_blank" rel="noopener noreferrer">Política de Privacidade do Google</a>.
                Você pode revogar o acesso da NUME a qualquer momento em
                <a href="https://myaccount.google.com/permissions" class="text-orange-600 underline font-bold" target="_blank" rel="noopener noreferrer">myaccount.google.com/permissions</a>.
            </p>
            <p class="text-xs text-zinc-600">
                NUME Creative's use and transfer to any other app of information received from Google APIs will adhere to the
                <a href="https://developers.google.com/terms/api-services-user-data-policy" class="text-orange-600 underline font-bold" target="_blank" rel="noopener noreferrer">Google API Services User Data Policy</a>,
                including the Limited Use requirements.
            </p>
            <p>
                <strong>Como desconectar e apagar:</strong> no portal, em Redes Sociais, use o botão <em>Desconectar</em>: o acesso é revogado na rede e os tokens são apagados na hora. Para apagar também o histórico de publicações e demais dados, veja
                <a href="{{ route('legal.data-deletion') }}" class="text-orange-600 underline font-bold">Exclusão de dados</a>.
            </p>
        </section>

        <section class="bg-white p-6 rounded-2xl border border-zinc-200 space-y-3">
            <h2 class="text-base font-bold text-zinc-900 flex items-center gap-2">
                <span>6.</span>
                <span>Armazenamento, Compartilhamento e Retenção</span>
            </h2>
            <ul class="list-disc pl-5 space-y-1 text-xs sm:text-sm">
                <li><strong>Vídeos enviados:</strong> ficam em armazenamento privado e só são entregues por links temporários assinados.</li>
                <li><strong>Compartilhamento:</strong> só com a rede social escolhida por você, no ato da publicação, e com provedores de infraestrutura e pagamento necessários à prestação do serviço.</li>
                <li><strong>Retenção:</strong> tokens de redes são apagados ao desconectar. O histórico de publicações (título, status e link) é mantido enquanto a conta existir, para consulta e faturamento, e apagado mediante solicitação.</li>
            </ul>
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
