<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $sale->document_number }}</title>
    @include('printing.partials.ticket-styles')
</head>
<body>
    <div class="page-layout">
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
                <p class="meta-line is-strong">Fecha: {{ optional($sale->sold_at)->format('d/m/Y h:i a') ?? $sale->created_at->format('d/m/Y h:i a') }}</p>
                @if ($sale->branch)
                    <p class="meta-line">Sucursal: {{ $sale->branch->name }}</p>
                @endif
                @if ($sale->cashRegister)
                    <p class="meta-line">Caja: {{ $sale->cashRegister->name }}</p>
                @endif
                @if ($sale->user)
                    <p class="meta-line is-strong">Vendedor: {{ $sale->user->name }}</p>
                @endif
                @if ($sale->customer?->person)
                    <p class="meta-line is-strong">Cliente: {{ trim($sale->customer->person->first_name.' '.$sale->customer->person->last_name) }}</p>
                @endif
            </div>
        </section>

        @if ($barcodeSvg)
            <section class="barcode">
                {!! $barcodeSvg !!}
                <p class="barcode-text">{{ $sale->document_number }}</p>
            </section>
        @endif

        {{-- <colgroup> explicito porque table-layout:fixed deberia bastar
        con los % puestos en th/td, pero el motor de impresion (print
        preview / driver de la impresora) no siempre respeta esos anchos
        igual que la pantalla normal — con <col> el ancho de columna queda
        fijado antes de que se dibuje cualquier celda, sin ambiguedad. --}}
        <table class="items">
            @if ($ticketFormat === 'thermal_58mm')
                <colgroup>
                    <col style="width: 42%;">
                    <col style="width: 14%;">
                    <col style="width: 18%;">
                    <col style="width: 26%;">
                </colgroup>
            @else
                <colgroup>
                    <col style="width: 42%;">
                    <col style="width: 13%;">
                    <col style="width: 21%;">
                    <col style="width: 24%;">
                </colgroup>
            @endif
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
            {{-- Subtotal = Total menos el IVA ya incluido en cada producto
            (ver Impuestos abajo), para que Subtotal + Impuestos = Total
            cuadre visualmente. NO es $sale->subtotal (esa es la base ANTES
            del tax_rate aditivo de la linea, que en ventas POS siempre es 0,
            asi que sin este ajuste quedaba igual al Total). --}}
            <div class="totals-row">
                <span>Subtotal</span>
                <span>{{ \App\Support\Money::format((float) \App\Support\SaleTaxCalculator::taxExcludedSubtotal($sale)) }}</span>
            </div>
            {{-- Impuestos = suma del IVA de cada producto (precio del item x
            el IVA con el que se creo ese producto), ya incluido en el total
            — no $sale->tax_total, que es un impuesto ADITIVO sin uso real en
            ventas POS y siempre queda en 0. Antes esto mismo se repetia
            aparte como "IVA incluido"; con Impuestos mostrando el valor
            correcto, esa segunda linea sobraba. --}}
            <div class="totals-row">
                <span>Impuestos</span>
                <span>{{ $taxIncludedTotal }}</span>
            </div>
            <div class="totals-row total">
                <span>Total</span>
                <span>{{ \App\Support\Money::format((float) $sale->grand_total) }}</span>
            </div>
        </section>

        @if ($receiptQrSvg)
            <section class="receipt-qr">
                {!! $receiptQrSvg !!}
                <p class="receipt-qr-text">Escanea para ver tu compra</p>
            </section>
        @endif

        {{-- La marca del SaaS no es un ajuste de empresa: siempre va, no es
        algo que la empresa pueda quitar desde Reglas. --}}
        <section class="footer">
            <span class="brand-chip">Desarrollado por {{ \App\Models\PlatformSetting::appName() }}</span>
        </section>
    </main>

    <div class="screen-actions">
        <button type="button" onclick="window.print()" title="Imprimir (Ctrl+P)" aria-label="Imprimir">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:28px;height:28px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
            </svg>
        </button>
        <button type="button" class="close-button" onclick="window.close()" title="Cerrar pestaña" aria-label="Cerrar pestaña">
            <svg xmlns="http://www.w3.org/2000/svg" style="width:28px;height:28px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    </div>
</body>
</html>
