<?php

namespace App\Livewire\Purchases;

use App\Actions\Suppliers\ImportSuppliersFromCsv;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class SupplierImportsPage extends Component
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
        $this->ensurePermission('suppliers.manage');

        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'imports')
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'imports.excel'),
            403,
            'El plan actual no tiene habilitadas las importaciones operativas.'
        );
    }

    public function import(ImportSuppliersFromCsv $importSuppliersFromCsv): void
    {
        $this->ensurePermission('suppliers.manage');

        $validated = $this->validate([
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        try {
            $this->summary = $importSuppliersFromCsv->handle(
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
        return view('livewire.purchases.supplier-imports-page')
            ->layout('layouts.app', [
                'header' => view('components.page-title', [
                    'title' => 'Importar proveedores',
                    'description' => 'Carga el maestro de proveedores por CSV con validacion parcial por fila.',
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
