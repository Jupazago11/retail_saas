<?php

namespace App\Actions\Settings;

use App\Models\Company;
use App\Services\Settings\CompanySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateCompanyLogo
{
    // Luminosidad (0-255, formula estandar de percepcion de brillo) desde
    // la cual un pixel se considera "claro" y se deja transparente en vez
    // de pintarlo de negro. No es un valor exacto que se pueda calcular:
    // 140 es el tipico punto medio usado para umbralizar logos/texto.
    protected const LUMINANCE_THRESHOLD = 140;

    // Un logo de ticket nunca necesita mas resolucion que esto — caparlo
    // aca evita procesar pixel por pixel una foto de varios megapixeles
    // que alguien suba por error (la validacion solo limita el peso del
    // archivo, no las dimensiones).
    protected const MAX_DIMENSION = 400;

    public function __construct(
        protected CompanySettings $companySettings,
    ) {
    }

    /**
     * Mismo bucket "r2" privado que el logo de plataforma y los comprobantes
     * de pago (ver UpdatePlatformLogo, AttachPaymentProof) — credenciales
     * via las variables R2_* del .env.
     */
    public function isStorageConfigured(): bool
    {
        return Config::get('filesystems.disks.r2.key')
            && Config::get('filesystems.disks.r2.secret')
            && Config::get('filesystems.disks.r2.bucket')
            && Config::get('filesystems.disks.r2.endpoint');
    }

    /**
     * Guarda dos archivos: el original tal cual lo subio la empresa (para
     * cualquier vista a color, ej. la miniatura de Reglas) y una version
     * aparte ya convertida a blanco/negro puro por umbral, pensada
     * especificamente para el ticket termico (ver convertToPrintReadyPng).
     */
    public function handle(Company $company, UploadedFile $file): void
    {
        if (! $this->isStorageConfigured()) {
            throw new InvalidArgumentException('Todavia no se configuraron las credenciales de Cloudflare R2 (variables R2_* en el .env).');
        }

        $this->deleteExisting($company);

        // companies/{id}-{slug}/logo/{uuid}.{ext} — mismo agrupado por
        // empresa primero que ya usa AttachPaymentProof.
        $folder = 'companies/'.$company->id.'-'.$company->slug.'/logo';
        $id = (string) Str::uuid();

        $originalPath = $folder.'/'.$id.'.'.$file->getClientOriginalExtension();
        Storage::disk('r2')->putFileAs('', $file, $originalPath);

        $printPath = $folder.'/'.$id.'-print.png';
        Storage::disk('r2')->put($printPath, $this->convertToPrintReadyPng($file->getRealPath()));

        $this->companySettings->set($company, 'general', 'logo_path', $originalPath);
        $this->companySettings->set($company, 'general', 'logo_print_path', $printPath);
    }

    public function remove(Company $company): void
    {
        $this->deleteExisting($company);

        $this->companySettings->set($company, 'general', 'logo_path', '');
        $this->companySettings->set($company, 'general', 'logo_print_path', '');
    }

    /**
     * El bucket es privado, asi que en vez de un link fijo se firma una URL
     * temporal en cada render — igual que PlatformSetting::logoUrl(). Esta
     * es la version ORIGINAL a color, para cualquier vista que no sea el
     * ticket termico.
     */
    public function currentUrl(Company $company): ?string
    {
        return $this->signedUrl($this->path($company));
    }

    /**
     * Version blanco/negro pensada para el ticket. Si la empresa subio su
     * logo antes de que existiera esta conversion, todavia no tiene archivo
     * "print" propio — se usa el original como respaldo (no ideal, pero
     * mejor que no mostrar nada) hasta que lo vuelvan a subir.
     */
    public function currentPrintUrl(Company $company): ?string
    {
        return $this->signedUrl($this->printPath($company)) ?? $this->currentUrl($company);
    }

    protected function signedUrl(?string $path): ?string
    {
        if ($path === null || ! $this->isStorageConfigured()) {
            return null;
        }

        return Storage::disk('r2')->temporaryUrl($path, now()->addDay());
    }

    protected function path(Company $company): ?string
    {
        $path = trim((string) $this->companySettings->get($company, 'general', 'logo_path'));

        return $path !== '' ? $path : null;
    }

    protected function printPath(Company $company): ?string
    {
        $path = trim((string) $this->companySettings->get($company, 'general', 'logo_print_path'));

        return $path !== '' ? $path : null;
    }

    protected function deleteExisting(Company $company): void
    {
        if (! $this->isStorageConfigured()) {
            return;
        }

        foreach ([$this->path($company), $this->printPath($company)] as $existing) {
            if ($existing !== null) {
                Storage::disk('r2')->delete($existing);
            }
        }
    }

    /**
     * Convierte cualquier logo (a color, con degradados, con fondo) a un
     * PNG de verdad blanco/negro puro con transparencia: cada pixel se
     * clasifica por umbral de luminosidad, sin grises intermedios. Es lo
     * unico que evita el dithering borroso de una impresora termica de 1
     * bit — un filtro CSS (grayscale/contrast) no alcanza porque el
     * antialiasing de los bordes sigue mandando grises que la impresora
     * tiene que inventar con puntitos dispersos.
     */
    protected function convertToPrintReadyPng(string $sourcePath): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($sourcePath));

        if ($source === false) {
            throw new InvalidArgumentException('No se pudo leer la imagen del logo.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $scale = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            imagedestroy($source);
            $source = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
        $black = imagecolorallocatealpha($output, 0, 0, 0, 0);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $colors = imagecolorsforindex($source, imagecolorat($source, $x, $y));

                // alpha va de 0 (opaco) a 127 (transparente del todo): un
                // pixel casi transparente se deja como fondo, no como negro.
                if ($colors['alpha'] > 90) {
                    continue;
                }

                $luminance = 0.299 * $colors['red'] + 0.587 * $colors['green'] + 0.114 * $colors['blue'];

                if ($luminance <= self::LUMINANCE_THRESHOLD) {
                    imagesetpixel($output, $x, $y, $black);
                }
            }
        }

        ob_start();
        imagepng($output);
        $contents = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($output);

        return $contents;
    }
}
