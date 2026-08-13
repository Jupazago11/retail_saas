<?php

namespace App\Livewire\Cash;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\ManageCashSessionExpenses;
use App\Actions\Cash\ManageCashSessionFunds;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Settings\UpdateCompanySettings;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionExpense;
use App\Models\CashSessionFund;
use App\Models\Company;
use App\Services\Plans\CompanyPlanResolver;
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

    public string $cashStep = 'choice';
    public ?int $activeCashRegisterId = null;
    public string $calendarMonth = '';
    public string $historyDate = '';
    public ?int $historyCashRegisterId = null;

    public ?int $branchId = null;
    public ?int $cashRegisterId = null;
    public string $openingAmount = '';
    public array $openFunds = [];

    public ?int $closingSessionId = null;
    public string $closingCountedAmount = '';

    public bool $showCuadreModal = false;
    public ?int $cuadreSessionId = null;
    public string $newExpenseDescription = '';
    public string $newExpenseAmount = '';
    public array $denominationCounts = [];
    public string $newFundLabel = '';
    public string $newFundAmount = '';

    public bool $showRulesModal = false;
    public bool $ruleRequiresOpenCashSession = true;
    public bool $ruleOpeningRequired = true;
    public string $ruleDefaultOpeningAmount = '0';
    public bool $ruleAllowCloseWithDifference = false;
    public string $newCashRegisterName = '';

    public function mount(): void
    {
        $this->ensureCashAccess();
        $this->calendarMonth = now()->format('Y-m');

        if ($this->requiresCashRegisterSelection()) {
            $stored = session($this->activeCashRegisterSessionKey());
            $isValid = $stored && $this->enabledCashRegisters()->contains('id', (int) $stored);

            if ($isValid) {
                $this->activeCashRegisterId = (int) $stored;
            } else {
                $this->cashStep = 'select_register';

                return;
            }
        }

        $this->resetOpenForm();
        $this->afterCashRegisterResolved();
    }

    // Empresas con mas de una caja habilitada deben elegir con cual van a
    // trabajar antes de ver el menu real de caja, una sola vez por inicio
    // de sesion (la eleccion vive en la sesion HTTP, se limpia al cerrar
    // sesion). Si solo hay una caja habilitada no tiene sentido preguntar.
    public function requiresCashRegisterSelection(): bool
    {
        return $this->enabledCashRegisters()->count() > 1;
    }

    public function enabledCashRegisters(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->with('branch')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function selectActiveCashRegister(int $registerId): void
    {
        $register = $this->enabledCashRegisters()->firstWhere('id', $registerId);

        if (! $register) {
            $this->addError('activeCashRegisterId', 'Selecciona una caja valida.');

            return;
        }

        $this->activeCashRegisterId = $register->id;
        session([$this->activeCashRegisterSessionKey() => $register->id]);
        $this->resetErrorBag();
        $this->resetOpenForm();
        $this->cashStep = 'choice';
        $this->afterCashRegisterResolved();
    }

    public function changeActiveCashRegister(): void
    {
        $this->cashStep = 'select_register';
    }

    protected function activeCashRegisterSessionKey(): string
    {
        return 'cash.active_register.company_' . $this->currentCompany()->id;
    }

    protected function afterCashRegisterResolved(): void
    {
        // Si la caja activa de esta sesion ya tiene un cuadre abierto, no
        // tiene sentido preguntar "crear o ver historial": se entra directo.
        if ($this->activeCashRegisterId) {
            $openSession = $this->sessionsQuery()
                ->where('status', CashSessionStatus::Open->value)
                ->where('cash_register_id', $this->activeCashRegisterId)
                ->first();

            if ($openSession) {
                $this->openCuadre($openSession->id);
            }

            return;
        }

        // Si el plan ya esta al tope de cajas y solo hay una sesion abierta,
        // no tiene sentido preguntar "crear o ver historial": se entra
        // directo al cuadre de esa sesion.
        if (! $this->hasAvailableCashRegisters()) {
            $openSessions = $this->openSessionsForChoice();

            if ($openSessions->count() === 1) {
                $this->openCuadre($openSessions->first()->id);
            }
        }
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

    public function clearOpenForm(): void
    {
        $this->resetOpenForm();
    }

    public function startCreate(): void
    {
        $this->ensurePermission('cash.open');

        if (! $this->hasAvailableCashRegisters()) {
            $openSession = $this->openSessionsForChoice()->first();

            if ($openSession) {
                $this->openCuadre($openSession->id);

                return;
            }
        }

        $this->resetOpenForm();
        $this->cashStep = 'create';
    }

    public function startHistory(): void
    {
        $this->calendarMonth = now()->format('Y-m');
        $this->historyDate = '';
        $this->historyCashRegisterId = null;
        $this->cashStep = 'calendar';
    }

    public function openCalendarShortcut(): void
    {
        $this->showCuadreModal = false;
        $this->cuadreSessionId = null;
        $this->startHistory();
    }

    public function changeCashRegisterShortcut(): void
    {
        $this->showCuadreModal = false;
        $this->cuadreSessionId = null;
        $this->changeActiveCashRegister();
    }

    public function backToChoice(): void
    {
        $this->cashStep = 'choice';
        $this->historyDate = '';
        $this->historyCashRegisterId = null;
    }

    public function backToCalendar(): void
    {
        $this->cashStep = 'calendar';
        $this->historyDate = '';
        $this->historyCashRegisterId = null;
    }

    public function calendarPrevMonth(): void
    {
        $this->shiftCalendarMonth(-1);
    }

    public function calendarNextMonth(): void
    {
        $this->shiftCalendarMonth(1);
    }

    protected function shiftCalendarMonth(int $delta): void
    {
        $this->calendarMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->calendarMonth . '-01')
            ->addMonths($delta)
            ->format('Y-m');
    }

    public function selectHistoryDate(string $date): void
    {
        $this->historyDate = $date;

        $registerIds = $this->sessionsQuery()
            ->whereDate('opened_at', $date)
            ->orderBy('cash_register_id')
            ->pluck('cash_register_id')
            ->unique();

        $this->historyCashRegisterId = $registerIds->first();
        $this->cashStep = 'day_view';
    }

    public function canManageRules(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('settings.manage') ?? false;
    }

    public function openRulesModal(): void
    {
        $this->ensurePermission('settings.manage');

        $companySettings = app(CompanySettings::class);
        $company = $this->currentCompany();

        $this->ruleRequiresOpenCashSession = (bool) $companySettings->get($company, 'pos', 'requires_open_cash_session');
        $this->ruleOpeningRequired = (bool) $companySettings->get($company, 'cash', 'opening_required');
        $this->ruleDefaultOpeningAmount = $this->trimDecimalZeros((string) $companySettings->get($company, 'cash', 'default_opening_amount'));
        $this->ruleAllowCloseWithDifference = (bool) $companySettings->get($company, 'cash', 'allow_close_with_difference');
        $this->newCashRegisterName = '';

        $this->resetErrorBag();
        $this->showRulesModal = true;
    }

    protected function trimDecimalZeros(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    public function closeRulesModal(): void
    {
        $this->showRulesModal = false;
        $this->resetErrorBag();
    }

    public function saveRules(UpdateCompanySettings $updateCompanySettings): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'ruleDefaultOpeningAmount' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $updateCompanySettings->handle($this->currentCompany(), [
                'pos' => [
                    'requires_open_cash_session' => $this->ruleRequiresOpenCashSession,
                ],
                'cash' => [
                    'opening_required' => $this->ruleOpeningRequired,
                    'default_opening_amount' => $validated['ruleDefaultOpeningAmount'],
                    'allow_close_with_difference' => $this->ruleAllowCloseWithDifference,
                ],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('ruleDefaultOpeningAmount', $exception->getMessage());

            return;
        }

        $this->showRulesModal = false;
        $this->toast('Reglas de caja actualizadas correctamente.');
    }

    public function companyCashRegisters(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->whereNull('deleted_at')
            ->with('branch')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function cashRegisterPlanLimit(): ?int
    {
        return app(CompanyPlanResolver::class)->limit($this->currentCompany(), 'max_cash_registers');
    }

    public function canCreateMoreCashRegisters(): bool
    {
        $limit = $this->cashRegisterPlanLimit();

        if ($limit === null) {
            return true;
        }

        return $this->companyCashRegisters()->count() < $limit;
    }

    public function addCashRegister(\App\Actions\Company\CreateCashRegister $createCashRegister): void
    {
        $this->ensurePermission('settings.manage');

        $validated = $this->validate([
            'newCashRegisterName' => ['required', 'string', 'max:255'],
        ]);

        $company = $this->currentCompany();
        $branch = $this->branches()->first();

        if (! $branch) {
            $this->addError('newCashRegisterName', 'Debes tener al menos una sucursal activa para crear una caja.');

            return;
        }

        try {
            $createCashRegister->handle($company, [
                'branch_id' => $branch->id,
                'name' => $validated['newCashRegisterName'],
                'code' => $validated['newCashRegisterName'],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('newCashRegisterName', $exception->getMessage());

            return;
        }

        $this->newCashRegisterName = '';
        $this->toast('Caja creada correctamente.');
    }

    public function toggleCashRegisterStatus(int $registerId): void
    {
        $this->ensurePermission('settings.manage');

        $register = CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->findOrFail($registerId);

        if ($register->is_primary) {
            $this->toast('La caja principal no se puede desactivar.', 'warning');

            return;
        }

        if ($register->status === RecordStatus::Active->value) {
            $hasOpenSession = CashSession::query()
                ->where('cash_register_id', $register->id)
                ->where('status', CashSessionStatus::Open->value)
                ->exists();

            if ($hasOpenSession) {
                $this->toast('No puedes desactivar una caja con una sesion abierta.', 'error');

                return;
            }
        }

        $register->update([
            'status' => $register->status === RecordStatus::Active->value
                ? RecordStatus::Inactive->value
                : RecordStatus::Active->value,
        ]);

        $this->toast('Estado de la caja actualizado.');
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

        $funds = collect($this->openFunds)
            ->map(fn ($fund) => ['label' => trim((string) ($fund['label'] ?? '')), 'amount' => $fund['amount'] ?? ''])
            ->filter(fn ($fund) => $fund['label'] !== '' && is_numeric($fund['amount']) && (float) $fund['amount'] > 0)
            ->values()
            ->all();

        if ($funds !== []) {
            $payload['funds'] = $funds;
        } elseif ($this->blankToNull($validated['openingAmount'] ?? null) !== null) {
            $payload['opening_amount'] = $validated['openingAmount'];
        }

        try {
            $newSession = $openCashSession->handle($company, $payload);
        } catch (InvalidArgumentException $exception) {
            $this->addError('openingAmount', $exception->getMessage());

            return;
        }

        $this->resetOpenForm();
        $this->cashStep = 'choice';
        $this->toast('Sesion de caja abierta correctamente.');
        $this->openCuadre($newSession->id);
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
            'closingCountedAmount' => ['nullable', 'numeric'],
        ]);

        $session = $this->sessionsQuery()->findOrFail((int) $validated['closingSessionId']);

        $closePayload = ['closed_by' => auth()->id()];
        $denominationRows = $this->denominationBreakdownPayload();

        if ($denominationRows !== []) {
            $closePayload['denomination_breakdown'] = $denominationRows;
        } elseif ($this->blankToNull($validated['closingCountedAmount'] ?? null) !== null) {
            $closePayload['closing_counted_amount'] = $validated['closingCountedAmount'];
        } else {
            $this->addError('closingCountedAmount', 'Ingresa el monto contado o cuenta el efectivo por denominacion.');

            return;
        }

        try {
            $closeCashSession->handle($this->currentCompany(), $session, $closePayload);
        } catch (InvalidArgumentException $exception) {
            $this->addError('closingCountedAmount', $exception->getMessage());

            return;
        }

        $this->closeCuadre();
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
            ->whereDoesntHave('cashSessions', fn ($query) => $query->where('status', CashSessionStatus::Open->value))
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function hasAvailableCashRegisters(): bool
    {
        foreach ($this->branches() as $branch) {
            $available = CashRegister::query()
                ->where('company_id', $this->currentCompany()->id)
                ->where('branch_id', $branch->id)
                ->where('status', RecordStatus::Active->value)
                ->whereNull('deleted_at')
                ->whereDoesntHave('cashSessions', fn ($query) => $query->where('status', CashSessionStatus::Open->value))
                ->exists();

            if ($available) {
                return true;
            }
        }

        return false;
    }

    public function openSessionsForChoice(): Collection
    {
        return $this->sessionsQuery()
            ->with(['branch', 'cashRegister'])
            ->where('status', CashSessionStatus::Open->value)
            ->when($this->activeCashRegisterId, fn ($query) => $query->where('cash_register_id', $this->activeCashRegisterId))
            ->orderBy('opened_at')
            ->get();
    }

    public function calendarDaysWithSessions(): array
    {
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->calendarMonth . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $this->sessionsQuery()
            ->whereBetween('opened_at', [$start, $end])
            ->selectRaw('DATE(opened_at) as day')
            ->distinct()
            ->pluck('day')
            ->map(fn ($day) => (string) $day)
            ->all();
    }

    public function calendarCells(): array
    {
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->calendarMonth . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysWithSessions = $this->calendarDaysWithSessions();

        $cells = [];

        // Relleno inicial para que el dia 1 caiga en su columna (semana inicia en lunes).
        for ($i = 0; $i < $start->dayOfWeekIso - 1; $i++) {
            $cells[] = ['date' => null, 'day' => null, 'hasSessions' => false, 'isToday' => false];
        }

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->format('Y-m-d');

            $cells[] = [
                'date' => $dateString,
                'day' => $date->day,
                'hasSessions' => in_array($dateString, $daysWithSessions, true),
                'isToday' => $date->isToday(),
            ];
        }

        return $cells;
    }

    public function historyCashRegisterOptions(): Collection
    {
        if ($this->historyDate === '') {
            return new Collection();
        }

        $registerIds = $this->sessionsQuery()
            ->whereDate('opened_at', $this->historyDate)
            ->pluck('cash_register_id')
            ->unique();

        return CashRegister::query()
            ->whereIn('id', $registerIds)
            ->orderBy('name')
            ->get();
    }

    public function historySession(): ?CashSession
    {
        if ($this->historyDate === '' || ! $this->historyCashRegisterId) {
            return null;
        }

        return $this->sessionsQuery()
            ->with([
                'branch', 'cashRegister', 'opener', 'closer', 'payments',
                'expenses' => fn ($query) => $query->orderByDesc('id'),
                'funds' => fn ($query) => $query->orderBy('id'),
            ])
            ->whereDate('opened_at', $this->historyDate)
            ->where('cash_register_id', $this->historyCashRegisterId)
            ->orderByDesc('opened_at')
            ->first();
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
        $session->loadMissing(['payments', 'expenses']);

        $cashPayments = $session->payments
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('payment_method_code', 'cash')
            ->sum('amount');

        $expenses = $session->expenses->sum('amount');

        if (in_array($session->status, [CashSessionStatus::Closed->value, CashSessionStatus::Reconciled->value], true) && $session->closing_expected_amount !== null) {
            return (string) round((float) $session->closing_expected_amount);
        }

        return (string) round((float) $session->opening_amount + (float) $cashPayments - (float) $expenses);
    }

    public function dailySalesAmount(CashSession $session): string
    {
        $session->loadMissing('payments');

        return (string) round((float) $session->payments
            ->where('status', PaymentStatus::Confirmed->value)
            ->sum('amount'));
    }

    public function render(): View
    {
        return view('livewire.cash.cash-sessions-page', [
            'branches' => $this->branches(),
            'cashRegisters' => $this->cashRegisters(),
            'canOpenCashSessions' => $this->canOpenCashSessions(),
            'cuadreSession' => $this->cuadreSession(),
            'openSessionsForChoice' => $this->openSessionsForChoice(),
            'calendarCells' => $this->calendarCells(),
            'historyCashRegisterOptions' => $this->historyCashRegisterOptions(),
            'historySession' => $this->historySession(),
            'companyCashRegisters' => $this->companyCashRegisters(),
            'cashRegisterPlanLimit' => $this->cashRegisterPlanLimit(),
            'enabledCashRegisters' => $this->requiresCashRegisterSelection() || $this->cashStep === 'select_register' ? $this->enabledCashRegisters() : new Collection(),
            'requiresCashRegisterSelection' => $this->requiresCashRegisterSelection(),
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

        abort_unless(
            (bool) app(CompanySettings::class)->get($this->currentCompany(), 'cash', 'module_enabled'),
            404,
            'Esta empresa no tiene habilitado el modulo de caja.'
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
        $activeRegister = $this->activeCashRegisterId
            ? $this->enabledCashRegisters()->firstWhere('id', $this->activeCashRegisterId)
            : null;

        if ($activeRegister) {
            $this->branchId = $activeRegister->branch_id;
        } else {
            $this->branchId = $this->branches()->first()?->id;
        }

        $available = $this->cashRegisters();
        $this->cashRegisterId = $activeRegister && $available->contains('id', $activeRegister->id)
            ? $activeRegister->id
            : $available->first()?->id;

        $this->openingAmount = '';
        $this->openFunds = [['label' => 'Base inicial', 'amount' => '']];
        $this->resetValidation();
    }

    protected function resetCloseForm(): void
    {
        $this->closingSessionId = null;
        $this->closingCountedAmount = '';
        $this->denominationCounts = [];
        $this->resetValidation();
    }

    public function addOpenFund(): void
    {
        $this->openFunds[] = ['label' => 'Base ' . (count($this->openFunds) + 1), 'amount' => ''];
    }

    public function removeOpenFund(int $index): void
    {
        if (count($this->openFunds) <= 1) {
            return;
        }

        unset($this->openFunds[$index]);
        $this->openFunds = array_values($this->openFunds);
    }

    public function denominations(): array
    {
        return [
            'Moneda' => ['coin_50' => 50, 'coin_100' => 100, 'coin_200' => 200, 'coin_500' => 500, 'coin_1000' => 1000],
            'Billete' => ['bill_1000' => 1000, 'bill_2000' => 2000, 'bill_5000' => 5000, 'bill_10000' => 10000, 'bill_20000' => 20000, 'bill_50000' => 50000, 'bill_100000' => 100000],
        ];
    }

    public function denominationTotal(): string
    {
        $total = '0.00';

        foreach ($this->denominationBreakdownPayload() as $row) {
            $total = bcadd($total, bcmul((string) $row['value'], (string) $row['quantity'], 2), 2);
        }

        return $total;
    }

    protected function denominationBreakdownPayload(): array
    {
        $values = array_merge(...array_values($this->denominations()));
        $rows = [];

        foreach ($values as $key => $value) {
            $quantity = (int) ($this->denominationCounts[$key] ?? 0);

            if ($quantity > 0) {
                $rows[] = ['value' => $value, 'quantity' => $quantity];
            }
        }

        return $rows;
    }

    public function openCuadre(int $sessionId): void
    {
        $session = $this->sessionsQuery()->findOrFail($sessionId);

        $this->cuadreSessionId = $session->id;
        $this->closingSessionId = $session->status === CashSessionStatus::Open->value ? $session->id : null;
        $this->closingCountedAmount = '';
        $this->denominationCounts = [];
        $this->newExpenseDescription = '';
        $this->newExpenseAmount = '';
        $this->newFundLabel = '';
        $this->newFundAmount = '';
        $this->resetErrorBag();
        $this->showCuadreModal = true;
    }

    public function closeCuadre(): void
    {
        $this->showCuadreModal = false;
        $this->cuadreSessionId = null;
        $this->resetCloseForm();
    }

    public function canEditHistoricalCuadres(): bool
    {
        return $this->canManageRules();
    }

    public function cuadreSession(): ?CashSession
    {
        if (! $this->cuadreSessionId) {
            return null;
        }

        return $this->sessionsQuery()
            ->with([
                'branch', 'cashRegister', 'opener', 'payments',
                'expenses' => fn ($query) => $query->orderByDesc('id'),
                'funds' => fn ($query) => $query->orderBy('id'),
            ])
            ->find($this->cuadreSessionId);
    }

    public function addFund(ManageCashSessionFunds $manageCashSessionFunds): void
    {
        $this->ensurePermission('cash.open');

        if (! $this->cuadreSessionId) {
            return;
        }

        $validated = $this->validate([
            'newFundLabel' => ['required', 'string', 'max:255'],
            'newFundAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $session = $this->sessionsQuery()->findOrFail($this->cuadreSessionId);

        try {
            $manageCashSessionFunds->add($this->currentCompany(), $session, [
                'label' => $validated['newFundLabel'],
                'amount' => $validated['newFundAmount'],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('newFundAmount', $exception->getMessage());

            return;
        }

        $this->newFundLabel = '';
        $this->newFundAmount = '';
        $this->toast('Base agregada.');
    }

    public function saveFundAmount(int $fundId, string $amount, ManageCashSessionFunds $manageCashSessionFunds): void
    {
        $this->ensurePermission('cash.open');

        $fund = CashSessionFund::query()
            ->where('company_id', $this->currentCompany()->id)
            ->findOrFail($fundId);

        try {
            $manageCashSessionFunds->update($this->currentCompany(), $fund, ['amount' => $amount], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast('Base actualizada.');
    }

    public function saveFundLabel(int $fundId, string $label, ManageCashSessionFunds $manageCashSessionFunds): void
    {
        $this->ensurePermission('cash.open');

        $fund = CashSessionFund::query()
            ->where('company_id', $this->currentCompany()->id)
            ->findOrFail($fundId);

        try {
            $manageCashSessionFunds->update($this->currentCompany(), $fund, ['label' => $label], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast('Base actualizada.');
    }

    public function deleteFund(int $fundId, ManageCashSessionFunds $manageCashSessionFunds): void
    {
        $this->ensurePermission('cash.open');

        $fund = CashSessionFund::query()
            ->where('company_id', $this->currentCompany()->id)
            ->findOrFail($fundId);

        try {
            $manageCashSessionFunds->delete($this->currentCompany(), $fund, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast('Base eliminada.', 'warning');
    }

    public function addExpense(ManageCashSessionExpenses $manageCashSessionExpenses): void
    {
        $this->ensurePermission('cash.open');

        if (! $this->cuadreSessionId) {
            return;
        }

        $validated = $this->validate([
            'newExpenseDescription' => ['required', 'string', 'max:255'],
            'newExpenseAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $session = $this->sessionsQuery()->findOrFail($this->cuadreSessionId);

        try {
            $manageCashSessionExpenses->record($this->currentCompany(), $session, [
                'description' => $validated['newExpenseDescription'],
                'amount' => $validated['newExpenseAmount'],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('newExpenseAmount', $exception->getMessage());

            return;
        }

        $this->newExpenseDescription = '';
        $this->newExpenseAmount = '';
        $this->toast('Pago de caja registrado.');
    }

    public function deleteExpense(int $expenseId, ManageCashSessionExpenses $manageCashSessionExpenses): void
    {
        $this->ensurePermission('cash.open');

        $expense = CashSessionExpense::query()
            ->where('company_id', $this->currentCompany()->id)
            ->findOrFail($expenseId);

        try {
            $manageCashSessionExpenses->delete($this->currentCompany(), $expense, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast('Pago de caja eliminado.', 'warning');
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

