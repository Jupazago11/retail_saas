<?php

namespace App\Livewire\Platform;

use App\Livewire\Concerns\InteractsWithToast;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PlatformSettingsPage extends Component
{
    use InteractsWithToast;

    public string $bankName    = '';
    public string $bankAccount = '';
    public string $bankHolder  = '';
    public string $bankType    = 'Cuenta de ahorros';
    public string $bankNit     = '';
    public string $planPrice   = '';
    public string $contactEmail = '';
    public string $contactPhone = '';
    public string $appName     = '';

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
        $this->appName      = PlatformSetting::get('app_name',      config('app.name', ''));
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
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        $this->toast('Parámetros guardados correctamente.');
    }

    public function render(): View
    {
        return view('livewire.platform.platform-settings-page')
            ->layout('layouts.platform');
    }
}
