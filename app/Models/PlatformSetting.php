<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlatformSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, string $default = ''): string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function appName(): string
    {
        return static::get('app_name', config('app.name', 'Laravel'));
    }

    public static function ownerNotificationEmail(): string
    {
        return static::get('owner_notification_email', 'jupazago11@gmail.com');
    }

    /**
     * Ruta del logo en el disco "r2", o null si la plataforma sigue usando
     * el logo por defecto (no se ha subido nada o se elimino).
     */
    public static function logoPath(): ?string
    {
        $path = static::get('app_logo_path', '');

        return $path !== '' ? $path : null;
    }

    /**
     * El bucket de logos es el mismo "r2" privado que los comprobantes de
     * pago (ver PaymentAttachment) — Railway no conserva el disco local
     * entre despliegues, asi que cualquier archivo subido por el usuario
     * tiene que vivir en storage externo. Como no es publico, se firma una
     * URL temporal larga en cada render en vez de guardar un link fijo.
     */
    public static function logoUrl(): ?string
    {
        $path = static::logoPath();

        if ($path === null) {
            return null;
        }

        return Storage::disk('r2')->temporaryUrl($path, now()->addDay());
    }

    /**
     * Link de WhatsApp (wa.me) hacia el numero de soporte configurado en
     * "Telefono / WhatsApp". Normaliza el numero a formato internacional
     * (agrega el indicativo 57 de Colombia si el numero guardado son 10
     * digitos sin el).
     */
    public static function whatsappUrl(?string $message = null): string
    {
        $digits = preg_replace('/\D+/', '', static::get('contact_phone', config('platform.contact_phone', '')));

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10) {
            $digits = '57'.$digits;
        }

        $url = "https://wa.me/{$digits}";

        if ($message !== null && $message !== '') {
            $url .= '?text='.rawurlencode($message);
        }

        return $url;
    }
}
