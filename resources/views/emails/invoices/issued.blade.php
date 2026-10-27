<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura Oficial - {{ $tenant?->name ?? 'NUME' }}</title>
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
        .header h1 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .header p {
            color: #ea580c;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 4px 0 0 0;
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
        .invoice-card {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .invoice-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .invoice-row.total {
            border-top: 2px solid #e4e4e7;
            padding-top: 10px;
            margin-top: 10px;
            margin-bottom: 0;
            font-size: 15px;
            font-weight: 800;
            color: #18181b;
        }
        .badge {
            display: inline-block;
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .cta-container {
            text-align: center;
            margin: 32px 0 20px 0;
        }
        .cta-button {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 28px;
            border-radius: 10px;
        }
        .attachment-note {
            background-color: #eff6ff;
            border: 1px dashed #bfdbfe;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            color: #1e40af;
            text-align: center;
            margin-bottom: 24px;
        }
        .footer {
            background-color: #fafafa;
            border-top: 1px solid #e4e4e7;
            padding: 20px 30px;
            text-align: center;
            font-size: 11px;
            color: #a1a1aa;
            line-height: 1.5;
        }
        .invoice-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .invoice-td-label { color: #71717a; padding: 4px 0; }
        .invoice-td-val-mono { text-align: right; font-weight: bold; font-family: monospace; }
        .invoice-td-val-semibold { text-align: right; font-weight: 600; }
        .invoice-td-val-tax { text-align: right; font-family: monospace; color: #166534; }
        .invoice-tr-total { border-top: 1px solid #e4e4e7; }
        .invoice-td-total-label { font-weight: bold; padding: 8px 0 0 0; font-size: 14px; }
        .invoice-td-total-val { text-align: right; font-weight: 800; font-size: 15px; padding: 8px 0 0 0; font-family: monospace; color: #ea580c; }
        .intro-text-sm { font-size: 13px; line-height: 1.6; color: #3f3f46; margin-bottom: 24px; }
    </style>
</head>
<body>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>NUME CREATIVE</h1>
            <p>{{ $tenant?->name ?? 'Producción Audiovisual Estratégica' }}</p>
        </div>

        <!-- Body -->
        <div class="content">
            <div class="greeting">
                ¡Hola, {{ $cliente?->name ?? 'Cliente' }}!
            </div>

            <p class="intro-text">
                Confirmamos con éxito la activación de su paquete <strong>{{ $pack?->name ?? 'de vídeos' }}</strong>.
                Adjunto a este correo encontrará su <strong>Factura Oficial</strong> en formato PDF, emitida en cumplimiento del Real Decreto 1619/2012 de la Agencia Tributaria.
            </p>

            <!-- Nota de Anexo -->
            <div class="attachment-note">
                <strong>Factura Oficial Adjunta:</strong> <code>Factura-{{ $invoiceNumber }}.pdf</code>
            </div>

            <!-- Resumen de Facturación -->
            <div class="invoice-card">
                <table class="invoice-table">
                    <tr>
                        <td class="invoice-td-label">Nº Factura:</td>
                        <td class="invoice-td-val-mono">{{ $invoiceNumber }}</td>
                    </tr>
                    <tr>
                        <td class="invoice-td-label">Paquete:</td>
                        <td class="invoice-td-val-semibold">{{ $pack?->name }} ({{ $pack?->video_quantity }} vídeos)</td>
                    </tr>
                    <tr>
                        <td class="invoice-td-label">Base Imponible:</td>
                        <td class="invoice-td-val-mono">€ {{ number_format($subtotal, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="invoice-td-label">IVA (21%):</td>
                        <td class="invoice-td-val-tax">€ {{ number_format($taxAmount, 2, ',', '.') }}</td>
                    </tr>
                    <tr class="invoice-tr-total">
                        <td class="invoice-td-total-label">Total Pagado:</td>
                        <td class="invoice-td-total-val">
                            € {{ number_format($totalAmount, 2, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </div>

            <p class="intro-text-sm">
                Nuestro equipo de producción y el motor de inteligencia artificial de Sofi ya están preparados para calibrar sus guiones y dar inicio a la esteira de contenido.
            </p>

            <!-- CTA -->
            <div class="cta-container">
                <a href="{{ route('portal.dashboard') }}" class="cta-button">
                    Acceder al Portal del Cliente →
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Factura emitida de conformidad con el Real Decreto 1619/2012 (España).</p>
            <p>{{ $tenant?->name ?? 'NUME Creative Studio' }} · Zaragoza (España)</p>
        </div>
    </div>

</body>
</html>
