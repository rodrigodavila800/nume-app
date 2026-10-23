<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte Aprovado e Saldo Quitado - {{ $tenant?->name ?? 'NUME' }}</title>
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
            color: #10b981;
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
        .success-box {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .success-box-title {
            font-size: 13px;
            font-weight: 800;
            color: #065f46;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed #d1fae5;
            font-size: 13px;
        }
        .metric-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .metric-label {
            color: #047857;
            font-weight: 600;
        }
        .metric-value {
            color: #064e3b;
            font-weight: 800;
        }
        .details-card {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .details-title {
            font-size: 14px;
            font-weight: 700;
            color: #18181b;
            margin: 0 0 10px 0;
        }
        .details-item {
            font-size: 13px;
            color: #52525b;
            margin: 4px 0;
        }
        .cta-section {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #18181b 0%, #27272a 100%);
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(24, 24, 27, 0.2);
        }
        .cta-hint {
            font-size: 11px;
            color: #a1a1aa;
            margin-top: 10px;
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
            <p class="header-tag">✨ Entrega Audiovisual Concluída com Sucesso</p>
        </div>

        <!-- Conteúdo Principal -->
        <div class="content">
            <div class="greeting">
                Olá, equipe {{ $tenant?->name ?? 'NUME' }}!
            </div>

            <p class="intro-text">
                O cliente <strong>{{ $cliente?->name ?? 'Cliente' }}</strong> ({{ $cliente?->company_name ?: $cliente?->email }}) acabou de aprovar formalmente o corte final do vídeo no portal.
            </p>

            <!-- Box de Métricas e Liquidação Financeira -->
            <div class="success-box">
                <div class="success-box-title">💰 Status Financeiro & Liquidação</div>
                <div class="metric-row">
                    <span class="metric-label">Vídeo Aprovado:</span>
                    <span class="metric-value">{{ $video->title ?: $production->title }}</span>
                </div>
                @if($isSplit5050 && $balanceAmount > 0)
                    <div class="metric-row">
                        <span class="metric-label">Saldo Restante (50%):</span>
                        <span class="metric-value">{{ number_format($balanceAmount, 2, ',', '.') }} {{ $currency }} (QUITADO)</span>
                    </div>
                @endif
                <div class="metric-row">
                    <span class="metric-label">Ativo em Custódia:</span>
                    <span class="metric-value">LIBERADO (Sem marca d'água)</span>
                </div>
                <div class="metric-row">
                    <span class="metric-label">Fatura Fiscal:</span>
                    <span class="metric-value">EMITIDA & ENVIADA AO CLIENTE</span>
                </div>
            </div>

            <!-- Detalhes da Produção -->
            <div class="details-card">
                <div class="details-title">📋 Informações da Demanda</div>
                <p class="details-item"><strong>Produção:</strong> {{ $production->title }}</p>
                <p class="details-item"><strong>Cliente:</strong> {{ $cliente?->name }} ({{ $cliente?->email }})</p>
                @if($cliente?->phone)
                    <p class="details-item"><strong>WhatsApp do Cliente:</strong> {{ $cliente->phone }}</p>
                @endif
                @if($video->duration_seconds)
                    <p class="details-item"><strong>Duração do Corte:</strong> {{ gmdate("i:s", $video->duration_seconds) }}</p>
                @endif
            </div>

            <!-- Botão de Ação -->
            <div class="cta-section">
                <a href="{{ $productionUrl }}" class="cta-button">
                    🚀 Abrir Produção no Painel da Produtora
                </a>
                <p class="cta-hint">
                    Acompanhe o disparo das redes sociais ou faça o download dos arquivos master.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ $tenant?->name ?? 'NUME Creative' }}</strong> • Notificação Interna de Equipe</p>
            <p>Este alerta é disparado automaticamente quando o cliente aprova o corte final e liquida o saldo.</p>
        </div>
    </div>
</body>
</html>
