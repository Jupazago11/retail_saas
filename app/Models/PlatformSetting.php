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
}
