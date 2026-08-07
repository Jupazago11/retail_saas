<?php

namespace App\Livewire\Credit;

use App\Actions\Customers\ImportCustomersFromCsv;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CustomerImportsPage extends Component
{
    use InteractsWithToast;
    use WithFileUploads;

    public UploadedFile|\Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $importFile = null;

    public array $summary = [
        'file_name' => null,
        'created_count' => 0,
        'error_count' => 0,
        'errors' => [],
    ];

    public function mount(): void
    {
        $this->ensurePermission('credit.manage');

        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'imports')
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'imports.excel'),
            403,
            'El plan actual no tiene habilitadas las importaciones operativas.'
        );
    }

    public function import(ImportCustomersFromCsv $importCustomersFromCsv): void
    {
        $this->ensurePermission('credit.manage');

        $validated = $this->validate([
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        try {
            $this->summary = $importCustomersFromCsv->handle(
                $this->currentCompany(),
                $validated['importFile'],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('importFile', $exception->getMessage());

            return;
        }

        $this->reset('importFile');
        $this->resetValidation('importFile');

        $this->toast(
            'Importacion procesada: '.$this->summary['created_count'].' creados y '.$this->summary['error_count'].' con error.',
            $this->summary['error_count'] > 0 ? 'warning' : 'success'
        );
    }

    public function render(): View
    {
        return view('livewire.credit.customer-imports-page')
            ->layout('layouts.app', [
                'header' => view('components.page-title', [
                    'title' => 'Importar clientes',
                    'description' => 'Carga clientes por CSV con soporte opcional para credito y fidelizacion desde el mismo lote.',
                ]),
            ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
