<?php

namespace App\Livewire\Admin;

use App\Actions\Company\CreateBranch;
use App\Actions\Company\CreateCashRegister;
use App\Actions\Company\CreateWarehouse;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;

class CompanyStructurePage extends Component
{
    use InteractsWithToast;

    public ?string $activeModal = null;

    public string $branchName = '';
    public string $branchCode = '';

    public string $warehouseName     = '';
    public string $warehouseCode     = '';
    public ?int   $warehouseBranchId = null;

    public string $cajaName     = '';
    public string $cajaCode     = '';
    public ?int   $cajaBranchId = null;

    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
    }

    public function openModal(string $type): void
    {
        $this->resetValidation();
        $firstBranchId = $this->branches()->first()?->id;

        match ($type) {
            'warehouse' => [
                $this->warehouseName     = '',
                $this->warehouseCode     = '',
                $this->warehouseBranchId = $firstBranchId,
            ],
            'cash' => [
                $this->cajaName     = '',
                $this->cajaCode     = '',
                $this->cajaBranchId = $firstBranchId,
            ],
            default => [
                $this->branchName = '',
                $this->branchCode = '',
            ],
        };

        $this->activeModal = $type;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
        $this->resetValidation();
    }

    // ─── Crear ───────────────────────────────────────────────────────────────

    public function saveBranch(CreateBranch $createBranch): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'branchName' => ['required', 'string', 'max:255'],
            'branchCode' => ['required', 'string', 'max:80'],
        ]);

        try {
            $createBranch->handle($this->currentCompany(), [
                'name' => $validated['branchName'],
                'code' => strtoupper($validated['branchCode']),
            ], auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->addError('branchName', $e->getMessage());
            return;
        }

        $this->activeModal = null;
        $this->toast('Sucursal guardada correctamente.');
    }

    public function saveWarehouse(CreateWarehouse $createWarehouse): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'warehouseBranchId' => ['required', 'integer'],
            'warehouseName'     => ['required', 'string', 'max:255'],
            'warehouseCode'     => ['required', 'string', 'max:80'],
        ]);

        try {
            $createWarehouse->handle($this->currentCompany(), [
                'branch_id' => $validated['warehouseBranchId'],
                'name'      => $validated['warehouseName'],
                'code'      => strtoupper($validated['warehouseCode']),
            ], auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->addError('warehouseName', $e->getMessage());
            return;
        }

        $this->activeModal = null;
        $this->toast('Bodega guardada correctamente.');
    }

    public function saveCashRegister(CreateCashRegister $createCashRegister): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'cajaBranchId' => ['required', 'integer'],
            'cajaName'     => ['required', 'string', 'max:255'],
            'cajaCode'     => ['required', 'string', 'max:80'],
        ]);

        try {
            $createCashRegister->handle($this->currentCompany(), [
                'branch_id' => $validated['cajaBranchId'],
                'name'      => $validated['cajaName'],
                'code'      => strtoupper($validated['cajaCode']),
            ], auth()->user());
        } catch (InvalidArgumentException $e) {
            $this->addError('cajaName', $e->getMessage());
            return;
        }

        $this->activeModal = null;
        $this->toast('Caja guardada correctamente.');
    }

    // ─── Toggle estado ────────────────────────────────────────────────────────

    public function toggleStatus(string $type, int $id): void
    {
        $this->ensurePermission('settings.manage');

        $record = $this->findRecord($type, $id);

        if ($record->is_primary) {
            $this->toast('El registro principal no se puede desactivar.', 'warning');
            return;
        }

        $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
        $this->toast('Estado actualizado.');
    }

    // ─── Eliminar ─────────────────────────────────────────────────────────────

    public function deleteRecord(string $type, int $id): void
    {
        $this->ensurePermission('settings.manage');

        $record = $this->findRecord($type, $id);

        if ($record->is_primary) {
            $this->toast('El registro principal no puede eliminarse.', 'warning');
            return;
        }

        if (! $this->recordCanBeDeleted($record, $type)) {
            $this->toast('No se puede eliminar: tiene registros asociados.', 'warning');
            return;
        }

        $record->delete();
        $this->toast('Registro eliminado.');
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function warehouses(): Collection
    {
        return Warehouse::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('deleted_at')
            ->with('branch')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function cashRegisters(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('deleted_at')
            ->with('branch')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        $branches      = $this->branches();
        $warehouses    = $this->warehouses();
        $cashRegisters = $this->cashRegisters();

        // Precompute which IDs can be deleted to avoid N+1 in blade
        $deletableBranchIds      = $branches->filter(fn ($b) => $this->recordCanBeDeleted($b, 'branch'))->pluck('id')->all();
        $deletableWarehouseIds   = $warehouses->filter(fn ($w) => $this->recordCanBeDeleted($w, 'warehouse'))->pluck('id')->all();
        $deletableCashIds        = $cashRegisters->filter(fn ($c) => $this->recordCanBeDeleted($c, 'cash'))->pluck('id')->all();

        $planResolver     = app(CompanyPlanResolver::class);
        $company           = $this->currentCompany();
        $maxBranches       = $planResolver->limit($company, 'max_branches');
        $maxWarehouses     = $planResolver->limit($company, 'max_warehouses');
        $maxCashRegisters  = $planResolver->limit($company, 'max_cash_registers');

        return view('livewire.admin.company-structure-page', compact(
            'branches', 'warehouses', 'cashRegisters',
            'deletableBranchIds', 'deletableWarehouseIds', 'deletableCashIds',
            'maxBranches', 'maxWarehouses', 'maxCashRegisters',
        ))->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title'       => 'Estructura Operativa',
                'description' => 'Administra sucursales, bodegas y cajas de la empresa.',
            ]),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function findRecord(string $type, int $id): Branch|Warehouse|CashRegister
    {
        $record = match ($type) {
            'branch'    => Branch::where('company_id', $this->currentCompany()->id)->findOrFail($id),
            'warehouse' => Warehouse::where('company_id', $this->currentCompany()->id)->findOrFail($id),
            'cash'      => CashRegister::where('company_id', $this->currentCompany()->id)->findOrFail($id),
        };

        return $record;
    }

    protected function recordCanBeDeleted(Branch|Warehouse|CashRegister $record, string $type): bool
    {
        if ($record->is_primary) {
            return false;
        }

        return match ($type) {
            'branch' => $record->warehouses()->whereNull('deleted_at')->doesntExist()
                && $record->cashRegisters()->whereNull('deleted_at')->doesntExist()
                && $record->sales()->doesntExist()
                && $record->purchases()->doesntExist()
                && $record->inventoryAdjustments()->doesntExist()
                && $record->cashSessions()->doesntExist(),

            'warehouse' => $record->sales()->doesntExist()
                && $record->purchases()->doesntExist()
                && $record->inventoryAdjustments()->doesntExist()
                && $record->inventoryMovements()->doesntExist()
                && $record->inventoryBalances()->doesntExist(),

            'cash' => $record->sales()->doesntExist()
                && $record->cashSessions()->doesntExist()
                && $record->frozenSales()->doesntExist(),

            default => false,
        };
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
        );
    }
}
