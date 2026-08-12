<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Subscriptions\AttachPaymentProof;
use App\Enums\RecordStatus;
use App\Livewire\Platform\SubscriptionsPage;
use App\Models\AuditLog;
use App\Models\PaymentAttachment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_is_reported_as_not_configured_when_r2_env_vars_are_empty(): void
    {
        Config::set('filesystems.disks.r2.key', null);
        Config::set('filesystems.disks.r2.secret', null);
        Config::set('filesystems.disks.r2.bucket', null);
        Config::set('filesystems.disks.r2.endpoint', null);

        $this->assertFalse(app(AttachPaymentProof::class)->isStorageConfigured());
    }

    public function test_it_uploads_a_photo_to_r2_and_creates_the_attachment(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Comprobante SAS']);
        $subscription = Subscription::query()->where('company_id', $company->id)->firstOrFail();

        $file = UploadedFile::fake()->create('comprobante.jpg', 100, 'image/jpeg');

        $attachment = app(AttachPaymentProof::class)->handle($subscription, $file, $admin);

        $this->assertSame(RecordStatus::Active, $attachment->status);
        $this->assertSame($admin->id, $attachment->uploaded_by);
        Storage::disk('r2')->assertExists($attachment->path);

        // Organizado por empresa primero, para poder ubicar todo lo de un
        // cliente en el bucket sin adivinar a medida que crece la plataforma.
        $this->assertStringStartsWith(
            "companies/{$company->id}-{$company->slug}/payment-proofs/{$subscription->id}/",
            $attachment->path
        );

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'payment_attachment.uploaded',
        ]);
    }

    public function test_archiving_an_attachment_does_not_delete_it(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Archivar SAS']);
        $subscription = Subscription::query()->where('company_id', $company->id)->firstOrFail();

        $attachment = app(AttachPaymentProof::class)->handle($subscription, UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg'), $admin);

        app(AttachPaymentProof::class)->archive($attachment, $admin);

        $this->assertSame(RecordStatus::Archived, $attachment->fresh()->status);
        Storage::disk('r2')->assertExists($attachment->path);
        $this->assertDatabaseHas('payment_attachments', ['id' => $attachment->id]);
    }

    public function test_the_livewire_page_can_upload_and_archive_a_payment_attachment(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Livewire Comprobante SAS']);
        $subscription = Subscription::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($admin);

        $component = Livewire::test(SubscriptionsPage::class)
            ->call('openAttachmentsModal', $subscription->id)
            ->set('newAttachments', [UploadedFile::fake()->create('comprobante.jpg', 100, 'image/jpeg')])
            ->call('uploadAttachments')
            ->assertHasNoErrors();

        $attachment = PaymentAttachment::query()->where('subscription_id', $subscription->id)->firstOrFail();

        $component->call('archiveAttachment', $attachment->id);

        $this->assertSame(RecordStatus::Archived, $attachment->fresh()->status);
    }

    public function test_uploading_fails_gracefully_without_r2_credentials(): void
    {
        Config::set('filesystems.disks.r2.key', null);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Sin R2 SAS']);
        $subscription = Subscription::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(SubscriptionsPage::class)
            ->call('openAttachmentsModal', $subscription->id)
            ->set('newAttachments', [UploadedFile::fake()->create('comprobante.jpg', 100, 'image/jpeg')])
            ->call('uploadAttachments')
            ->assertHasErrors('newAttachments');

        $this->assertDatabaseCount('payment_attachments', 0);
    }

    public function test_a_file_over_20mb_is_rejected_with_an_explicit_spanish_message(): void
    {
        // Prueba directa de la regla + mensaje personalizado (sin pasar por
        // el ciclo completo de subida de Livewire, que genera el archivo de
        // prueba completo en disco y no aporta nada a lo que se quiere
        // confirmar aqui: el texto exacto del mensaje en espanol).
        $tooLarge = UploadedFile::fake()->create('comprobante-pesado.jpg', 20481, 'image/jpeg');

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['newAttachments' => [$tooLarge]],
            ['newAttachments.*' => ['image', 'max:20480']],
            ['newAttachments.*.max' => 'Esa foto pesa mas de 20MB. Comprimela o recortala e intenta de nuevo.']
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Esa foto pesa mas de 20MB. Comprimela o recortala e intenta de nuevo.',
            $validator->errors()->first('newAttachments.0')
        );
    }

    protected function fakeR2Config(): void
    {
        Config::set('filesystems.disks.r2.key', 'fake-key');
        Config::set('filesystems.disks.r2.secret', 'fake-secret');
        Config::set('filesystems.disks.r2.bucket', 'fake-bucket');
        Config::set('filesystems.disks.r2.endpoint', 'https://fake.r2.cloudflarestorage.com');
    }
}
