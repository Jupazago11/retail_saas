<?php

namespace App\Livewire\Credit;

use App\Actions\Credit\RegisterCreditPayment;
use App\Actions\Customers\EnableCustomerCredit;
use App\Enums\CashSessionStatus;
use App\Enums\CreditMovementType;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CreditAccount;
use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class CreditAccountsPage extends Component
{
    use InteractsWithToast;

    public string $search = '';
    public bool $overdueOnly = false;
    public ?int $selectedCustomerId = null;
    public bool $showDetailModal = false;
    public bool $showEditModal = false;

    public string $editFirstName = '';
    public string $editLastName = '';
    public string $editPhone = '';
    public string $editEmail = '';
    public string $editCreditLimit = '';

    public ?int $paymentSaleId = null;
    public ?int $cashSessionId = null;
    public string $paymentAmount = '';
    public string $paymentMethodCode = 'cash';
    public string $paymentReference = '';

    public bool $showAddCustomerModal = false;
    public string $addCustomerSearch = '';
    public ?int $addCustomerSelectedId = null;
    public string $addCustomerCreditLimit = '';

    public function mount(): void
    {
        $this->ensureCreditAccess();
    }

    public function accounts(): Collection
    {
        return CreditAccount::query()
            ->where('company_id', $this->currentCompany()->id)
            ->with([
                'customer.person',
                'movements' => fn ($query) => $query
                    ->with('sale')
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id'),
            ])
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

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
            ->get();
    }

    public function creditSales(): Collection
    {
        return Sale::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('sale_type', 'credit')
            ->with([
                'branch',
                'cashRegister',
                'customer.person',
                'user',
                'payments',
                'creditAccount',
                'creditMovements',
            ])
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('id', $search)
                        ->orWhereLike('document_number', $search)
                        ->orWhereHas('customer.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        })
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->whereLike('name', $search));
                });
            })
            ->when($this->overdueOnly, fn (Builder $query) => $query->whereNotNull('credit_due_at')->where('credit_due_at', '<', now()))
            ->orderByRaw("case when credit_due_at is null then 1 else 0 end")
            ->orderBy('credit_due_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Sale $sale) => ! $this->overdueOnly || bccomp($this->outstandingForSale($sale), '0.00', 2) === 1)
            ->values();
    }

    public function summaryCards(): array
    {
        $accounts = $this->accounts();
        $sales = $this->creditSales();

        return [
            'accounts_count' => $accounts->count(),
            'balance_due_total' => \App\Support\Money::format((float) $accounts->sum(fn (CreditAccount $account) => (float) $account->balance_due)),
            'available_credit_total' => \App\Support\Money::format((float) $accounts->sum(fn (CreditAccount $account) => (float) $account->available_credit)),
            'overdue_sales_count' => $sales->filter(fn (Sale $sale) => $sale->credit_due_at && $sale->credit_due_at->isPast() && bccomp($this->outstandingForSale($sale), '0.00', 2) === 1)->count(),
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
        $this->resetValidation();
    }

    public function closeAddCustomerModal(): void
    {
        $this->showAddCustomerModal = false;
        $this->addCustomerSearch = '';
        $this->addCustomerSelectedId = null;
        $this->addCustomerCreditLimit = '';
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
        $this->editLastName  = $person?->last_name  ?? '';
        $this->editPhone     = $person?->phone      ?? '';
        $this->editEmail     = $person?->email      ?? '';
        $this->editCreditLimit = (string) ($account->credit_limit ?? '');
        $this->showEditModal = true;
        $this->resetValidation();
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
            'editFirstName'   => ['required', 'string', 'max:100'],
            'editLastName'    => ['nullable', 'string', 'max:100'],
            'editPhone'       => ['nullable', 'string', 'max:30'],
            'editEmail'       => ['nullable', 'email', 'max:150'],
            'editCreditLimit' => ['required', 'numeric', 'min:0'],
        ]);

        $account = $this->accounts()->firstWhere('customer_id', $this->selectedCustomerId);
        if (! $account) {
            return;
        }

        $person = $account->customer?->person;
        if ($person) {
            $person->update([
                'first_name' => trim($validated['editFirstName']),
                'last_name'  => trim($validated['editLastName'] ?? ''),
                'phone'      => $this->blankToNull($validated['editPhone']),
                'email'      => $this->blankToNull($validated['editEmail']),
            ]);
        }

        $newLimit = (string) $validated['editCreditLimit'];
        if (bccomp($newLimit, (string) $account->credit_limit, 2) !== 0) {
            $account->update([
                'credit_limit'     => $newLimit,
                'available_credit' => bcsub($newLimit, (string) $account->balance_due, 2),
            ]);
        }

        $this->closeEditModal();
        $this->toast('Cliente actualizado correctamente.');
    }

    public function selectedCustomerSales(): Collection
    {
        if (! $this->selectedCustomerId) {
            return new Collection();
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

    public function startRegisteringPayment(int $saleId): void
    {
        $this->ensurePermission('credit.manage');

        $sale = $this->creditSalesQuery()
            ->with(['creditMovements'])
            ->findOrFail($saleId);

        $outstanding = $this->outstandingForSale($sale);

        if (bccomp($outstanding, '0.00', 2) !== 1) {
            $this->toast('La venta ya no tiene saldo pendiente.', 'warning');

            return;
        }

        $this->paymentSaleId = $sale->id;
        $this->paymentAmount = $outstanding;
        $this->paymentMethodCode = 'cash';
        $this->paymentReference = '';
        $this->cashSessionId = $this->openCashSessionsForSale($sale)->first()?->id;
        $this->resetValidation();
    }

    public function cancelRegisteringPayment(): void
    {
        $this->paymentSaleId = null;
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
            'paymentSaleId' => [
                'required',
                Rule::exists('sales', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)->where('sale_type', 'credit')),
            ],
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

        $sale = $this->creditSalesQuery()
            ->with(['creditMovements'])
            ->findOrFail((int) $validated['paymentSaleId']);

        if ($validated['cashSessionId']) {
            $allowedCashSessionIds = $this->openCashSessionsForSale($sale)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! in_array((int) $validated['cashSessionId'], $allowedCashSessionIds, true)) {
                $this->addError('cashSessionId', 'La sesion de caja no corresponde al contexto operativo de la venta.');

                return;
            }
        }

        try {
            $registerCreditPayment->handle($this->currentCompany(), $sale, [
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

    public function openCashSessionsForSale(Sale $sale): Collection
    {
        return CashSession::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $sale->branch_id)
            ->where('status', CashSessionStatus::Open->value)
            ->when($sale->cash_register_id, fn ($query) => $query->where('cash_register_id', $sale->cash_register_id))
            ->with(['cashRegister', 'opener'])
            ->orderByDesc('opened_at')
            ->get();
    }

    public function outstandingForSale(Sale $sale): string
    {
        $sale->loadMissing('creditMovements');

        return $sale->creditMovements->reduce(function (string $carry, CreditMovement $movement) {
            return $this->movementDirection($movement->movement_type) === 1
                ? bcadd($carry, (string) $movement->amount, 2)
                : bcsub($carry, (string) $movement->amount, 2);
        }, '0.00');
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
            'accounts'             => $this->accounts(),
            'selectedCustomerSales' => $this->selectedCustomerSales(),
            'statusCards'          => $this->summaryCards(),
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
            ->where('sale_type', 'credit');
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

    protected function movementDirection(string $movementType): int
    {
        return match ($movementType) {
            CreditMovementType::SaleCharge->value => 1,
            CreditMovementType::Payment->value,
            CreditMovementType::SaleReturnAdjustment->value,
            CreditMovementType::SaleCancellationAdjustment->value => -1,
            default => 0,
        };
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
