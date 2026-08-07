<?php

namespace App\Livewire\Cash;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Company;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class CashSessionsPage extends Component
{
    use InteractsWithToast;

    public ?int $branchId = null;
    public ?int $cashRegisterId = null;
    public string $openingAmount = '';

    public ?int $closingSessionId = null;
    public string $closingCountedAmount = '';

    public string $search = '';
    public string $statusFilter = '';
    public ?int $branchFilterId = null;
    public ?int $cashRegisterFilterId = null;

    public function mount(): void
    {
        $this->ensureCashAccess();
        $this->resetOpenForm();
    }

    public function updatedBranchId($value): void
    {
        $value = $value ? (int) $value : null;

        $cashRegisterIds = $this->cashRegisters()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value === null || ! in_array((int) $this->cashRegisterId, $cashRegisterIds, true)) {
            $this->cashRegisterId = $cashRegisterIds[0] ?? null;
        }
    }

    public function updatedBranchFilterId($value): void
    {
        $value = $value ? (int) $value : null;

        $cashRegisterIds = $this->filterCashRegisters()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value === null || ! in_array((int) $this->cashRegisterFilterId, $cashRegisterIds, true)) {
            $this->cashRegisterFilterId = null;
        }
    }

    public function clearOpenForm(): void
    {
        $this->resetOpenForm();
    }

    public function openSession(OpenCashSession $openCashSession): void
    {
        $this->ensurePermission('cash.open');

        if (! $this->canOpenCashSessions()) {
            $this->addError('branchId', 'Debes tener al menos una sucursal activa y una caja activa para abrir sesion.');

            return;
        }

        $company = $this->currentCompany();
        $validated = $this->validate([
            'branchId' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')),
            ],
            'cashRegisterId' => [
                'required',
                Rule::exists('cash_registers', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')),
            ],
            'openingAmount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payload = [
            'branch_id' => (int) $validated['branchId'],
            'cash_register_id' => (int) $validated['cashRegisterId'],
            'opened_by' => auth()->id(),
        ];

        if ($this->blankToNull($validated['openingAmount'] ?? null) !== null) {
            $payload['opening_amount'] = $validated['openingAmount'];
        }

        try {
            $openCashSession->handle($company, $payload);
        } catch (InvalidArgumentException $exception) {
            $this->addError('openingAmount', $exception->getMessage());

            return;
        }

        $this->resetOpenForm();
        $this->toast('Sesion de caja abierta correctamente.');
    }

    public function startClosingSession(int $sessionId): void
    {
        $this->ensurePermission('cash.close');

        $session = $this->sessionsQuery()
            ->with('payments')
            ->where('status', CashSessionStatus::Open->value)
            ->findOrFail($sessionId);

        $this->closingSessionId = $session->id;
        $this->closingCountedAmount = $this->expectedCashAmount($session);
        $this->resetValidation();
    }

    public function cancelClosingSession(): void
    {
        $this->resetCloseForm();
    }

    public function closeSession(CloseCashSession $closeCashSession): void
    {
        $this->ensurePermission('cash.close');

        $validated = $this->validate([
            'closingSessionId' => [
                'required',
                Rule::exists('cash_sessions', 'id')->where(fn ($query) => $query
                    ->where('company_id', $this->currentCompany()->id)),
            ],
            'closingCountedAmount' => ['required', 'numeric'],
        ]);

        $session = $this->sessionsQuery()->findOrFail((int) $validated['closingSessionId']);

        try {
            $closeCashSession->handle($this->currentCompany(), $session, [
                'closed_by' => auth()->id(),
                'closing_counted_amount' => $validated['closingCountedAmount'],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('closingCountedAmount', $exception->getMessage());

            return;
        }

        $this->resetCloseForm();
        $this->toast('Sesion de caja cerrada correctamente.');
    }

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function cashRegisters(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
        }

        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function filterCashRegisters(): Collection
    {
        $query = CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at');

        if ($this->branchFilterId) {
            $query->where('branch_id', $this->branchFilterId);
        }

        return $query
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function sessions(): Collection
    {
        return $this->sessionsQuery()
            ->with(['branch', 'cashRegister', 'opener', 'closer', 'payments'])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->branchFilterId, fn (Builder $query) => $query->where('branch_id', $this->branchFilterId))
            ->when($this->cashRegisterFilterId, fn (Builder $query) => $query->where('cash_register_id', $this->cashRegisterFilterId))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('id', $search)
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->whereLike('name', $search))
                        ->orWhereHas('cashRegister', fn (Builder $registerQuery) => $registerQuery->whereLike('name', $search))
                        ->orWhereHas('opener', fn (Builder $userQuery) => $userQuery->whereLike('name', $search))
                        ->orWhereHas('closer', fn (Builder $userQuery) => $userQuery->whereLike('name', $search));
                });
            })
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get();
    }

    public function statusCards(): array
    {
        $sessions = $this->sessions();

        return [
            'open_count' => $sessions->where('status', CashSessionStatus::Open->value)->count(),
            'closed_count' => $sessions->where('status', CashSessionStatus::Closed->value)->count(),
            'reconciled_count' => $sessions->where('status', CashSessionStatus::Reconciled->value)->count(),
            'open_expected_cash' => \App\Support\Money::format((float) $sessions
                ->where('status', CashSessionStatus::Open->value)
                ->sum(fn (CashSession $session) => (float) $this->expectedCashAmount($session)), 2, '.', ','),
            'cash_registers_count' => $this->filterCashRegisters()->count(),
        ];
    }

    public function canAccessCash(): bool
    {
        return $this->canOpenCash()
            || $this->canCloseCash()
            || $this->canViewDifference();
    }

    public function canOpenCash(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('cash.open') ?? false;
    }

    public function canCloseCash(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('cash.close') ?? false;
    }

    public function canViewDifference(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('cash.view_difference') ?? false;
    }

    public function canOpenCashSessions(): bool
    {
        return $this->branches()->isNotEmpty()
            && $this->cashRegisters()->isNotEmpty();
    }

    public function openingRequired(): bool
    {
        return (bool) app(CompanySettings::class)->get($this->currentCompany(), 'cash', 'opening_required');
    }

    public function defaultOpeningAmount(): string
    {
        return \App\Support\Money::format((float) app(CompanySettings::class)->get($this->currentCompany(), 'cash', 'default_opening_amount'));
    }

    public function allowsCloseWithDifference(): bool
    {
        return (bool) app(CompanySettings::class)->get($this->currentCompany(), 'cash', 'allow_close_with_difference');
    }

    public function expectedCashAmount(CashSession $session): string
    {
        $session->loadMissing('payments');

        $cashPayments = $session->payments
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('payment_method_code', 'cash')
            ->sum('amount');

        if (in_array($session->status, [CashSessionStatus::Closed->value, CashSessionStatus::Reconciled->value], true) && $session->closing_expected_amount !== null) {
            return (string) round((float) $session->closing_expected_amount);
        }

        return (string) round((float) $session->opening_amount + (float) $cashPayments);
    }

    public function render(): View
    {
        return view('livewire.cash.cash-sessions-page', [
            'branches' => $this->branches(),
            'cashRegisters' => $this->cashRegisters(),
            'filterCashRegisters' => $this->filterCashRegisters(),
            'sessions' => $this->sessions(),
            'statusCards' => $this->statusCards(),
            'canOpenCashSessions' => $this->canOpenCashSessions(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Caja',
                'description' => 'Abre y cierra sesiones de caja, controla efectivo esperado y deja listo el contexto operativo para el POS.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function sessionsQuery()
    {
        return CashSession::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function ensureCashAccess(): void
    {
        abort_unless(
            $this->canAccessCash(),
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

    protected function resetOpenForm(): void
    {
        $branches = $this->branches();
        $this->branchId = $branches->first()?->id;
        $this->cashRegisterId = $this->cashRegisters()->first()?->id;
        $this->openingAmount = '';
        $this->resetValidation();
    }

    protected function resetCloseForm(): void
    {
        $this->closingSessionId = null;
        $this->closingCountedAmount = '';
        $this->resetValidation();
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

