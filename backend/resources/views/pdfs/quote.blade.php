<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización #{{ $quote->id }} - FidelOS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .header {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #2b87da;
            padding-bottom: 10px;
        }
        .header table {
            width: 100%;
        }
        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #2b87da;
        }
        .company-info {
            text-align: right;
            font-size: 11px;
            color: #666666;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #1e293b;
        }
        .meta-box {
            width: 100%;
            margin-bottom: 20px;
        }
        .meta-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-box td {
            padding: 6px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #2b87da;
            color: #ffffff;
            font-weight: bold;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .totals-table {
            width: 40%;
            float: right;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px;
            text-align: right;
        }
        .totals-table td.total-label {
            font-weight: bold;
        }
        .totals-table td.total-amount {
            font-weight: bold;
            font-size: 14px;
            color: #2b87da;
        }
        .clear {
            clear: both;
        }
        .footer {
            margin-top: 40px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
            font-size: 10px;
            text-align: center;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="logo">{{ $company->name }}</div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Vertical: <strong>{{ strtoupper($quote->vertical ?? 'GENERAL') }}</strong></div>
                </td>
                <td class="company-info">
                    <strong>NIT:</strong> {{ $company->nit ?? 'N/A' }}<br>
                    <strong>Dirección:</strong> {{ $company->address ?? 'N/A' }}<br>
                    <strong>Teléfono:</strong> {{ $company->phone ?? 'N/A' }}<br>
                    <strong>Email:</strong> {{ $company->email ?? 'N/A' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="title">COTIZACIÓN COMERCIAL #{{ sprintf('%05d', $quote->id) }}</div>

    <div class="meta-box">
        <table>
            <tr>
                <td width="50%">
                    <strong>CLIENTE / SOLICITANTE:</strong><br>
                    {{ $quote->client->name ?? $quote->lead->name ?? 'Cliente Web' }}<br>
                    Email: {{ $quote->client->email ?? $quote->lead->email ?? 'N/A' }}<br>
                    Teléfono: {{ $quote->client->phone ?? $quote->lead->phone ?? 'N/A' }}
                </td>
                <td width="50%">
                    <strong>DETALLES DE LA COTIZACIÓN:</strong><br>
                    Fecha de Emisión: {{ $quote->created_at ? $quote->created_at->format('d/m/Y') : date('d/m/Y') }}<br>
                    Vigencia: {{ $quote->valid_until ? $quote->valid_until->format('d/m/Y') : '30 días' }}<br>
                    Estado: <strong>{{ strtoupper($quote->status) }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Ítem / Descripción</th>
                <th>Tipo</th>
                <th style="text-align: center;">Cant.</th>
                <th style="text-align: right;">Precio Unit.</th>
                <th style="text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $item)
            <tr>
                <td>
                    <strong>{{ $item->product_name ?? $item->description }}</strong>
                    @if($item->sku) <br><small style="color:#64748b;">SKU: {{ $item->sku }}</small> @endif
                </td>
                <td>{{ strtoupper($item->item_type ?? 'PRODUCTO') }}</td>
                <td style="text-align: center;">{{ $item->quantity }}</td>
                <td style="text-align: right;">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td style="text-align: right;">${{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td class="total-label">Subtotal:</td>
            <td>${{ number_format($quote->subtotal > 0 ? $quote->subtotal : $quote->total, 0, ',', '.') }}</td>
        </tr>
        @if($quote->discount_amount > 0)
        <tr>
            <td class="total-label">Descuento:</td>
            <td>-${{ number_format($quote->discount_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if($quote->tax_amount > 0)
        <tr>
            <td class="total-label">Impuestos (IVA):</td>
            <td>+${{ number_format($quote->tax_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr>
            <td class="total-label" style="font-size: 14px;">TOTAL:</td>
            <td class="total-amount">${{ number_format($quote->total, 0, ',', '.') }} COP</td>
        </tr>
    </table>

    <div class="clear"></div>

    @if($quote->notes)
    <div style="margin-top: 25px; padding: 10px; background-color: #f1f5f9; border-radius: 4px; font-size: 11px;">
        <strong>Observaciones:</strong><br>
        {{ $quote->notes }}
    </div>
    @endif

    <div class="footer">
        Esta cotización es generada electrónicamente por el sistema transversal FidelOS ERP.<br>
        Gracias por confiar en {{ $company->name }}.
    </div>
</body>
</html>
