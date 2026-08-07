<?php

namespace App\Livewire\Products;

use App\Actions\Products\ImportProductsFromCsv;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use InvalidArgumentException;

class ProductImportsPage extends Component
{
    use InteractsWithToast;
    use WithFileUploads;

    public UploadedFile|\Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null $importFile = null;

    public array $summary = [
        'created_count' => 0,
        'error_count' => 0,
        'errors' => [],
        'file_name' => null,
    ];

    public function mount(): void
    {
        $this->ensurePermission('products.create');

        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'imports')
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'imports.excel'),
            403,
            'El plan actual no tiene habilitadas las importaciones de catalogo.'
        );
    }

    public function import(ImportProductsFromCsv $importProductsFromCsv): void
    {
        $this->ensurePermission('products.create');

        $validated = $this->validate([
            'importFile' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        try {
            $this->summary = $importProductsFromCsv->handle(
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
        return view('livewire.products.product-imports-page')
            ->layout('layouts.app', [
                'header' => view('components.page-title', [
                    'title' => 'Importar productos',
                    'description' => 'Carga catalogo de productos por CSV validando referencias maestras, permisos y limites del plan.',
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
