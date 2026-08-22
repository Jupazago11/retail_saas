<?php

namespace App\Actions\Subscriptions;

use App\Enums\RecordStatus;
use App\Models\PaymentAttachment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AttachPaymentProof
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * El bucket de R2 se configura despues (las variables R2_* del .env
     * llegan por separado); mientras esten vacias, avisamos claro en vez
     * de fallar con un error crudo del SDK de S3.
     */
    public function isStorageConfigured(): bool
    {
        return Config::get('filesystems.disks.r2.key')
            && Config::get('filesystems.disks.r2.secret')
            && Config::get('filesystems.disks.r2.bucket')
            && Config::get('filesystems.disks.r2.endpoint');
    }

    public function handle(Subscription $subscription, UploadedFile $file, ?User $actor = null): PaymentAttachment
    {
        if (! $this->isStorageConfigured()) {
            throw new InvalidArgumentException('Todavia no se configuraron las credenciales de Cloudflare R2 (variables R2_* en el .env).');
        }

        $path = $this->buildPath($subscription, $file);

        // Sin visibilidad publica a proposito: el bucket es privado, las
        // fotos se muestran despues con un link firmado que vence solo
        // (ver PaymentAttachment::temporaryUrl()).
        //
        // isStorageConfigured() solo verifica que las variables existan, no
        // que sean correctas ni que R2 sea alcanzable — sin este try/catch,
        // credenciales invalidas tiraban un 500 crudo del SDK de S3.
        try {
            Storage::disk('r2')->putFileAs('', $file, $path);
        } catch (\Throwable $exception) {
            report($exception);

            throw new InvalidArgumentException('No se pudo subir el archivo a Cloudflare R2. Verifica que las credenciales sean correctas o intenta de nuevo.');
        }

        $attachment = PaymentAttachment::create([
            'subscription_id' => $subscription->id,
            'disk' => 'r2',
            'path' => $path,
            'url' => Storage::disk('r2')->url($path),
            'original_filename' => $file->getClientOriginalName(),
            'status' => RecordStatus::Active->value,
            'uploaded_by' => $actor?->id,
        ]);

        $this->auditLogger->logCreated($subscription->company, 'payment_attachment.uploaded', $attachment, $actor);

        return $attachment;
    }

    /**
     * companies/{id}-{slug}/payment-proofs/{subscription_id}/{uuid}.{ext}
     *
     * Todo queda agrupado por empresa primero a proposito: con la
     * plataforma creciendo, hace falta poder abrir el bucket y ubicar todo
     * lo de un cliente sin tener que adivinar, y deja espacio para meter
     * otras carpetas de documentos por empresa mas adelante (no solo
     * comprobantes de pago) sin reorganizar nada de lo ya subido.
     */
    protected function buildPath(Subscription $subscription, UploadedFile $file): string
    {
        $company = $subscription->company;
        $companyFolder = $company
            ? $company->id.'-'.$company->slug
            : 'sin-empresa';

        return sprintf(
            'companies/%s/payment-proofs/%d/%s.%s',
            $companyFolder,
            $subscription->id,
            Str::uuid(),
            $file->getClientOriginalExtension(),
        );
    }

    public function archive(PaymentAttachment $attachment, ?User $actor = null): PaymentAttachment
    {
        $before = $attachment->replicate()->forceFill(['id' => $attachment->id]);

        $attachment->update(['status' => RecordStatus::Archived->value]);

        $this->auditLogger->logUpdated($attachment->subscription->company, 'payment_attachment.archived', $before, $attachment->fresh(), $actor);

        return $attachment;
    }
}
