<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualização da Produção - {{ $tenant?->name ?? 'NUME' }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f5; color: #27272a; margin: 0; padding: 24px; line-height: 1.5;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #e4e4e7;">
        
        <!-- Header -->
        <div style="background-color: #18181b; padding: 32px 30px; text-align: center;">
            <p style="color: #ea580c; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin: 0 0 6px 0;">
                SOFI NOTIFICAÇÕES · {{ strtoupper($tenant?->name ?? 'NUME CREATIVE') }}
            </p>
            <h1 style="color: #ffffff; font-size: 20px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">
                Status da Sua Produção Atualizado
            </h1>
        </div>

        <!-- Conteúdo Principal -->
        <div style="padding: 32px 30px;">
            <p style="font-size: 16px; font-weight: 700; color: #18181b; margin: 0 0 12px 0;">
                Olá, {{ $cliente?->name ?? 'Cliente' }}!
            </p>
            
            <p style="font-size: 14px; color: #52525b; margin: 0 0 20px 0; line-height: 1.6;">
                Aqui é a <strong>Sofi</strong>. A equipe da <strong>{{ $tenant?->name ?? 'NUME Creative' }}</strong> acabou de atualizar o andamento do seu projeto:
            </p>

            <!-- Card de Destaque da Produção -->
            <div style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <p style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #71717a; margin: 0 0 4px 0;">
                    Projeto
                </p>
                <h2 style="font-size: 16px; font-weight: 800; color: #18181b; margin: 0 0 12px 0;">
                    {{ $production->title }}
                </h2>

                <div style="display: inline-block; padding: 6px 14px; border-radius: 9999px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
                    @if(in_array($newStatus, ['approved', 'published'])) background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;
                    @elseif(in_array($newStatus, ['ready', 'pending_review'])) background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;
                    @elseif(in_array($newStatus, ['in_production', 'editing'])) background-color: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;
                    @else background-color: #f4f4f5; color: #52525b; border: 1px solid #e4e4e7;
                    @endif">
                    Status Atual: {{ ucfirst(str_replace('_', ' ', $newStatus)) }}
                </div>
            </div>

            <!-- Explicação da Etapa Atual -->
            <div style="background-color: #fff7ed; border-left: 4px solid #ea580c; padding: 14px 18px; border-radius: 0 8px 8px 0; margin-bottom: 28px;">
                <p style="font-size: 13px; color: #9a3412; margin: 0; font-weight: 600; line-height: 1.5;">
                    @if($newStatus === 'in_production')
                        🎬 <strong>Em Gravação:</strong> Nossa equipe técnica está em campo captando os takes e planos cinematográficos com padrão 4K.
                    @elseif($newStatus === 'editing')
                        ✂️ <strong>Na Ilha de Edição:</strong> Montagem rítmica, sound design e color grading para reter o público nos primeiros 3 segundos.
                    @elseif(in_array($newStatus, ['ready', 'pending_review']))
                        ✨ <strong>Pronto para Revisão:</strong> O corte foi finalizado! Você já pode assistir à prévia e aprovar diretamente pelo portal.
                    @elseif($newStatus === 'approved')
                        🎉 <strong>Aprovado com Sucesso:</strong> Projeto validado! Os entregáveis em alta resolução estão liberados para download e agendamento.
                    @elseif($newStatus === 'published')
                        🚀 <strong>Publicado nas Redes:</strong> Seu vídeo entrou no ar! Acompanhe as métricas de engajamento diretamente no painel.
                    @else
                        📋 <strong>Em Andamento:</strong> Projeto em desenvolvimento conforme o cronograma estratégico.
                    @endif
                </p>
            </div>

            <!-- Botão de Ação -->
            <div style="text-align: center; margin-bottom: 30px;">
                <a href="{{ $portalUrl }}" target="_blank" style="display: inline-block; background-color: #ea580c; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 14px; padding: 14px 28px; border-radius: 12px; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);">
                    Acessar Produção no Portal →
                </a>
            </div>

            <p style="font-size: 12px; color: #71717a; text-align: center; margin: 0;">
                Dúvidas ou solicitações? Você pode responder a este e-mail ou abrir um chamado com a nossa equipe pelo portal.
            </p>
        </div>

        <!-- Footer -->
        <div style="background-color: #fafafa; border-top: 1px solid #e4e4e7; padding: 20px; text-align: center; font-size: 11px; color: #a1a1aa;">
            <p style="margin: 0 0 4px 0;">{{ $tenant?->name ?? 'NUME Creative' }} · Plataforma Inteligente de Produção Audiovisual</p>
            <p style="margin: 0;">Mensagem automática gerada pela Sofi IA para {{ $cliente?->email ?? 'seu e-mail' }}.</p>
        </div>

    </div>
</body>
</html>

