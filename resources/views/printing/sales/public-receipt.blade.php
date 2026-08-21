<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#b45309">
    <title>Compra {{ $sale->document_number }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #1c1917;
            --muted: #78716c;
            --line: #e7e5e4;
            --panel: #f4f1ec;
            --brand: #b45309;
            --brand-dark: #92400e;
            --brand-soft: #fef3e2;
            --ok: #15803d;
            --ok-soft: #ecfdf3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--panel);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        .wrap {
            max-width: 460px;
            margin: 0 auto;
            padding: 20px 16px calc(32px + env(safe-area-inset-bottom, 0px));
        }

        .card {
            position: relative;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 12px 32px rgba(28, 25, 23, 0.12);
            overflow: hidden;
        }

        .band {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            padding: 22px 20px 40px;
            text-align: center;
            color: #fff;
        }

        .avatar {
            width: 52px;
            height: 52px;
            margin: 0 auto 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            border: 1.5px solid rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
        }

        .company-name {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            padding: 5px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 12px;
            font-weight: 700;
        }

        .status-pill svg {
            width: 13px;
            height: 13px;
        }

        .meta {
            margin: -26px 20px 0;
            position: relative;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 6px 16px rgba(28, 25, 23, 0.08);
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .meta-block p {
            margin: 0;
        }

        .meta-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .meta-value {
            margin-top: 2px !important;
            font-size: 13.5px;
            font-weight: 700;
        }

        .meta-block.align-right {
            text-align: right;
        }

        .body {
            padding: 22px 20px 4px;
        }

        .section-title {
            margin: 0 0 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .items {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .items li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f2f0ed;
        }

        .items li:last-child {
            border-bottom: 0;
        }

        .item-index {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border-radius: 7px;
            background: var(--brand-soft);
            color: var(--brand-dark);
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
        }

        .item-main {
            flex: 1;
            min-width: 0;
        }

        .item-name {
            margin: 0;
            font-weight: 700;
            font-size: 14.5px;
            word-break: break-word;
        }

        .item-detail {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 12.5px;
        }

        .item-total {
            flex-shrink: 0;
            font-weight: 800;
            font-size: 14.5px;
            white-space: nowrap;
            padding-top: 1px;
        }

        .totals {
            margin-top: 6px;
            padding: 14px 0 6px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
            margin: 6px 0;
            color: var(--muted);
        }

        .total-band {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--brand-soft);
            border-radius: 12px;
            padding: 12px 16px;
        }

        .total-band .label {
            font-size: 13px;
            font-weight: 700;
            color: var(--brand-dark);
        }

        .total-band .amount {
            font-size: 21px;
            font-weight: 800;
            color: var(--brand-dark);
        }

        .scallop {
            height: 14px;
            margin-top: 18px;
            background: radial-gradient(circle at 7px 7px, var(--panel) 7px, transparent 7.5px) 0 0 / 14px 14px repeat-x;
        }

        .footer {
            text-align: center;
            margin-top: 18px;
            color: var(--muted);
        }

        .footer .thanks {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
        }

        .footer .powered {
            margin: 4px 0 0;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="band">
                <div class="avatar">{{ mb_strtoupper(mb_substr($company->display_name, 0, 1)) }}</div>
                <p class="company-name">{{ $company->display_name }}</p>
                <span class="status-pill">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Compra confirmada
                </span>
            </div>

            <div class="meta">
                <div class="meta-block">
                    <p class="meta-label">Numero</p>
                    <p class="meta-value">{{ $sale->document_number }}</p>
                </div>
                <div class="meta-block align-right">
                    <p class="meta-label">Fecha</p>
                    <p class="meta-value">{{ optional($sale->sold_at)->format('d/m/Y h:i a') ?? $sale->created_at->format('d/m/Y h:i a') }}</p>
                </div>
            </div>

            <div class="body">
                <p class="section-title">Productos</p>
                <ul class="items">
                    @foreach ($items as $index => $item)
                        <li>
                            <span class="item-index">{{ $index + 1 }}</span>
                            <div class="item-main">
                                <p class="item-name">{{ $item['description'] }}</p>
                                <p class="item-detail">{{ $item['quantity'] }} x {{ $item['unit_price'] }}</p>
                            </div>
                            <div class="item-total">{{ $item['line_total'] }}</div>
                        </li>
                    @endforeach
                </ul>

                <div class="totals">
                    <div class="totals-row">
                        <span>Subtotal</span>
                        <span>{{ $subtotal }}</span>
                    </div>
                    <div class="totals-row">
                        <span>Impuestos</span>
                        <span>{{ $taxIncludedTotal }}</span>
                    </div>
                    <div class="total-band">
                        <span class="label">Total</span>
                        <span class="amount">{{ $grandTotal }}</span>
                    </div>
                </div>
            </div>

            <div class="scallop"></div>
        </div>

        <div class="footer">
            <p class="thanks">Gracias por tu compra en {{ $company->display_name }}</p>
            <p class="powered">Comprobante digital &middot; {{ \App\Models\PlatformSetting::appName() }}</p>
        </div>
    </div>
</body>
</html>
