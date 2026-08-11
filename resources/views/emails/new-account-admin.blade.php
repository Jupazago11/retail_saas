<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nueva cuenta registrada</title>
</head>
<body style="margin:0; padding:0; background-color:#f9fafb; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $user->name }} ({{ $user->username }}) acaba de crear una cuenta en {{ \App\Models\PlatformSetting::appName() }}.
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

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 4px 0; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#2563eb;">
                                Nueva cuenta
                            </p>
                            <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:800; color:#111827;">
                                Alguien se acaba de registrar
                            </h1>
                        </td>
                    </tr>

                    {{-- Detalles --}}
                    <tr>
                        <td style="padding:0 32px 28px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:12px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="padding:5px 12px 5px 0; font-size:13px; color:#6b7280; white-space:nowrap;">Nombre</td>
                                                <td style="padding:5px 0; font-size:14px; font-weight:600; color:#111827;">{{ $user->name }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:5px 12px 5px 0; font-size:13px; color:#6b7280; white-space:nowrap;">Usuario</td>
                                                <td style="padding:5px 0; font-size:14px; font-weight:600; color:#111827; font-family:'Courier New', monospace;">{{ $user->username }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:5px 12px 5px 0; font-size:13px; color:#6b7280; white-space:nowrap;">Correo</td>
                                                <td style="padding:5px 0; font-size:14px; font-weight:600; color:#111827;">{{ $user->email }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:5px 12px 5px 0; font-size:13px; color:#6b7280; white-space:nowrap;">Fecha</td>
                                                <td style="padding:5px 0; font-size:14px; font-weight:600; color:#111827;">{{ $user->created_at->timezone(config('app.timezone'))->translatedFormat('d/m/Y H:i') }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
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
