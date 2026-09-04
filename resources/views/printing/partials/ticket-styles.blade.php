{{-- Estilos compartidos por toda vista imprimible de tipo "ticket" (venta
pagada y cuenta sin pagar de una mesa): mismo papel, mismo layout, mismos 3
formatos (thermal_58mm/thermal_80mm/letter_a4). Requiere $ticketFormat en el
scope de quien lo incluye. --}}
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

    {{-- .page-layout reemplaza el viejo `margin:24px auto` de .sheet:
    pone el ticket y los botones de pantalla como hermanos en fila,
    centrados juntos como grupo, para que los botones queden pegados al
    ticket en vez de anclados a una esquina fija del navegador (que
    quedaba lejos si la ventana era ancha). --}}
    .page-layout {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        gap: 14px;
        padding: 24px 16px;
    }

    .sheet {
        background: #fff;
        box-shadow: 0 16px 40px rgba(28, 25, 23, 0.10);
    }

    {{-- Botones de pantalla (imprimir/cerrar): al lado del ticket, no
    cuentan como contenido del ticket. Se ocultan explicitamente en
    @media print mas abajo. --}}
    .screen-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .screen-actions button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 999px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--ink);
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(28, 25, 23, 0.12);
        transition: border-color 0.15s, color 0.15s;
    }

    .screen-actions button:hover {
        border-color: var(--brand);
        color: var(--brand);
    }

    .screen-actions .close-button {
        color: #dc2626;
        border-color: #fca5a5;
    }

    .screen-actions .close-button:hover {
        color: #b91c1c;
        border-color: #dc2626;
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
        padding: 10px 4px 12px;
        font-size: 11px;
    }

    .sheet.thermal_58mm .company-name {
        font-size: 15px;
    }

    .sheet.thermal_58mm .totals-row.total {
        font-size: 13px;
    }

    {{-- Compactar todo lo de texto corrido (no la tabla, que ya tiene su
    propio font-size mas abajo): en 48mm cada linea de mas es papel
    desperdiciado, y el 12px fijo que comparten con 80mm no hacia falta
    aca. --}}
    .sheet.thermal_58mm .company-line,
    .sheet.thermal_58mm .footer-line,
    .sheet.thermal_58mm .meta-line {
        font-size: 10px;
    }

    .sheet.thermal_58mm .meta-grid {
        gap: 6px;
    }

    .sheet.thermal_58mm .header,
    .sheet.thermal_58mm .meta,
    .sheet.thermal_58mm .totals,
    .sheet.thermal_58mm .footer {
        padding-bottom: 8px;
        margin-bottom: 8px;
    }

    .sheet.thermal_58mm .barcode {
        margin: 8px 0;
    }

    {{-- max-width en mm (no solo el 220px/100% generico) como respaldo
    fisico: si el motor de impresion no reescala el SVG igual que la
    pantalla, este limite es absoluto y no depende de ningun calculo de
    porcentaje. 38mm deja margen real dentro de los 48mm imprimibles. --}}
    .sheet.thermal_58mm .barcode svg {
        max-width: 38mm;
    }

    .sheet.thermal_58mm .totals-row {
        margin: 2px 0;
    }

    {{-- La tabla de items usaba el mismo 12px que 80mm (fijo aparte del
    11px general del formato) — en 48mm de ancho util eso hacia que el
    header "Cant." se saliera de su columna y se montara sobre "Precio".
    --}}
    .sheet.thermal_58mm .items th,
    .sheet.thermal_58mm .items td {
        font-size: 8px;
        padding-top: 4px;
        padding-bottom: 4px;
    }

    .sheet.thermal_58mm .items {
        margin-bottom: 8px;
    }

    {{-- Los mismos % de 80mm (42/13/21/24) no alcanzaban en 48mm: incluso
    con el font-size mas chico, "Cant." (el header, no los valores)
    seguia saliendose de su columna y pegandose contra "Precio". Cant.
    necesita mas proporcion aca porque el ancho fisico disponible es
    casi un tercio menor que en 80mm, no porque el contenido cambie. --}}
    .sheet.thermal_58mm .items th:first-child,
    .sheet.thermal_58mm .items td:first-child {
        width: 42%;
    }

    .sheet.thermal_58mm .items th:nth-child(2),
    .sheet.thermal_58mm .items td:nth-child(2) {
        width: 14%;
    }

    .sheet.thermal_58mm .items th:nth-child(3),
    .sheet.thermal_58mm .items td:nth-child(3) {
        width: 18%;
    }

    {{-- Total seguia saliendose un poco en el papel real incluso con mas
    % de columna: el texto quedaba pegado al borde imprimible. Con la
    tabla en 9px ya sobra ancho, asi que en vez de pelearlo con % se
    corre el contenido a la izquierda con mas padding-right. --}}
    .sheet.thermal_58mm .items th:last-child,
    .sheet.thermal_58mm .items td:last-child {
        width: 26%;
        padding-right: 6px;
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

    .meta-line.is-strong {
        font-weight: 700;
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

    {{-- QR al recibo publico (ver public-receipt.blade.php): va en el
    footer, separado del codigo de barras (ese es para el scanner del
    cajero, el QR es para el celular del cliente). --}}
    .receipt-qr {
        margin: 14px 0 0;
        text-align: center;
    }

    .receipt-qr svg {
        display: block;
        width: 120px;
        height: 120px;
        margin: 0 auto;
    }

    .receipt-qr-text {
        margin: 4px 0 0;
        font-size: 10px;
        color: var(--muted);
    }

    .items {
        width: 100%;
        table-layout: fixed;
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

    {{-- Sin table-layout:fixed + anchos por columna, un nombre de
    producto largo estira la fila entera y empuja Precio/Total fuera del
    area imprimible (se ven cortados en el papel aunque en pantalla se
    vean completos). word-break permite que el nombre baje de linea en
    vez de ensanchar la columna. --}}
    .items th:first-child,
    .items td:first-child {
        width: 42%;
        padding-left: 0;
        word-break: break-word;
        overflow-wrap: break-word;
    }

    .items td:first-child {
        font-weight: 700;
    }

    {{-- Alineado a la izquierda: el intento de alinear a la derecha
    (padding, tabular-nums, colgroup) no resolvia lo que el usuario veia
    en su pantalla/impresora, asi que se descarto right-align del todo
    por algo mas simple y predecible. padding-right se mantiene solo
    como respiro antes del borde del papel (ver nota de anchos
    48mm/72mm mas arriba), no para alinear nada. --}}
    .items th:last-child,
    .items td:last-child {
        text-align: left;
        padding-right: 10px;
        font-variant-numeric: tabular-nums;
    }

    {{-- Cant./Precio comparten el mismo corrimiento sutil hacia la
    izquierda que Total, para que las tres columnas numericas queden
    alineadas visualmente. --}}
    .items th:nth-child(2),
    .items td:nth-child(2),
    .items th:nth-child(3),
    .items td:nth-child(3) {
        padding-right: 6px;
    }

    .items th:nth-child(2),
    .items td:nth-child(2) {
        width: 13%;
    }

    .items th:nth-child(3),
    .items td:nth-child(3) {
        width: 21%;
    }

    {{-- El desborde real no era de ancho: era el padding-left:10px que
    esta columna tenia antes (pensado para separarla de Precio cuando
    el texto estaba alineado a la derecha) empujando el numero hacia el
    borde. Ya se quito ese padding; el 24% (2 puntos mas que Precio,
    nada mas) es solo colchon extra para "121.800", el valor mas largo
    de la tabla. --}}
    .items th:last-child,
    .items td:last-child {
        width: 24%;
    }

    .items th:nth-child(2),
    .items td:nth-child(2),
    .items th:nth-child(3),
    .items td:nth-child(3),
    .items th:nth-child(4),
    .items td:nth-child(4) {
        text-align: left;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .items td:nth-child(2),
    .items td:nth-child(3),
    .items td:nth-child(4) {
        font-weight: 700;
    }

    .totals-row {
        display: flex;
        gap: 12px;
        font-size: 13px;
        margin: 4px 0;
    }

    {{-- El valor toma el espacio que sobra despues de la etiqueta
    (Subtotal/Descuentos/...) y se centra ahi, en vez de quedar pegado
    al borde derecho. --}}
    .totals-row span:last-child {
        flex: 1;
        text-align: center;
        font-variant-numeric: tabular-nums;
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

    {{-- margin-right (no margin-left) para correrlo un poco a la
    izquierda del centro: .footer tiene text-align:center, y centra la
    caja del chip incluyendo sus margenes — mas margen solo del lado
    derecho desplaza el contenido visible hacia la izquierda sin tener
    que reestructurar el layout. --}}
    .brand-chip {
        display: inline-block;
        margin-top: 6px;
        margin-right: 14px;
        padding: 4px 8px;
        border-radius: 999px;
        background: #fef3c7;
        color: #92400e;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    {{-- Aviso "NO PAGADO" de la cuenta preliminar de una mesa (ver
    printing/dining/order-preview-ticket.blade.php): visible en pantalla
    Y en papel (incluida la version 1 bit de las termicas), a diferencia
    de .brand-chip que si se fuerza a blanco/negro en @media print. --}}
    .unpaid-banner {
        margin: 0 0 12px;
        padding: 8px;
        border: 2px solid #b91c1c;
        border-radius: 6px;
        text-align: center;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #b91c1c;
    }

    .sheet.thermal_58mm .unpaid-banner {
        font-size: 11px;
        padding: 6px;
    }

    {{-- Los tamaños de fuente/anchos de arriba estan calibrados en mm
    reales para la impresora termica (48/72mm) — a esa escala, en un
    monitor normal el ticket se ve diminuto e ilegible. `zoom` (no
    `transform: scale`, que no reacomoda el layout y se solaparia con
    los botones de al lado) agranda SOLO la vista en pantalla; esta
    regla vive fuera de @media print asi que la impresion real no
    cambia en nada. --}}
    @media screen {
        .sheet.thermal_58mm,
        .sheet.thermal_80mm {
            zoom: 2;
        }
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

        .page-layout {
            padding: 0;
            gap: 0;
        }

        .sheet {
            box-shadow: none;
        }

        .screen-actions {
            display: none;
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
