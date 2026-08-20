<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\HasResponsivePageSize;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Customer;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersPage extends Component
{
    use HasResponsivePageSize, InteractsWithToast, WithPagination;

    public int $perPage = 10;

    public ?int $editingCustomerId = null;
    public string $documentType = '';
    public string $documentNumber = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $phone = '';
    public string $email = '';
    public string $status = 'active';
    public string $search = '';
    public string $statusFilter = '';

    public bool $showModal = false;

    public function mount(): void
    {
        $this->ensurePermission('customers.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        if (! in_array($status, ['', RecordStatus::Active->value, RecordStatus::Inactive->value], true)) {
            return;
        }

        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function saveCustomer(CreateCustomer $createCustomer, UpdateCustomer $updateCustomer): void
    {
        $this->ensurePermission('customers.manage');

        $validated = $this->validate([
            'documentType' => ['nullable', 'string', 'max:30'],
            'documentNumber' => ['nullable', 'string', 'max:50'],
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in([RecordStatus::Active->value, RecordStatus::Inactive->value])],
        ]);

        $payload = [
            'document_type' => $this->blankToNull($validated['documentType']),
            'document_number' => $this->blankToNull($validated['documentNumber']),
            'first_name' => trim($validated['firstName']),
            'last_name' => $this->blankToNull($validated['lastName']),
            'phone' => $this->blankToNull($validated['phone']),
            'email' => $this->blankToNull($validated['email']),
            'status' => $validated['status'],
        ];

        try {
            if ($this->editingCustomerId) {
                $customer = $this->customersQuery()->findOrFail($this->editingCustomerId);
                $updateCustomer->handle($this->currentCompany(), $customer, $payload);
            } else {
                $createCustomer->handle($this->currentCompany(), $payload);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('firstName', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->resetCustomerForm();
        $this->toast('Cliente guardado correctamente.');
    }

    public function editCustomer(int $customerId): void
    {
        $this->ensurePermission('customers.manage');

        $customer = $this->customersQuery()->with('person')->findOrFail($customerId);

        $this->editingCustomerId = $customer->id;
        $this->documentType = $customer->person?->document_type ?? '';
        $this->documentNumber = $customer->person?->document_number ?? '';
        $this->firstName = $customer->person?->first_name ?? '';
        $this->lastName = $customer->person?->last_name ?? '';
        $this->phone = $customer->person?->phone ?? '';
        $this->email = $customer->person?->email ?? '';
        $this->status = $customer->status;
        $this->showModal = true;
    }

    public function toggleCustomerStatus(int $customerId): void
    {
        $this->ensurePermission('customers.manage');

        $customer = $this->customersQuery()->findOrFail($customerId);

        $customer->update([
            'status' => $customer->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast(
            $customer->status === RecordStatus::Active->value
                ? 'Cliente activado correctamente.'
                : 'Cliente desactivado correctamente.',
            'info'
        );
    }

    public function resetCustomerForm(): void
    {
        $this->reset(
            'editingCustomerId',
            'documentType',
            'documentNumber',
            'firstName',
            'lastName',
            'phone',
            'email'
        );

        $this->status = RecordStatus::Active->value;
        $this->resetValidation();
    }

    public function openModal(): void
    {
        $this->resetCustomerForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetCustomerForm();
    }

    public function customers(): LengthAwarePaginator
    {
        return $this->customersQuery()
            ->with(['person', 'creditAccount', 'loyaltyAccount'])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->whereHas('person', function (Builder $personQuery) use ($search) {
                    $personQuery
                        ->whereLike('first_name', $search)
                        ->orWhereLike('last_name', $search)
                        ->orWhereLike('document_number', $search)
                        ->orWhereLike('phone', $search)
                        ->orWhereLike('email', $search);
                });
            })
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.customers.customers-page', [
            'customers' => $this->customers(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Clientes',
                'description' => 'Mantiene el maestro general de clientes, el mismo que se usa al facturar, en credito y en fidelizacion.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function customersQuery()
    {
        return Customer::query()
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
