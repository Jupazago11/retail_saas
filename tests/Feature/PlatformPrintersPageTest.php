<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Livewire\Platform\PlatformPrintersPage;
use App\Models\PrinterGuide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformPrintersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_a_guide_without_a_file(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformPrintersPage::class)
            ->call('openCreate')
            ->set('title', 'Xprinter XP-58')
            ->set('instructions', 'Cambiar el puerto de LPT1 a USB en Propiedades de impresora.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Xprinter XP-58');

        $guide = PrinterGuide::query()->where('title', 'Xprinter XP-58')->firstOrFail();
        $this->assertSame(RecordStatus::Active, $guide->status);
        $this->assertNull($guide->path);
    }

    public function test_platform_admin_can_upload_any_file_extension_to_r2(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformPrintersPage::class)
            ->call('openCreate')
            ->set('title', 'Impresora con instalador')
            ->set('instructions', 'Pasos de prueba')
            ->set('file', UploadedFile::fake()->create('driver.exe', 500))
            ->call('save')
            ->assertHasNoErrors();

        $guide = PrinterGuide::query()->where('title', 'Impresora con instalador')->firstOrFail();
        $this->assertSame('driver.exe', $guide->original_filename);
        Storage::disk('r2')->assertExists($guide->path);
    }

    public function test_replacing_the_file_on_edit_deletes_the_old_one(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformPrintersPage::class)
            ->call('openCreate')
            ->set('title', 'Impresora con instalador')
            ->set('instructions', 'Pasos de prueba')
            ->set('file', UploadedFile::fake()->create('driver.exe', 500))
            ->call('save')
            ->assertHasNoErrors();

        $guide = PrinterGuide::query()->where('title', 'Impresora con instalador')->firstOrFail();
        $oldPath = $guide->path;

        Livewire::test(PlatformPrintersPage::class)
            ->call('startEdit', $guide->id)
            ->set('file', UploadedFile::fake()->create('driver-v2.exe', 300))
            ->call('save')
            ->assertHasNoErrors();

        $guide->refresh();
        $this->assertNotSame($oldPath, $guide->path);
        Storage::disk('r2')->assertMissing($oldPath);
        Storage::disk('r2')->assertExists($guide->path);
    }

    public function test_uploading_fails_gracefully_without_r2_credentials(): void
    {
        Config::set('filesystems.disks.r2.key', null);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformPrintersPage::class)
            ->call('openCreate')
            ->set('title', 'Impresora con instalador')
            ->set('instructions', 'Pasos de prueba')
            ->set('file', UploadedFile::fake()->create('driver.exe', 500))
            ->call('save')
            ->assertHasErrors('file');

        $this->assertDatabaseCount('printer_guides', 0);
    }

    public function test_uploading_fails_gracefully_when_r2_upload_throws(): void
    {
        // Reproduce el 500 reportado: isStorageConfigured() solo mira que
        // las variables existan, no que sean validas ni que R2 sea
        // alcanzable — con credenciales presentes pero un fallo real de
        // subida (SDK de S3, red, bucket incorrecto...), antes de este fix
        // la excepcion cruda del SDK se colaba sin capturar hasta un 500.
        $this->fakeR2Config();

        // partialMock (no shouldReceive puro): Livewire sube el temp file al
        // disco "local" por su cuenta antes de que save() corra — con un
        // mock completo de la fachada, esa llamada tambien quedaria
        // interceptada y fallaria por falta de expectativa.
        Storage::partialMock()
            ->shouldReceive('disk')
            ->with('r2')
            ->andReturnUsing(function () {
                $disk = \Mockery::mock();
                $disk->shouldReceive('putFileAs')->andThrow(new \RuntimeException('R2 unreachable'));

                return $disk;
            });

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformPrintersPage::class)
            ->call('openCreate')
            ->set('title', 'Impresora con instalador')
            ->set('instructions', 'Pasos de prueba')
            ->set('file', UploadedFile::fake()->create('driver.exe', 500))
            ->call('save')
            ->assertHasErrors('file');

        $this->assertDatabaseCount('printer_guides', 0);
    }

    public function test_toggle_status_flips_active_and_inactive(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $guide = PrinterGuide::create([
            'title' => 'Guia de prueba',
            'instructions' => 'Pasos de prueba',
            'status' => RecordStatus::Active->value,
        ]);

        $this->actingAs($admin);

        Livewire::test(PlatformPrintersPage::class)->call('toggleStatus', $guide->id);
        $this->assertSame(RecordStatus::Inactive, $guide->fresh()->status);

        Livewire::test(PlatformPrintersPage::class)->call('toggleStatus', $guide->id);
        $this->assertSame(RecordStatus::Active, $guide->fresh()->status);
    }

    public function test_non_platform_admin_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($viewer);

        Livewire::test(PlatformPrintersPage::class)->assertStatus(403);
    }

    protected function fakeR2Config(): void
    {
        Config::set('filesystems.disks.r2.key', 'fake-key');
        Config::set('filesystems.disks.r2.secret', 'fake-secret');
        Config::set('filesystems.disks.r2.bucket', 'fake-bucket');
        Config::set('filesystems.disks.r2.endpoint', 'https://fake.r2.cloudflarestorage.com');
    }
}
