<?php

namespace App\Actions\Platform;

use App\Models\PlatformSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdatePlatformLogo
{
    /**
     * Mismo bucket "r2" que los comprobantes de pago (ver
     * AttachPaymentProof::isStorageConfigured) — las credenciales llegan
     * por separado via las variables R2_* del .env.
     */
    public function isStorageConfigured(): bool
    {
        return Config::get('filesystems.disks.r2.key')
            && Config::get('filesystems.disks.r2.secret')
            && Config::get('filesystems.disks.r2.bucket')
            && Config::get('filesystems.disks.r2.endpoint');
    }

    public function handle(UploadedFile $file): void
    {
        if (! $this->isStorageConfigured()) {
            throw new InvalidArgumentException('Todavia no se configuraron las credenciales de Cloudflare R2 (variables R2_* en el .env).');
        }

        $this->deleteExisting();

        $path = 'platform/logo/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        Storage::disk('r2')->putFileAs('', $file, $path);

        PlatformSetting::set('app_logo_path', $path);
    }

    public function remove(): void
    {
        $this->deleteExisting();

        PlatformSetting::set('app_logo_path', '');
    }

    protected function deleteExisting(): void
    {
        $existing = PlatformSetting::logoPath();

        if ($existing !== null && $this->isStorageConfigured()) {
            Storage::disk('r2')->delete($existing);
        }
    }
}
