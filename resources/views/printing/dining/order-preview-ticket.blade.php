<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuenta mesa {{ $table->name }}</title>
    @include('printing.partials.ticket-styles')
</head>
<body>
    <div class="page-layout">
    <main class="sheet {{ $ticketFormat }}">
        <p class="unpaid-banner">Cuenta sin pagar</p>

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
                <p class="meta-line is-strong">Mesa: {{ $table->name }}</p>
                <p class="meta-line">Fecha: {{ $generatedAt->format('d/m/Y h:i a') }}</p>
                @if ($branchName)
                    <p class="meta-line">Sucursal: {{ $branchName }}</p>
                @endif
                @if ($servedBy)
                    <p class="meta-line is-strong">Atendido por: {{ $servedBy }}</p>
                @endif
            </div>
        </section>

        <table class="items">
            <colgroup>
                <col style="width: 42%;">
                <col style="width: 13%;">
                <col style="width: 21%;">
                <col style="width: 24%;">
            </colgroup>
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
                <span>{{ $subtotal }}</span>
            </div>
            <div class="totals-row">
                <span>Impuestos</span>
                <span>{{ $taxTotal }}</span>
            </div>
            <div class="totals-row total">
                <span>Total</span>
                <span>{{ $grandTotal }}</span>
            </div>
        </section>

        <p class="unpaid-banner">Esta cuenta aun no ha sido pagada</p>

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
