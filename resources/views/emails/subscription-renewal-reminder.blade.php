<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hoy vence tu suscripcion de {{ \App\Models\PlatformSetting::appName() }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f9fafb; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Hoy es el ultimo dia para pagar y mantener tu suscripcion activa sin interrupciones.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">

                    {{-- Header --}}
                    <tr>
                        <td align="center" bgcolor="#2563eb" style="background-color:#2563eb; background-image:linear-gradient(135deg, #2563eb, #7c3aed); padding:28px 24px;">
                            <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:4px; text-transform:uppercase; color:#ffffff;">
                                {{ \App\Models\PlatformSetting::appName() }}
                            </p>
                        </td>
                    </tr>

                    {{-- Icono --}}
                    <tr>
                        <td align="center" style="padding:32px 32px 0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="56" height="56" align="center" valign="middle" bgcolor="#fffbeb" style="background-color:#fffbeb; border-radius:9999px; font-size:26px; font-weight:700; color:#d97706; line-height:56px;">
                                        !
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td align="center" style="padding:20px 32px 8px 32px;">
                            <p style="margin:0 0 6px 0; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#d97706;">
                                Vence hoy
                            </p>
                            <h1 style="margin:0 0 16px 0; font-size:24px; font-weight:800; color:#111827;">
                                Hola, {{ \Illuminate\Support\Str::of($subscription->company->owner->name)->before(' ') }}
                            </h1>
                            <p style="margin:0 0 12px 0; font-size:15px; line-height:24px; color:#4b5563; text-align:left;">
                                Tu plan <strong>{{ $subscription->plan?->name ?? 'actual' }}</strong> de <strong>{{ $subscription->company->display_name }}</strong> vence hoy, {{ $subscription->ends_at->format('d/m/Y') }}. Este es tu ultimo dia para realizar el pago y mantener tu cuenta activa sin interrupciones.
                            </p>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    @php($whatsappUrl = \App\Models\PlatformSetting::whatsappUrl('Hola, quiero enviar el comprobante de pago de la suscripcion de '.$subscription->company->display_name.'.'))
                    @if ($whatsappUrl !== '')
                        <tr>
                            <td style="padding:0 32px 24px 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fffbeb; border:1px solid #fde68a; border-radius:12px;">
                                    <tr>
                                        <td style="padding:20px 24px;">
                                            <p style="margin:0; font-size:14px; line-height:22px; color:#78350f;">
                                                Envianos tu comprobante de pago por WhatsApp y activamos tu cuenta el mismo dia.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding:0 32px 36px 32px;">
                                <a href="{{ $whatsappUrl }}" style="display:inline-block; background-color:#059669; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700; padding:12px 32px; border-radius:8px;">
                                    Enviar comprobante por WhatsApp
                                </a>
                            </td>
                        </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="background-color:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 24px;">
                            <p style="margin:0; font-size:11px; color:#9ca3af;">
                                Powered by {{ \App\Models\PlatformSetting::appName() }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
