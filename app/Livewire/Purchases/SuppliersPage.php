<?php

namespace App\Livewire\Purchases;

use App\Actions\Suppliers\CreateSupplier;
use App\Actions\Suppliers\UpdateSupplier;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Supplier;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class SuppliersPage extends Component
{
    use InteractsWithToast;

    public ?int $editingSupplierId = null;
    public string $documentType = '';
    public string $documentNumber = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $phone = '';
    public string $email = '';
    public string $status = 'active';
    public string $paymentTermDays = '';
    public string $notes = '';
    public string $search = '';
    public string $statusFilter = '';

    public bool $showModal = false;

    public function mount(): void
    {
        $this->ensurePermission('suppliers.view');
    }

    public function saveSupplier(CreateSupplier $createSupplier, UpdateSupplier $updateSupplier): void
    {
        $this->ensurePermission('suppliers.manage');

        $validated = $this->validate([
            'documentType' => ['nullable', 'string', 'max:30'],
            'documentNumber' => ['nullable', 'string', 'max:50'],
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
            'paymentTermDays' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'document_type' => $this->blankToNull($validated['documentType']),
            'document_number' => $this->blankToNull($validated['documentNumber']),
            'first_name' => trim($validated['firstName']),
            'last_name' => $this->blankToNull($validated['lastName']),
            'phone' => $this->blankToNull($validated['phone']),
            'email' => $this->blankToNull($validated['email']),
            'status' => $validated['status'],
            'payment_term_days' => $this->blankToNull($validated['paymentTermDays']),
            'notes' => $this->blankToNull($validated['notes']),
        ];

        try {
            if ($this->editingSupplierId) {
                $supplier = $this->suppliersQuery()->findOrFail($this->editingSupplierId);
                $updateSupplier->handle($this->currentCompany(), $supplier, $payload);
            } else {
                $createSupplier->handle($this->currentCompany(), $payload);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('firstName', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->resetSupplierForm();
        $this->toast('Proveedor guardado correctamente.');
    }

    public function editSupplier(int $supplierId): void
    {
        $this->ensurePermission('suppliers.manage');

        $supplier = $this->suppliersQuery()->with('person')->findOrFail($supplierId);

        $this->editingSupplierId = $supplier->id;
        $this->documentType = $supplier->person?->document_type ?? '';
        $this->documentNumber = $supplier->person?->document_number ?? '';
        $this->firstName = $supplier->person?->first_name ?? '';
        $this->lastName = $supplier->person?->last_name ?? '';
        $this->phone = $supplier->person?->phone ?? '';
        $this->email = $supplier->person?->email ?? '';
        $this->status = $supplier->status;
        $this->paymentTermDays = $supplier->payment_term_days !== null ? (string) $supplier->payment_term_days : '';
        $this->notes = $supplier->notes ?? '';
        $this->showModal = true;
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['', RecordStatus::Active->value, RecordStatus::Inactive->value], true)) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function toggleSupplierStatus(int $supplierId): void
    {
        $this->ensurePermission('suppliers.manage');

        $supplier = $this->suppliersQuery()->findOrFail($supplierId);

        $supplier->update([
            'status' => $supplier->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $supplier->status === RecordStatus::Active->value
                ? 'Proveedor activado correctamente.'
                : 'Proveedor desactivado correctamente.',
            'info'
        );
    }

    public function resetSupplierForm(): void
    {
        $this->reset(
            'editingSupplierId',
            'documentType',
            'documentNumber',
            'firstName',
            'lastName',
            'phone',
            'email',
            'paymentTermDays',
            'notes'
        );

        $this->status = RecordStatus::Active->value;
        $this->resetValidation();
    }

    public function openModal(): void
    {
        $this->resetSupplierForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetSupplierForm();
    }

    public function suppliers(): Collection
    {
        return $this->suppliersQuery()
            ->with('person')
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereHas('person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search)
                                ->orWhereLike('phone', $search)
                                ->orWhereLike('email', $search);
                        })
                        ->orWhereLike('notes', $search);
                });
            })
            ->orderByDesc('credit_balance')
            ->orderByDesc('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.purchases.suppliers-page', [
            'suppliers' => $this->suppliers(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Proveedores',
                'description' => 'Mantiene el maestro comercial, plazos y saldo a favor disponible por proveedor.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function suppliersQuery()
    {
        return Supplier::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
