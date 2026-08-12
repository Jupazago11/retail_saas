<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
