<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto - {{ $tenant?->name ?? 'NUME' }}</title>
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
            padding: 28px 24px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            font-size: 20px;
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
            padding: 28px 24px;
        }
        .custom-body {
            font-size: 14px;
            line-height: 1.6;
            color: #27272a;
            white-space: pre-line;
            margin-bottom: 24px;
        }
        .summary-card {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .summary-table td {
            padding: 6px 0;
        }
        .label {
            color: #71717a;
        }
        .value {
            text-align: right;
            font-weight: 600;
            color: #18181b;
        }
        .value-mono {
            text-align: right;
            font-family: monospace;
            font-weight: 700;
        }
        .total-row {
            border-top: 2px solid #e4e4e7;
        }
        .total-row td {
            padding-top: 10px;
            font-weight: 800;
            font-size: 15px;
        }
        .total-val {
            text-align: right;
            color: #b72c13;
            font-family: monospace;
            font-size: 16px;
        }
        .attachment-box {
            background-color: #fff7ed;
            border: 1px dashed #fdba74;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            color: #9a3412;
            text-align: center;
            margin-bottom: 20px;
        }
        .footer {
            background-color: #fafafa;
            border-top: 1px solid #e4e4e7;
            padding: 16px 24px;
            text-align: center;
            font-size: 11px;
            color: #a1a1aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $tenant?->name ?? 'NUME CREATIVE' }}</h1>
            <p>Propuesta y Presupuesto Audiovisual</p>
        </div>

        <div class="content">
            @if(!empty($customBody))
                <div class="custom-body">{{ $customBody }}</div>
            @else
                <div class="custom-body">
Estimado/a {{ $quote->client_name }}:

Gracias por contar con {{ $tenant?->name ?? 'NUME' }}. Le enviamos adjunto el presupuesto detallado para {{ $quote->project_title ?: 'su proyecto audiovisual' }}.

Quedamos a su total disposición para resolver cualquier duda o ajustar las necesidades de la grabación.

Un cordial saludo,
{{ $tenant?->name ?? 'NUME Creative Studio' }}
                </div>
            @endif

            <div class="attachment-box">
                📄 <strong>Presupuesto oficial en PDF adjunto:</strong> <code>Presupuesto_{{ $quote->number }}.pdf</code>
            </div>

            <div class="summary-card">
                <table class="summary-table">
                    <tr>
                        <td class="label">Nº Presupuesto:</td>
                        <td class="value-mono">{{ $quote->number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Proyecto:</td>
                        <td class="value">{{ $quote->project_title ?: 'Producción audiovisual' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Vídeos:</td>
                        <td class="value">{{ $inputs['videos'] ?? 1 }} vídeos</td>
                    </tr>
                    <tr>
                        <td class="label">Plazo de entrega:</td>
                        <td class="value">Hasta {{ $inputs['prazo'] ?? 15 }} días tras la aprobación</td>
                    </tr>
                    <tr class="total-row">
                        <td>Importe Total:</td>
                        <td class="total-val">
                            € {{ number_format($quote->subtotal, 2, ',', '.') }} + {{ number_format($quote->tax_rate, 0) }}% IVA
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Forma de pago:</td>
                        <td class="value">50% a la aprobación · 50% a la entrega</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="footer">
            <p>{{ $tenant?->name ?? 'NUME Creative Studio' }} · Producción Audiovisual Estratégica</p>
            <p>Zaragoza (España)</p>
        </div>
    </div>
</body>
</html>
