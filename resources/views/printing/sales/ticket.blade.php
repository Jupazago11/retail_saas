<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $sale->document_number }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #1c1917;
            --muted: #57534e;
            --line: #d6d3d1;
            --panel: #fafaf9;
            --brand: #b45309;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #e7e5e4;
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .sheet {
            margin: 24px auto;
            background: #fff;
            box-shadow: 0 16px 40px rgba(28, 25, 23, 0.10);
        }

        {{-- El rollo mide 58mm/80mm pero el cabezal de la impresora
        termica solo imprime dentro de un area mas angosta (48mm/72mm
        segun el driver de Windows) — lo que quede fuera de esa franja
        central simplemente no se imprime, cortando texto cerca del
        borde derecho. Por eso el ancho del contenido es menor al del
        papel; el sobrante queda como margen en blanco (centrado via
        `margin: 0 auto` en @media print) dentro de la zona no
        imprimible. --}}
        .sheet.thermal_58mm {
            width: 48mm;
            max-width: 48mm;
            padding: 12px 4px 16px;
            font-size: 11px;
        }

        .sheet.thermal_58mm .company-name {
            font-size: 16px;
        }

        .sheet.thermal_58mm .totals-row.total {
            font-size: 14px;
        }

        .sheet.thermal_80mm {
            width: 72mm;
            max-width: 72mm;
            padding: 16px 6px 20px;
        }

        .sheet.letter_a4 {
            width: min(100%, 840px);
            padding: 32px 36px 40px;
        }

        .header,
        .meta,
        .totals,
        .footer {
            border-bottom: 1px dashed var(--line);
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .header {
            text-align: center;
        }

        .logo {
            max-width: 140px;
            max-height: 80px;
            margin: 0 auto 10px;
            display: block;
            object-fit: contain;
        }

        .company-name {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .company-line,
        .footer-line {
            margin: 4px 0;
            color: var(--muted);
            font-size: 12px;
        }

        {{-- El espaciado de este bloque lo controla solo el `gap` del
        grid (sin margin propio) para que sea un unico numero facil de
        ajustar, en vez de sumar margin + gap + line-height. --}}
        .meta-line {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .meta-grid {
            display: grid;
            gap: 10px;
        }

        {{-- El scanner laser lee el codigo de barras directo del papel;
        el texto debajo es respaldo por si el scanner falla o alguien
        necesita buscar el numero a mano en el campo "Buscar" de
        Ventas registradas. --}}
        .barcode {
            margin: 14px 0;
            text-align: center;
        }

        .barcode svg {
            display: block;
            width: 100%;
            max-width: 220px;
            height: auto;
            margin: 0 auto;
        }

        .barcode-text {
            margin: 4px 0 0;
            font-size: 11px;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .items th,
        .items td {
            text-align: left;
            padding: 6px 4px;
            vertical-align: top;
            font-size: 12px;
            border-bottom: 1px solid #f5f5f4;
        }

        .items th:first-child,
        .items td:first-child {
            padding-left: 0;
        }

        .items th:last-child,
        .items td:last-child,
        .totals-row span:last-child {
            text-align: right;
            padding-right: 0;
        }

        .items th:nth-child(2),
        .items td:nth-child(2),
        .items th:nth-child(3),
        .items td:nth-child(3),
        .items th:nth-child(4),
        .items td:nth-child(4) {
            text-align: right;
            white-space: nowrap;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            margin: 4px 0;
        }

        .totals-row.total {
            font-size: 16px;
            font-weight: 800;
            color: var(--brand);
        }

        .footer {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
            text-align: center;
        }

        .brand-chip {
            display: inline-block;
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        {{-- El fondo gris y la sombra son solo para la vista previa en
        pantalla: en papel desperdician tinta y pueden confundir al
        recortador de la impresora termica. El tamano de pagina se ajusta
        al formato configurado para que el navegador no intente encajar
        el ticket en una hoja carta/A4. --}}
        @media print {
            body {
                background: #fff;
            }

            .sheet {
                margin: 0 auto;
                box-shadow: none;
            }

            @if (in_array($ticketFormat, ['thermal_58mm', 'thermal_80mm'], true))
                {{-- Las impresoras termicas son de 1 bit (blanco o negro,
                sin escala de grises real): un color gris o café claro como
                --muted/--line/--brand se convierte en un punteado disperso
                de puntos negros ("dithering"), que es justo lo que se ve
                borroso en el papel aunque en pantalla se vea nitido. Se
                fuerza todo a negro solido solo para el formato termico. --}}
                * {
                    color: #000 !important;
                    border-color: #000 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .header,
                .meta,
                .totals,
                .footer {
                    border-bottom-style: solid;
                }

                .brand-chip {
                    background: #fff !important;
                    border: 1px solid #000;
                }
            @endif
        }

        @page {
            size: {{ match ($ticketFormat) {
                'thermal_58mm' => '58mm auto',
                'thermal_80mm' => '80mm auto',
                default => 'auto',
            } }};
            margin: {{ in_array($ticketFormat, ['thermal_58mm', 'thermal_80mm'], true) ? '0' : '12mm' }};
        }
    </style>
</head>
<body>
    <main class="sheet {{ $ticketFormat }}">
        <section class="header">
            @if ($showLogo && $logoPath)
                <img src="{{ $logoPath }}" alt="Logo de {{ $company->display_name }}" class="logo">
            @endif

            <h1 class="company-name">{{ $company->display_name }}</h1>
            <p class="company-line">{{ $company->legal_name }}</p>

            @if ($company->tax_id)
                <p class="company-line">NIT: {{ $company->tax_id }}</p>
            @endif

            @if ($companyPhone)
                <p class="company-line">Tel: {{ $companyPhone }}</p>
            @endif

            @if ($companyAddress)
                <p class="company-line">{{ $companyAddress }}</p>
            @endif
        </section>

        <section class="meta">
            <div class="meta-grid">
                <p class="meta-line">{{ $sale->document_number }} | {{ $sale->sale_type === 'credit' ? 'CREDITO' : strtoupper($sale->sale_type) }}</p>
                <p class="meta-line">Interno: Venta #{{ $sale->id }}</p>
                <p class="meta-line">Estado: {{ match ($sale->status) {
                    'draft' => 'Borrador',
                    'confirmed' => 'Confirmada',
                    'partially_returned' => 'Parcialmente devuelta',
                    'returned' => 'Devuelta',
                    'cancelled' => 'Anulada',
                    default => $sale->status,
                } }}</p>
                <p class="meta-line">Fecha: {{ optional($sale->sold_at)->format('Y-m-d H:i') ?? $sale->created_at->format('Y-m-d H:i') }}</p>
                @if ($sale->branch)
                    <p class="meta-line">Sucursal: {{ $sale->branch->name }}</p>
                @endif
                @if ($sale->cashRegister)
                    <p class="meta-line">Caja: {{ $sale->cashRegister->name }}</p>
                @endif
                @if ($sale->user)
                    <p class="meta-line">Vendedor: {{ $sale->user->name }}</p>
                @endif
                @if ($sale->customer?->person)
                    <p class="meta-line">Cliente: {{ trim($sale->customer->person->first_name.' '.$sale->customer->person->last_name) }}</p>
                @endif
            </div>
        </section>

        @if ($barcodeSvg)
            <section class="barcode">
                {!! $barcodeSvg !!}
                <p class="barcode-text">{{ $sale->document_number }}</p>
            </section>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Cant.</th>
                    <th>Precio</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ $item['unit_price'] }}</td>
                        <td>{{ $item['line_total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>{{ \App\Support\Money::format((float) $sale->subtotal) }}</span>
            </div>
            <div class="totals-row">
                <span>Descuentos</span>
                <span>{{ \App\Support\Money::format((float) $sale->discount_total) }}</span>
            </div>
            <div class="totals-row">
                <span>Impuestos</span>
                <span>{{ \App\Support\Money::format((float) $sale->tax_total) }}</span>
            </div>
            <div class="totals-row total">
                <span>Total</span>
                <span>{{ \App\Support\Money::format((float) $sale->grand_total) }}</span>
            </div>
        </section>

        <section class="footer">
            <p class="footer-line">Gracias por su compra.</p>

            @if ($showSaasBranding)
                <span class="brand-chip">Powered by {{ \App\Models\PlatformSetting::appName() }}</span>
            @endif
        </section>
    </main>
</body>
</html>
