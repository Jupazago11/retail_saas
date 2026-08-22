<?php

namespace App\Livewire\Credit;

use App\Actions\Credit\RegisterCreditPayment;
use App\Actions\Customers\CreateCustomer;
use App\Actions\Customers\EnableCustomerCredit;
use App\Enums\CashSessionStatus;
use App\Enums\CreditMovementType;
use App\Enums\SaleStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class CreditAccountsPage extends Component
{
    use InteractsWithToast;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $selectedCustomerId = null;

    public bool $showDetailModal = false;

    public bool $showEditModal = false;

    public string $editFirstName = '';

    public string $editLastName = '';

    public string $editPhone = '';

    public string $editEmail = '';

    public string $editCreditLimit = '';

    public string $editPaymentTermDays = '';

    public bool $showPaymentForm = false;

    public ?int $cashSessionId = null;

    public string $paymentAmount = '';

    public string $paymentMethodCode = 'cash';

    public string $paymentReference = '';

    public bool $showAddCustomerModal = false;

    public string $addCustomerSearch = '';

    public ?int $addCustomerSelectedId = null;

    public string $addCustomerCreditLimit = '';

    public bool $addCustomerCreatingNew = false;

    public string $newCustomerDocumentType = '';

    public string $newCustomerDocumentNumber = '';

    public string $newCustomerFirstName = '';

    public string $newCustomerLastName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public function mount(): void
    {
        $this->ensureCreditAccess();
    }

    public function setStatusFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'current', 'overdue'], true)) {
            return;
        }

        $this->statusFilter = $filter;
    }

    public function accounts(): Collection
    {
        return $this->accountsQuery()
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereHas('customer.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        });
                });
            })
            ->orderByDesc('balance_due')
            ->orderByDesc('id')
            ->get()
            ->filter(function (CreditAccount $account) {
                if ($this->statusFilter === 'all') {
                    return true;
                }

                $mora = $this->moraStatus($account);
                $isOverdue = $mora !== null && $mora['color'] !== 'emerald';

                return $this->statusFilter === 'overdue' ? $isOverdue : ! $isOverdue;
            })
            ->values();
    }

    public function summaryCards(): array
    {
        $accounts = $this->accountsQuery()->get();

        return [
            'accounts_count' => $accounts->count(),
            'balance_due_total' => Money::format((float) $accounts->sum(fn (CreditAccount $account) => (float) $account->balance_due)),
            'available_credit_total' => Money::format((float) $accounts->sum(fn (CreditAccount $account) => (float) $account->available_credit)),
            'overdue_accounts_count' => $accounts->filter(function (CreditAccount $account) {
                $mora = $this->moraStatus($account);

                return $mora !== null && $mora['color'] !== 'emerald';
            })->count(),
        ];
    }

    /**
     * Semaforo por tiempo de mora de la cuenta: verde = al dia (sin saldo o
     * sin facturas vencidas), amarillo = mora reciente (<=30 dias), rojo =
     * mora prolongada (>30 dias). Se ancla a la factura vencida mas antigua
     * de la cuenta porque los abonos ya no se enlazan a una venta puntual
     * (se aplican contra el saldo general), asi que no hay forma exacta de
     * saber cual factura especifica sigue sin pagar.
     *
     * @return array{color: string, label: string}|null
     */
    public function moraStatus(CreditAccount $account): ?array
    {
        if (bccomp((string) $account->balance_due, '0.00', 2) <= 0) {
            return null;
        }

        $oldestDueAt = Sale::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('credit_account_id', $account->id)
            ->where('status', '!=', SaleStatus::Cancelled->value)
            ->whereNotNull('credit_due_at')
            ->orderBy('credit_due_at')
            ->value('credit_due_at');

        if ($oldestDueAt === null) {
            return ['color' => 'emerald', 'label' => 'Al dia'];
        }

        $today = now()->startOfDay();
        $dueDate = $oldestDueAt->copy()->startOfDay();
        $daysUntilDue = (int) $today->diffInDays($dueDate);

        if ($daysUntilDue >= 0) {
            return ['color' => 'emerald', 'label' => 'Al dia'];
        }

        $daysOverdue = abs($daysUntilDue);

        return [
            'color' => $daysOverdue <= 30 ? 'amber' : 'rose',
            'label' => 'En mora '.$daysOverdue.' '.Str::plural('dia', $daysOverdue),
        ];
    }

    public function canViewCredit(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('credit.view') ?? false;
    }

    public function canManageCredit(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('credit.manage') ?? false;
    }

    public function customersWithoutCreditOptions(): array
    {
        return Customer::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereDoesntHave('creditAccount')
            ->with('person')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->person?->full_name ?: 'Cliente #'.$customer->id,
                'document' => $customer->person?->document_number ?? '',
            ])
            ->values()
            ->all();
    }

    public function openAddCustomerModal(): void
    {
        $this->ensurePermission('credit.manage');

        $this->showAddCustomerModal = true;
        $this->addCustomerSearch = '';
        $this->addCustomerSelectedId = null;
        $this->addCustomerCreditLimit = '';
        $this->cancelCreatingCustomerForCredit();
        $this->resetValidation();
    }

    public function closeAddCustomerModal(): void
    {
        $this->showAddCustomerModal = false;
        $this->addCustomerSearch = '';
        $this->addCustomerSelectedId = null;
        $this->addCustomerCreditLimit = '';
        $this->cancelCreatingCustomerForCredit();
        $this->resetValidation();
    }

    public function selectCustomerForCredit(int $customerId): void
    {
        $this->addCustomerSelectedId = $customerId;
    }

    public function enableCredit(EnableCustomerCredit $enableCustomerCredit): void
    {
        $this->ensurePermission('credit.manage');

        $validated = $this->validate([
            'addCustomerSelectedId' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)),
            ],
            'addCustomerCreditLimit' => ['required', 'numeric', 'min:0'],
        ]);

        $customer = Customer::query()
            ->where('company_id', $this->currentCompany()->id)
            ->findOrFail((int) $validated['addCustomerSelectedId']);

        try {
            $enableCustomerCredit->handle($this->currentCompany(), $customer, (string) $validated['addCustomerCreditLimit']);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->closeAddCustomerModal();
        $this->toast('Credito habilitado correctamente.');
    }

    /**
     * El buscador de "Agregar cliente a credito" solo ofrece clientes que ya
     * existen en el sistema (por ejemplo, creados al vuelo desde una venta).
     * Este flujo cubre el caso de un cliente que todavia no existe: crea el
     * registro completo (con mas datos de los que pide la venta rapida) y de
     * una vez le habilita cupo de credito.
     */
    public function startCreatingCustomerForCredit(): void
    {
        $this->ensurePermission('credit.manage');

        $this->addCustomerCreatingNew = true;
        $this->addCustomerSelectedId = null;
        $this->newCustomerDocumentType = '';
        $this->newCustomerDocumentNumber = '';
        $this->newCustomerFirstName = '';
        $this->newCustomerLastName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->addCustomerCreditLimit = '';
        $this->resetValidation();
    }

    public function cancelCreatingCustomerForCredit(): void
    {
        $this->addCustomerCreatingNew = false;
        $this->newCustomerDocumentType = '';
        $this->newCustomerDocumentNumber = '';
        $this->newCustomerFirstName = '';
        $this->newCustomerLastName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->resetValidation();
    }

    public function createCustomerForCredit(CreateCustomer $createCustomer): void
    {
        $this->ensurePermission('credit.manage');

        $validated = $this->validate([
            'newCustomerFirstName' => ['required', 'string', 'max:100'],
            'newCustomerLastName' => ['nullable', 'string', 'max:100'],
            'newCustomerDocumentType' => ['nullable', 'string', 'max:30'],
            'newCustomerDocumentNumber' => ['nullable', 'string', 'max:50'],
            'newCustomerPhone' => ['nullable', 'string', 'max:30'],
            'newCustomerEmail' => ['nullable', 'email', 'max:150'],
            'addCustomerCreditLimit' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $createCustomer->handle($this->currentCompany(), [
                'first_name' => trim($validated['newCustomerFirstName']),
                'last_name' => $this->blankToNull($validated['newCustomerLastName']),
                'document_type' => $this->blankToNull($validated['newCustomerDocumentType']),
                'document_number' => $this->blankToNull($validated['newCustomerDocumentNumber']),
                'phone' => $this->blankToNull($validated['newCustomerPhone']),
                'email' => $this->blankToNull($validated['newCustomerEmail']),
                'credit_enabled' => true,
                'credit_limit' => $validated['addCustomerCreditLimit'],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->closeAddCustomerModal();
        $this->toast('Cliente creado y credito habilitado correctamente.');
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->showDetailModal = true;
        $this->cancelRegisteringPayment();
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedCustomerId = null;
        $this->cancelRegisteringPayment();
    }

    public function editCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->openEditModal();
    }

    public function openEditModal(): void
    {
        $account = $this->accounts()->firstWhere('customer_id', $this->selectedCustomerId);
        if (! $account) {
            return;
        }
        $person = $account->customer?->person;
        $this->editFirstName = $person?->first_name ?? '';
        $this->editLastName = $person?->last_name ?? '';
        $this->editPhone = $person?->phone ?? '';
        $this->editEmail = $person?->email ?? '';
        $this->editCreditLimit = (string) ($account->credit_limit ?? '');
        $this->editPaymentTermDays = $account->payment_term_days !== null ? (string) $account->payment_term_days : '';
        $this->showEditModal = true;
        $this->resetValidation();
    }

    public function defaultTermDays(): int
    {
        return (int) app(CompanySettings::class)->get($this->currentCompany(), 'credit', 'default_term_days');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->resetValidation();
    }

    public function saveCustomerEdit(): void
    {
        $this->ensurePermission('credit.manage');

        $validated = $this->validate([
            'editFirstName' => ['required', 'string', 'max:100'],
            'editLastName' => ['nullable', 'string', 'max:100'],
            'editPhone' => ['nullable', 'string', 'max:30'],
            'editEmail' => ['nullable', 'email', 'max:150'],
            'editCreditLimit' => ['required', 'numeric', 'min:0'],
            'editPaymentTermDays' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $account = $this->accounts()->firstWhere('customer_id', $this->selectedCustomerId);
        if (! $account) {
            return;
        }

        $person = $account->customer?->person;
        if ($person) {
            $person->update([
                'first_name' => trim($validated['editFirstName']),
                'last_name' => trim($validated['editLastName'] ?? ''),
                'phone' => $this->blankToNull($validated['editPhone']),
                'email' => $this->blankToNull($validated['editEmail']),
            ]);
        }

        $newLimit = (string) $validated['editCreditLimit'];
        $newTermDays = $this->blankToNull($validated['editPaymentTermDays'] ?? null);
        $newTermDays = $newTermDays !== null ? (int) $newTermDays : null;

        if (bccomp($newLimit, (string) $account->credit_limit, 2) !== 0 || $newTermDays !== $account->payment_term_days) {
            $account->update([
                'credit_limit' => $newLimit,
                'available_credit' => bcsub($newLimit, (string) $account->balance_due, 2),
                'payment_term_days' => $newTermDays,
            ]);
        }

        $this->closeEditModal();
        $this->toast('Cliente actualizado correctamente.');
    }

    public function selectedCustomerSales(): Collection
    {
        if (! $this->selectedCustomerId) {
            return new Collection;
        }

        return $this->creditSalesQuery()
            ->where('customer_id', $this->selectedCustomerId)
            ->with([
                'payments',
                'creditMovements',
                'cashRegister',
            ])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get();
    }

    public function startRegisteringPayment(): void
    {
        $this->ensurePermission('credit.manage');

        $account = $this->selectedAccount();

        if (! $account || bccomp((string) $account->balance_due, '0.00', 2) !== 1) {
            $this->toast('Este cliente ya no tiene saldo pendiente.', 'warning');

            return;
        }

        $this->showPaymentForm = true;
        $this->paymentAmount = (string) (int) round((float) $account->balance_due);
        $this->paymentMethodCode = 'cash';
        $this->paymentReference = '';
        $this->cashSessionId = $this->openCashSessions()->first()?->id;
        $this->resetValidation();
    }

    public function cancelRegisteringPayment(): void
    {
        $this->showPaymentForm = false;
        $this->cashSessionId = null;
        $this->paymentAmount = '';
        $this->paymentMethodCode = 'cash';
        $this->paymentReference = '';
        $this->resetValidation();
    }

    public function registerPayment(RegisterCreditPayment $registerCreditPayment): void
    {
        $this->ensurePermission('credit.manage');

        $validated = $this->validate([
            'cashSessionId' => [
                'nullable',
                Rule::exists('cash_sessions', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->currentCompany()->id)
                    ->where('status', CashSessionStatus::Open->value)),
            ],
            'paymentAmount' => ['required', 'numeric', 'gt:0'],
            'paymentMethodCode' => ['required', 'string', 'max:50'],
            'paymentReference' => ['nullable', 'string', 'max:120'],
        ]);

        $account = $this->selectedAccount();

        if (! $account) {
            return;
        }

        try {
            $registerCreditPayment->handle($this->currentCompany(), $account, [
                'cash_session_id' => $validated['cashSessionId'] ? (int) $validated['cashSessionId'] : null,
                'received_by' => auth()->id(),
                'payment_method_code' => trim($validated['paymentMethodCode']),
                'amount' => $validated['paymentAmount'],
                'reference' => $this->blankToNull($validated['paymentReference']),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('paymentAmount', $exception->getMessage());

            return;
        }

        $this->cancelRegisteringPayment();
        $this->toast('Abono registrado correctamente.');
    }

    public function openCashSessions(): Collection
    {
        return CashSession::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', CashSessionStatus::Open->value)
            ->with(['cashRegister', 'opener'])
            ->orderByDesc('opened_at')
            ->get();
    }

    public function movementLabel(string $movementType): string
    {
        return match ($movementType) {
            CreditMovementType::SaleCharge->value => 'Cargo de venta',
            CreditMovementType::Payment->value => 'Abono',
            CreditMovementType::SaleReturnAdjustment->value => 'Ajuste por devolucion',
            CreditMovementType::SaleCancellationAdjustment->value => 'Ajuste por anulacion',
            default => $movementType,
        };
    }

    public function render(): View
    {
        return view('livewire.credit.credit-accounts-page', [
            'accounts' => $this->accounts(),
            'selectedAccount' => $this->selectedAccount(),
            'selectedCustomerSales' => $this->selectedCustomerSales(),
            'openCashSessions' => $this->showPaymentForm ? $this->openCashSessions() : new Collection,
            'statusCards' => $this->summaryCards(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Credito',
                'description' => 'Consulta cartera por cliente y venta, identifica mora y registra abonos sobre documentos a credito.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function creditSalesQuery()
    {
        return Sale::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNotNull('credit_account_id');
    }

    protected function accountsQuery(): Builder
    {
        return CreditAccount::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'customer.person',
                'movements' => fn ($query) => $query
                    ->with('sale')
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id'),
            ]);
    }

    protected function selectedAccount(): ?CreditAccount
    {
        if (! $this->selectedCustomerId) {
            return null;
        }

        return CreditAccount::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('customer_id', $this->selectedCustomerId)
            ->first();
    }

    protected function ensureCreditAccess(): void
    {
        abort_unless(
            app(CompanyPlanResolver::class)->hasModule($this->currentCompany(), 'credit')
            && app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'credit.enabled'),
            403,
            'El plan actual no tiene habilitado el modulo de credito.'
        );

        abort_unless(
            $this->canViewCredit() || $this->canManageCredit(),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
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
