<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $invoiceNumber }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #27272a;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 30px;
        }
        .header {
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 2px solid #f97316;
            padding-bottom: 15px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-text {
            font-size: 26px;
            font-weight: 900;
            color: #18181b;
            letter-spacing: -1px;
        }
        .logo-sub {
            color: #ea580c;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            margin: 0;
            font-size: 24px;
            color: #18181b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .invoice-title .meta {
            font-size: 11px;
            color: #71717a;
            margin-top: 4px;
        }
        .parties {
            width: 100%;
            margin-bottom: 25px;
        }
        .parties table {
            width: 100%;
            border-collapse: collapse;
        }
        .party-box {
            width: 48%;
            vertical-align: top;
            background: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            padding: 12px 15px;
        }
        .party-box h3 {
            margin: 0 0 8px 0;
            font-size: 11px;
            text-transform: uppercase;
            color: #ea580c;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .party-box p {
            margin: 2px 0;
            font-size: 11px;
            color: #3f3f46;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .items-table th {
            background: #18181b;
            color: #ffffff;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 10px;
            text-align: left;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e4e4e7;
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-section {
            width: 100%;
            margin-bottom: 30px;
        }
        .totals-section table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-section td {
            padding: 6px 10px;
            font-size: 11px;
        }
        .totals-section .total-row td {
            border-top: 2px solid #18181b;
            border-bottom: 2px solid #18181b;
            font-size: 14px;
            font-weight: bold;
            color: #18181b;
            padding: 10px;
        }
        .payment-status {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        .payment-status .status-badge {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e4e4e7;
            font-size: 9px;
            color: #a1a1aa;
            text-align: center;
            line-height: 1.5;
        }
        .w-50 { width: 50%; }
        .w-4 { width: 4%; }
        .w-55 { width: 55%; }
        .w-15 { width: 15%; }
        .w-total-col { width: 120px; font-weight: bold; }
        .item-subtext { font-size: 10px; color: #71717a; margin-top: 2px; }
        .text-muted { color: #71717a; }
        .text-irpf { color: #ea580c; font-weight: bold; }
        .table-full { width: 100%; border-collapse: collapse; }
        .valign-middle { vertical-align: middle; }
        .payment-info { margin-left: 8px; font-weight: bold; color: #166534; font-size: 11px; }
        .payment-ref { font-size: 10px; color: #15803d; margin-top: 3px; }
        .text-success-sm { font-size: 10px; color: #166534; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <table>
            <tr>
                <td class="w-50">
                    <div class="logo-text">NUME</div>
                    <div class="logo-sub">{{ $companyName }}</div>
                </td>
                <td class="invoice-title">
                    <h1>FACTURA</h1>
                    <div class="meta"><strong>Nº Factura:</strong> {{ $invoiceNumber }}</div>
                    <div class="meta"><strong>Fecha de emisión:</strong> {{ $issuedDate }}</div>
                    <div class="meta"><strong>Fecha de operación:</strong> {{ $operationDate }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Parties (Emisor y Cliente) -->
    <div class="parties">
        <table>
            <tr>
                <td class="party-box">
                    <h3>Emisor</h3>
                    <p><strong>{{ $companyName }}</strong></p>
                    <p><strong>NIF / CIF:</strong> {{ $companyCif }}</p>
                    <p>{{ $companyAddress }}</p>
                    <p>{{ $companyPostalCode }} {{ $companyCity }} (España)</p>
                    <p>{{ $companyEmail }}</p>
                </td>
                <td class="w-4"></td>
                <td class="party-box">
                    <h3>Cliente / Receptor</h3>
                    <p><strong>{{ $clientLegalName }}</strong></p>
                    @if(!empty($clientTaxId))
                        <p><strong>NIF / CIF / DNI:</strong> {{ $clientTaxId }}</p>
                    @endif
                    @if(!empty($clientAddress))
                        <p>{{ $clientAddress }}</p>
                    @endif
                    <p>{{ $clientPostalCode }} {{ $clientCity }} ({{ $clientCountry }})</p>
                    <p>{{ $clientEmail }}</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Tabla de Conceptos -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="w-55">Descripción del Servicio</th>
                <th class="text-center w-15">Cantidad</th>
                <th class="text-right w-15">Precio Unit.</th>
                <th class="text-right w-15">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $pack?->name ?? ($purchase->quote?->project_title ?? 'Serviço de Produção Audiovisual') }}</strong>
                    <div class="item-subtext">
                        @if($purchase->quote?->isAds())
                            Creación de creatividades publicitarias y gestión de campaña. La inversión publicitaria la paga el cliente directamente a la plataforma y no forma parte de esta factura.
                        @else
                            Cota de {{ $pack?->video_quantity ?? ($purchase->video_quantity ?? 1) }} vídeos de alto impacto con roteirização e edição estratégica.
                        @endif
                    </div>
                </td>
                <td class="text-center">1</td>
                <td class="text-right">€ {{ number_format($packBasePrice, 2, ',', '.') }}</td>
                <td class="text-right">€ {{ number_format($packBasePrice, 2, ',', '.') }}</td>
            </tr>
            @if(!empty($addonsList))
                @foreach($addonsList as $addon)
                    <tr>
                        <td>
                            <strong>+ Add-on: {{ $addon['name'] }}</strong>
                            <div class="item-subtext">
                                Opcional contratado para o pacote.
                            </div>
                        </td>
                        <td class="text-center">1</td>
                        <td class="text-right">€ {{ number_format($addon['price'], 2, ',', '.') }}</td>
                        <td class="text-right">€ {{ number_format($addon['price'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- Resumen Fiscal / Totales -->
    <div class="totals-section">
        <table>
            <tr>
                <td class="text-right text-muted">Base Imponible:</td>
                <td class="text-right w-total-col">€ {{ number_format($subtotal, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="text-right text-muted">
                    {{ $taxLabel }} ({{ number_format($taxRate, 2, ',', '.') }}%):
                </td>
                <td class="text-right font-bold">€ {{ number_format($taxAmount, 2, ',', '.') }}</td>
            </tr>
            @if($irpfAmount > 0)
                <tr>
                    <td class="text-right text-irpf">Retención IRPF (-{{ number_format($irpfRate, 2, ',', '.') }}%):</td>
                    <td class="text-right text-irpf">- € {{ number_format($irpfAmount, 2, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="text-right">TOTAL:</td>
                <td class="text-right">€ {{ number_format($totalAmount, 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <!-- Estado del Pago y Pasarela -->
    <div class="payment-status">
        <table class="table-full">
            <tr>
                <td class="valign-middle">
                    <span class="status-badge">PAGADA</span>
                    <span class="payment-info">
                        Cobro liquidado vía {{ $paymentMethodName }}
                    </span>
                    @if(!empty($gatewayReference))
                        <div class="payment-ref">
                            Referencia de transacción bancaria: <code>{{ $gatewayReference }}</code>
                        </div>
                    @endif
                </td>
                <td class="text-right valign-middle text-success-sm">
                    Operación confirmada electrónicamente
                </td>
            </tr>
        </table>
    </div>

    <!-- Pie Legal Agencia Tributaria -->
    <div class="footer">
        <p>Factura emitida de conformidad con el Real Decreto 1619/2012, de 30 de noviembre, por el que se aprueba el Reglamento por el que se regulan las obligaciones de facturación en España.</p>
        <p>{{ $companyName }} · NIF {{ $companyCif }} · {{ $companyCity }} (España)</p>
    </div>

</body>
</html>
