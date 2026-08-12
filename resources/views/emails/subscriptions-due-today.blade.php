<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vencimientos de hoy — {{ \App\Models\PlatformSetting::appName() }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f9fafb; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $subscriptions->count() }} empresa(s) tienen su suscripcion venciendo hoy.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">

                    {{-- Header --}}
                    <tr>
                        <td align="center" bgcolor="#2563eb" style="background-color:#2563eb; background-image:linear-gradient(135deg, #2563eb, #7c3aed); padding:28px 24px;">
                            <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:4px; text-transform:uppercase; color:#ffffff;">
                                {{ \App\Models\PlatformSetting::appName() }}
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 4px 0; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#2563eb;">
                                Vencimientos de hoy
                            </p>
                            <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:800; color:#111827;">
                                {{ $subscriptions->count() }} {{ \Illuminate\Support\Str::plural('empresa', $subscriptions->count()) }} {{ $subscriptions->count() === 1 ? 'vence' : 'vencen' }} hoy
                            </h1>
                        </td>
                    </tr>

                    {{-- Tabla --}}
                    <tr>
                        <td style="padding:0 32px 28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <tr>
                                    <td style="padding:8px 8px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#6b7280; border-bottom:1px solid #e5e7eb;">Empresa</td>
                                    <td style="padding:8px 8px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#6b7280; border-bottom:1px solid #e5e7eb;">Plan</td>
                                    <td style="padding:8px 8px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:#6b7280; border-bottom:1px solid #e5e7eb;">Contacto</td>
                                </tr>
                                @foreach ($subscriptions as $subscription)
                                    <tr>
                                        <td style="padding:10px 8px; font-size:14px; color:#111827; font-weight:600; border-bottom:1px solid #f3f4f6;">{{ $subscription->company->display_name }}</td>
                                        <td style="padding:10px 8px; font-size:14px; color:#4b5563; border-bottom:1px solid #f3f4f6;">{{ $subscription->plan?->name ?? '—' }}</td>
                                        <td style="padding:10px 8px; font-size:13px; color:#4b5563; border-bottom:1px solid #f3f4f6;">
                                            {{ $subscription->company->owner?->name }}<br>
                                            <span style="color:#6b7280;">{{ $subscription->company->owner?->email }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

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
