<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bienvenido a {{ \App\Models\PlatformSetting::appName() }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f9fafb; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Gracias por unirte a {{ \App\Models\PlatformSetting::appName() }} — tu cuenta ya esta lista, aqui tienes tus datos de acceso.
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

                    {{-- Icono de bienvenida --}}
                    <tr>
                        <td align="center" style="padding:32px 32px 0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="56" height="56" align="center" valign="middle" bgcolor="#eff6ff" style="background-color:#eff6ff; border-radius:9999px; font-size:26px; font-weight:700; color:#2563eb; line-height:56px;">
                                        ✓
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td align="center" style="padding:20px 32px 8px 32px;">
                            <p style="margin:0 0 6px 0; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#2563eb;">
                                Cuenta creada
                            </p>
                            <h1 style="margin:0 0 16px 0; font-size:24px; font-weight:800; color:#111827;">
                                ¡Gracias por unirte, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}!
                            </h1>
                            <p style="margin:0 0 12px 0; font-size:15px; line-height:24px; color:#4b5563; text-align:left;">
                                Que bueno tenerte por aca. Gracias por confiar en <strong>{{ \App\Models\PlatformSetting::appName() }}</strong> para llevar tu negocio dia a dia — sabemos que administrar una tienda no es facil, y construimos esta herramienta pensando exactamente en eso: que llevar tu inventario, tus ventas y tu caja sea simple, rapido y sin dolores de cabeza.
                            </p>
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:24px; color:#4b5563; text-align:left;">
                                Tu cuenta ya esta lista. Estos son tus datos para entrar:
                            </p>
                        </td>
                    </tr>

                    {{-- Credenciales --}}
                    <tr>
                        <td style="padding:0 32px 24px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:12px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <p style="margin:0 0 4px 0; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#2563eb;">
                                            Tus datos de acceso
                                        </p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:10px;">
                                            <tr>
                                                <td style="padding:4px 12px 4px 0; font-size:14px; color:#1d4ed8;">Usuario</td>
                                                <td style="padding:4px 0; font-size:15px; font-weight:700; color:#111827; font-family:'Courier New', monospace;">{{ $user->username }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 12px 4px 0; font-size:14px; color:#1d4ed8;">Contraseña</td>
                                                <td style="padding:4px 0; font-size:15px; font-weight:700; color:#111827; font-family:'Courier New', monospace;">{{ $plainPassword }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:12px 0 0 0; font-size:12px; line-height:18px; color:#6b7280;">
                                Guarda este correo en un lugar seguro. Si lo necesitas, puedes cambiar tu contraseña despues de iniciar sesion.
                            </p>
                        </td>
                    </tr>

                    {{-- Proximos pasos --}}
                    <tr>
                        <td style="padding:0 32px 8px 32px;">
                            <p style="margin:0 0 10px 0; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#9ca3af;">
                                Para empezar
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="20" valign="top" style="padding:3px 0; font-size:14px; color:#2563eb; font-weight:700;">1.</td>
                                    <td style="padding:3px 0; font-size:14px; line-height:20px; color:#374151;">Registra tus primeros productos en el catalogo.</td>
                                </tr>
                                <tr>
                                    <td width="20" valign="top" style="padding:3px 0; font-size:14px; color:#2563eb; font-weight:700;">2.</td>
                                    <td style="padding:3px 0; font-size:14px; line-height:20px; color:#374151;">Abre tu caja y haz tu primera venta desde el POS.</td>
                                </tr>
                                <tr>
                                    <td width="20" valign="top" style="padding:3px 0; font-size:14px; color:#2563eb; font-weight:700;">3.</td>
                                    <td style="padding:3px 0; font-size:14px; line-height:20px; color:#374151;">Revisa tus reportes al cierre del dia.</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td align="center" style="padding:0 32px 36px 32px;">
                            <a href="{{ route('login') }}" style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700; padding:12px 32px; border-radius:8px;">
                                Entrar a mi cuenta
                            </a>
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
