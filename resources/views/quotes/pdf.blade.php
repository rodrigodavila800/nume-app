<!DOCTYPE html>
<html lang="{{ $language ?? 'es' }}">
<head>
    <meta charset="utf-8">
    <title>Presupuesto {{ $quote->number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1d1d1b;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 30px;
        }
        .header {
            width: 100%;
            margin-bottom: 24px;
            border-bottom: 2px solid #b72c13;
            padding-bottom: 16px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-box {
            vertical-align: top;
        }
        .logo-box img {
            max-width: 160px;
            height: auto;
        }
        .logo-box .brand-fallback {
            font-size: 24px;
            font-weight: 800;
            color: #b72c13;
            letter-spacing: -0.5px;
        }
        .meta-box {
            text-align: right;
            vertical-align: top;
            font-size: 11px;
            color: #555555;
            line-height: 1.4;
        }
        .meta-box b {
            display: block;
            font-size: 16px;
            font-weight: 800;
            color: #b72c13;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #b72c13;
            margin: 18px 0 6px 0;
        }
        .client-box {
            background-color: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .client-name {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }
        .client-meta {
            font-size: 11px;
            color: #4b5563;
            margin-top: 2px;
        }
        .deliverables-list {
            margin: 4px 0 14px 0;
            padding-left: 20px;
        }
        .deliverables-list li {
            margin-bottom: 4px;
            font-size: 12px;
            color: #1f2937;
        }
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px 0;
        }
        .pricing-table td {
            padding: 8px 0;
            font-size: 13px;
        }
        .pricing-table .total-label {
            font-weight: 800;
            border-top: 2px solid #1d1d1b;
            color: #111827;
            font-size: 14px;
        }
        .pricing-table .total-val {
            text-align: right;
            font-weight: 800;
            border-top: 2px solid #1d1d1b;
            color: #b72c13;
            font-family: monospace;
            font-size: 16px;
        }
        .info-card {
            margin-bottom: 12px;
            font-size: 12px;
            color: #374151;
        }
        .validity-note {
            margin-top: 20px;
            font-size: 11px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 30px;
            right: 30px;
            font-size: 9px;
            color: #9ca3af;
            text-align: left;
            border-top: 1px solid #f3f4f6;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td class="logo-box">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="NUME">
                    @else
                        <div class="brand-fallback">{{ $tenant?->name ?? 'NUME' }}</div>
                    @endif
                </td>
                <td class="meta-box">
                    <b>PRESUPUESTO</b>
                    {{ $quote->number }}<br>
                    Fecha: {{ $issuedDate }}<br>
                    Válido hasta: {{ $validUntilDate }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Cliente</div>
    <div class="client-box">
        <div class="client-name">{{ $quote->client_name ?: 'Nombre del cliente' }}</div>
        @if(!empty($quote->client_contact))
            <div class="client-meta">Contacto: {{ $quote->client_contact }}</div>
        @endif
        @if(!empty($quote->client_email))
            <div class="client-meta">E-mail: {{ $quote->client_email }}</div>
        @endif
        @if(!empty($quote->project_title))
            <div class="client-meta">Proyecto: {{ $quote->project_title }}</div>
        @endif
    </div>

    <div class="section-title">Qué vamos a entregar</div>
    <ul class="deliverables-list">
        @foreach($deliverables as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>

    <div class="section-title">Importe</div>
    <table class="pricing-table">
        <tr>
            <td class="total-label">Total</td>
            <td class="total-val">
                € {{ number_format($quote->subtotal, 2, ',', '.') }} + {{ number_format($quote->tax_rate, 0) }}% IVA
            </td>
        </tr>
    </table>

    <div class="section-title">Plazo de entrega</div>
    <div class="info-card">
        Hasta {{ $inputs['prazo'] ?? 15 }} días tras la aprobación.
    </div>

    <div class="section-title">Forma de pago</div>
    <div class="info-card">
        50% a la aprobación, para reservar la fecha.<br>
        50% a la entrega.
    </div>

    <div class="validity-note">
        Presupuesto válido durante {{ $inputs['prazo'] ?? 10 }} días, hasta el {{ $validUntilDate }}.
    </div>

    <div class="footer">
        {{ $tenant?->name ?? 'Nume' }} · producción audiovisual estratégica · Zaragoza (España)
    </div>
</body>
</html>
