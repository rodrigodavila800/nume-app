<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte Final Pronto para Aprovação - {{ $tenant?->name ?? 'NUME' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f4f5;
            color: #27272a;
            margin: 0;
            padding: 24px;
            line-height: 1.5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid #e4e4e7;
        }
        .header {
            background-color: #18181b;
            padding: 32px 30px;
            text-align: center;
        }
        .header-title {
            color: #ffffff;
            font-size: 20px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .header-tag {
            color: #ea580c;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 6px 0 0 0;
        }
        .content {
            padding: 32px 30px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 12px;
        }
        .intro-text {
            font-size: 14px;
            color: #52525b;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .video-card {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .video-title {
            font-size: 15px;
            font-weight: 700;
            color: #18181b;
            margin: 0 0 6px 0;
        }
        .video-production {
            font-size: 12px;
            color: #71717a;
            margin: 0;
        }
        .escrow-alert {
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .escrow-alert-title {
            font-size: 13px;
            font-weight: 700;
            color: #9a3412;
            margin: 0 0 4px 0;
        }
        .escrow-alert-desc {
            font-size: 12px;
            color: #c2410c;
            margin: 0;
            line-height: 1.5;
        }
        .cta-section {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
        }
        .cta-hint {
            font-size: 11px;
            color: #a1a1aa;
            margin-top: 10px;
        }
        .benefits-list {
            margin: 24px 0;
            padding: 0;
            list-style: none;
        }
        .benefit-item {
            font-size: 13px;
            color: #3f3f46;
            margin-bottom: 10px;
            padding-left: 24px;
            position: relative;
        }
        .benefit-item::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: 800;
        }
        .footer {
            background-color: #fafafa;
            border-top: 1px solid #e4e4e7;
            padding: 24px 30px;
            text-align: center;
            font-size: 12px;
            color: #71717a;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="header-title">{{ $tenant?->name ?? 'NUME Creative' }}</h1>
            <p class="header-tag">Sofi IA • Inteligência Audiovisual</p>
        </div>

        <!-- Conteúdo Principal -->
        <div class="content">
            <div class="greeting">
                Olá, {{ $cliente?->name ?? 'Cliente' }}!
            </div>

            <p class="intro-text">
                Nossa equipe e a <strong>Sofi</strong> finalizaram a montagem e pós-produção do seu vídeo. O corte final já está calibrado com o DNA da sua marca e a estrutura de retenção (0-3s) para máxima performance nas redes sociais.
            </p>

            <!-- Card de Detalhes do Vídeo -->
            <div class="video-card">
                <h3 class="video-title">🎬 {{ $video->title ?: $production->title }}</h3>
                <p class="video-production">
                    Produção: <strong>{{ $production->title }}</strong>
                    @if($video->duration_seconds)
                        · Duração estimada: {{ gmdate("i:s", $video->duration_seconds) }}
                    @endif
                </p>
            </div>

            @if($isLocked)
                <!-- Aviso de Custódia & 50/50 -->
                <div class="escrow-alert">
                    <p class="escrow-alert-title">🔒 Prévia de Aprovação Disponível</p>
                    <p class="escrow-alert-desc">
                        Você pode assistir à prévia completa agora mesmo no portal. Ao confirmar a aprovação, o saldo restante de <strong>{{ number_format($balanceAmount, 2, ',', '.') }} {{ $currency }}</strong> será liquidado e os arquivos originais em 4K e o disparo direto para suas redes serão liberados instantaneamente.
                    </p>
                </div>
            @endif

            <!-- Botão de Ação com Link Mágico -->
            <div class="cta-section">
                <a href="{{ $magicUrl }}" class="cta-button">
                    🚀 Assistir Corte & Aprovar Vídeo
                </a>
                <p class="cta-hint">
                    Link seguro de 1 toque. Não é necessário digitar senha.
                </p>
            </div>

            <!-- Vantagens da Aprovação -->
            <ul class="benefits-list">
                <li class="benefit-item">Player dedicado para revisão detalhada em alta qualidade</li>
                <li class="benefit-item">Estratégia completa de SEO (título, legenda e hashtags) gerada pela Sofi</li>
                <li class="benefit-item">Disparo agendado ou imediato para YouTube Shorts, Instagram e TikTok</li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ $tenant?->name ?? 'NUME Creative' }}</strong></p>
            @if($tenant?->social_whatsapp)
                <p>Dúvidas? Entre em contato pelo WhatsApp: {{ $tenant->social_whatsapp }}</p>
            @endif
            <p>Este e-mail foi gerado automaticamente pelo ecossistema NUME App.</p>
        </div>
    </div>
</body>
</html>
