<?php

namespace Tests\Feature;

use App\Actions\Platform\UpdatePlatformLogo;
use App\Livewire\Platform\PlatformSettingsPage;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_logo_the_default_laravel_mark_is_used(): void
    {
        $this->assertNull(PlatformSetting::logoUrl());
    }

    public function test_uploading_a_logo_stores_it_on_r2_and_replaces_the_default(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $action = app(UpdatePlatformLogo::class);
        $action->handle(UploadedFile::fake()->create('logo.png', 100, 'image/png'));

        $path = PlatformSetting::logoPath();
        $this->assertNotNull($path);
        Storage::disk('r2')->assertExists($path);
        $this->assertNotNull(PlatformSetting::logoUrl());
    }

    public function test_uploading_a_new_logo_deletes_the_previous_one(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $action = app(UpdatePlatformLogo::class);
        $action->handle(UploadedFile::fake()->create('logo.png', 100, 'image/png'));
        $firstPath = PlatformSetting::logoPath();

        $action->handle(UploadedFile::fake()->create('logo-2.png', 100, 'image/png'));
        $secondPath = PlatformSetting::logoPath();

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('r2')->assertMissing($firstPath);
        Storage::disk('r2')->assertExists($secondPath);
    }

    public function test_removing_the_logo_falls_back_to_the_default(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $action = app(UpdatePlatformLogo::class);
        $action->handle(UploadedFile::fake()->create('logo.png', 100, 'image/png'));
        $path = PlatformSetting::logoPath();

        $action->remove();

        Storage::disk('r2')->assertMissing($path);
        $this->assertNull(PlatformSetting::logoPath());
        $this->assertNull(PlatformSetting::logoUrl());
    }

    public function test_the_livewire_page_can_upload_and_remove_a_logo(): void
    {
        $this->fakeR2Config();
        Storage::fake('r2');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformSettingsPage::class)
            ->set('newLogo', UploadedFile::fake()->create('logo.png', 100, 'image/png'))
            ->call('uploadLogo')
            ->assertHasNoErrors();

        $this->assertNotNull(PlatformSetting::logoPath());

        Livewire::test(PlatformSettingsPage::class)
            ->call('removeLogo');

        $this->assertNull(PlatformSetting::logoPath());
    }

    public function test_uploading_fails_gracefully_without_r2_credentials(): void
    {
        Config::set('filesystems.disks.r2.key', null);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformSettingsPage::class)
            ->set('newLogo', UploadedFile::fake()->create('logo.png', 100, 'image/png'))
            ->call('uploadLogo')
            ->assertHasErrors('newLogo');

        $this->assertNull(PlatformSetting::logoPath());
    }

    public function test_non_platform_admin_cannot_access_the_page(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($user);

        Livewire::test(PlatformSettingsPage::class)
            ->assertForbidden();
    }

    protected function fakeR2Config(): void
    {
        Config::set('filesystems.disks.r2.key', 'fake-key');
        Config::set('filesystems.disks.r2.secret', 'fake-secret');
        Config::set('filesystems.disks.r2.bucket', 'fake-bucket');
        Config::set('filesystems.disks.r2.endpoint', 'https://fake.r2.cloudflarestorage.com');
    }
}
