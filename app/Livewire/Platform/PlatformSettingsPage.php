<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\UpdatePlatformLogo;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

class PlatformSettingsPage extends Component
{
    use InteractsWithToast, WithFileUploads;

    public string $bankName    = '';
    public string $bankAccount = '';
    public string $bankHolder  = '';
    public string $bankType    = 'Cuenta de ahorros';
    public string $bankNit     = '';
    public string $planPrice   = '';
    public string $contactEmail = '';
    public string $contactPhone = '';
    public string $appName     = '';
    public string $ownerNotificationEmail = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $newLogo = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->bankName     = PlatformSetting::get('bank_name',     config('platform.bank_name', ''));
        $this->bankAccount  = PlatformSetting::get('bank_account',  config('platform.bank_account', ''));
        $this->bankHolder   = PlatformSetting::get('bank_holder',   config('platform.bank_holder', ''));
        $this->bankType     = PlatformSetting::get('bank_type',     config('platform.bank_type', 'Cuenta de ahorros'));
        $this->bankNit      = PlatformSetting::get('bank_nit',      config('platform.bank_nit', ''));
        $this->planPrice    = PlatformSetting::get('plan_price',    config('platform.plan_price', ''));
        $this->contactEmail = PlatformSetting::get('contact_email', config('platform.contact_email', ''));
        $this->contactPhone = PlatformSetting::get('contact_phone', config('platform.contact_phone', ''));
        $this->appName      = PlatformSetting::appName();
        $this->ownerNotificationEmail = PlatformSetting::ownerNotificationEmail();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'bankName'     => ['nullable', 'string', 'max:255'],
            'bankAccount'  => ['nullable', 'string', 'max:100'],
            'bankHolder'   => ['nullable', 'string', 'max:255'],
            'bankType'     => ['nullable', 'string', 'max:100'],
            'bankNit'      => ['nullable', 'string', 'max:50'],
            'planPrice'    => ['nullable', 'string', 'max:100'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
            'appName'      => ['nullable', 'string', 'max:100'],
            'ownerNotificationEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $settings = [
            'bank_name'     => $this->bankName,
            'bank_account'  => $this->bankAccount,
            'bank_holder'   => $this->bankHolder,
            'bank_type'     => $this->bankType,
            'bank_nit'      => $this->bankNit,
            'plan_price'    => $this->planPrice,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'app_name'      => $this->appName,
            'owner_notification_email' => $this->ownerNotificationEmail,
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        $this->toast('Parámetros guardados correctamente.');
    }

    public function uploadLogo(UpdatePlatformLogo $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $this->validate([
            'newLogo' => ['required', 'image', 'max:4096'],
        ], [], ['newLogo' => 'logo']);

        try {
            $action->handle($this->newLogo);
        } catch (InvalidArgumentException $exception) {
            $this->addError('newLogo', $exception->getMessage());

            return;
        }

        $this->newLogo = null;
        $this->toast('Logo actualizado correctamente.');
    }

    public function removeLogo(UpdatePlatformLogo $action): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $action->remove();

        $this->toast('Logo eliminado, se usara el logo por defecto.', 'info');
    }

    public function isLogoStorageConfigured(): bool
    {
        return app(UpdatePlatformLogo::class)->isStorageConfigured();
    }

    public function render(): View
    {
        return view('livewire.platform.platform-settings-page', [
            'currentLogoUrl' => PlatformSetting::logoUrl(),
        ])->layout('layouts.platform');
    }
}
