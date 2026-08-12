<?php

namespace App\Livewire\Company;

use App\Actions\Companies\CreateCompany;
use App\Livewire\Concerns\InteractsWithToast;
use App\Mail\NewAccountRegisteredMail;
use App\Models\PlatformSetting;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class SelectCompanyPage extends Component
{
    use InteractsWithToast;

    public string $legalName = '';
    public string $displayName = '';
    public string $taxId = '';

    public function switchCompany(int $companyId, CurrentCompany $currentCompany)
    {
        $company = $this->companies()->firstWhere('id', $companyId);

        abort_unless($company, 403);

        $currentCompany->setForUser(Auth::user(), $company);
        $this->flashToast('Empresa activa actualizada.');

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function createCompany(CreateCompany $createCompany, CurrentCompany $currentCompany)
    {
        $validated = $this->validate([
            'legalName' => ['required', 'string', 'max:255'],
            'displayName' => ['nullable', 'string', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $company = $createCompany->handle(Auth::user(), [
                'legal_name' => $validated['legalName'],
                'display_name' => $validated['displayName'] ?: $validated['legalName'],
                'tax_id' => $validated['taxId'] ?: null,
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('legalName', $exception->getMessage());

            return;
        }

        $currentCompany->setForUser(Auth::user(), $company);
        $this->flashToast('Empresa creada y seleccionada correctamente.');

        // Aviso al administrador de la plataforma, solo cuando esta es la
        // primera empresa del usuario (recien registrado): antes de este
        // punto no existia un plan que informar.
        if (Auth::user()->companies()->count() === 1) {
            $planName = $company->subscriptions()->with('plan')->latest('id')->first()?->plan?->name ?? 'Sin plan';

            try {
                Mail::to(PlatformSetting::ownerNotificationEmail())
                    ->send(new NewAccountRegisteredMail(Auth::user(), $planName));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el aviso de nueva cuenta al administrador.', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);
            }
        }

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function companies(): Collection
    {
        return Auth::user()
            ->companies()
            ->orderBy('display_name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.company.select-company-page', [
            'companies' => $this->companies(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Empresa activa',
                'description' => 'Selecciona una empresa vinculada o crea la primera para continuar.',
            ]),
        ]);
    }
}
