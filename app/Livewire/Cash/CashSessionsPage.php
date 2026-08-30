<?php

namespace App\Livewire\Cash;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\ManageCashSessionExpenses;
use App\Actions\Cash\ManageCashSessionFunds;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Cash\UpdateCashSessionCount;
use App\Actions\Settings\UpdateCompanySettings;
use App\Enums\CashSessionStatus;
use App\Enums\PayableMovementType;
use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionExpense;
use App\Models\CashSessionFund;
use App\Models\Company;
use App\Models\PayableMovement;
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
    // No vacio solo cuando el formulario de "crear caja" se abrio desde el
    // boton "Crear cuadre de caja" de un dia del historial sin cuadre (ver
    // startCreateForHistoryDate()) — le dice a openSession() que backdatee
    // la sesion a este dia en vez de usar "ahora".
    public string $creatingSessionForDate = '';

    public ?int $closingSessionId = null;
    public string $closingCountedAmount = '';
    // No nulo solo cuando se esta corrigiendo el "Contado" de una sesion YA
    // cerrada (ver startRecountingClosedSession()) — comparte
    // denominationCounts/closingCountedAmount con el cierre normal porque
    // solo un cuadre se muestra a la vez.
    public ?int $recountingSessionId = null;

    public bool $showCuadreModal = false;
    public ?int $cuadreSessionId = null;
    public string $newExpenseDescription = '';
    public string $newExpenseAmount = '';
    public array $denominationCounts = [];
    public string $newFundLabel = '';
    public string $newFundAmount = '';
    /** @var array<int> */
    public array $selectedPurchasePaymentIds = [];

    public bool $showRulesModal = false;
    public bool $ruleOpeningRequired = true;
    public string $ruleDefaultOpeningAmount = '0';
    public string $newRegisterName = '';
    public string $newRegisterPrinterType = 'thermal_58mm';

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

    /**
     * "Crear cuadre de caja" desde un dia del historial que no tiene
     * ninguna sesion registrada (day_view con historySession() = null).
     * Reutiliza el mismo formulario de "Crear caja", solo que openSession()
     * va a backdatear la sesion a $historyDate en vez de usar "ahora" (ver
     * creatingSessionForDate). Un dia pasado exige el mismo permiso que
     * corregir un cuadre ya cerrado, porque backdatear una apertura es
     * igual de sensible que editar una; el dia de hoy no, porque eso es
     * exactamente lo mismo que el flujo normal de "Crear caja".
     */
    public function startCreateForHistoryDate(): void
    {
        $this->ensurePermission('cash.open');

        if ($this->historyDate === '') {
            return;
        }

        $date = \Illuminate\Support\Carbon::parse($this->historyDate);

        if ($date->isFuture() && ! $date->isToday()) {
            $this->toast('No puedes crear un cuadre de caja para una fecha futura.', 'error');

            return;
        }

        if (! $date->isToday() && ! $this->canEditHistoricalCuadres()) {
            $this->toast('Solo un administrador puede crear el cuadre de un dia pasado.', 'error');

            return;
        }

        // Toda empresa siempre tiene al menos una sucursal y una caja
        // activa (la caja principal no se puede desactivar, ver
        // toggleCashRegisterStatus()), asi que si no hay ninguna caja
        // disponible la causa real casi siempre es esta: la unica caja (o
        // todas) ya tiene una sesion abierta ahora mismo. El mensaje
        // generico del formulario ("no tienes sucursal/caja activa") es
        // enganoso en ese caso, asi que se explica aqui antes de entrar.
        if (! $this->hasAvailableCashRegisters()) {
            $this->toast('No puedes crear un cuadre para otro dia mientras tengas una sesion de caja abierta ahora mismo. Cierrala primero.', 'error');

            return;
        }

        $this->resetOpenForm();
        $this->creatingSessionForDate = $this->historyDate;
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
        // El formulario de "crear caja" se puede haber abierto desde un dia
        // del historial sin cuadre (startCreateForHistoryDate()); "Volver"
        // ahi debe regresar a ese dia, no perderlo saltando a "choice".
        if ($this->creatingSessionForDate !== '') {
            $this->creatingSessionForDate = '';
            $this->cashStep = 'day_view';

            return;
        }

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
        $this->historyCashRegisterId = $this->historyCashRegisterOptions()->first()?->id;
        $this->cashStep = 'day_view';
        $this->selectedPurchasePaymentIds = [];
        $this->cancelRecountingClosedSession();
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

        $this->ruleOpeningRequired = (bool) $companySettings->get($company, 'cash', 'opening_required');
        $this->ruleDefaultOpeningAmount = $this->trimDecimalZeros((string) $companySettings->get($company, 'cash', 'default_opening_amount'));
        $this->newRegisterName = '';
        $this->newRegisterPrinterType = 'thermal_58mm';

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
                'cash' => [
                    'opening_required' => $this->ruleOpeningRequired,
                    'default_opening_amount' => $validated['ruleDefaultOpeningAmount'],
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
            'newRegisterName' => ['required', 'string', 'max:255'],
            'newRegisterPrinterType' => ['required', Rule::in(array_keys(CashRegister::PRINTER_TYPES))],
        ]);

        $company = $this->currentCompany();
        $branch = $this->branches()->first();

        if (! $branch) {
            $this->addError('newRegisterName', 'Debes tener al menos una sucursal activa para crear una caja.');

            return;
        }

        try {
            $createCashRegister->handle($company, [
                'branch_id' => $branch->id,
                'name' => $validated['newRegisterName'],
                'code' => $validated['newRegisterName'],
                'printer_type' => $validated['newRegisterPrinterType'],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('newRegisterName', $exception->getMessage());

            return;
        }

        $this->newRegisterName = '';
        $this->newRegisterPrinterType = 'thermal_58mm';
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

        if ($this->creatingSessionForDate !== '') {
            $payload['opened_at'] = $this->creatingSessionForDate;
        }

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

        $wasBackdated = $this->creatingSessionForDate !== '';
        $this->resetOpenForm();
        $this->toast('Sesion de caja abierta correctamente.');

        if ($wasBackdated) {
            // Vuelve a la vista del dia para el que se creo (en vez del
            // modal generico): ese dia ya no esta "sin cuadre" y ahora debe
            // mostrar la sesion recien abierta, lista para llenar/cerrar.
            $this->historyCashRegisterId = $newSession->cash_register_id;
            $this->cashStep = 'day_view';

            return;
        }

        $this->cashStep = 'choice';
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

    public function calendarCells(): array
    {
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $this->calendarMonth . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $sessionsByDay = $this->sessionsQuery()
            ->with('cashRegister')
            ->whereBetween('opened_at', [$start, $end])
            ->orderBy('opened_at')
            ->get()
            ->groupBy(fn (CashSession $session) => $session->opened_at->format('Y-m-d'));

        // Una caja abierta sigue "pendiente" sin importar que dia se abrio
        // — si se abrio ayer y todavia nadie la cierra, quiero verla en el
        // popover de HOY, no solo escondida en el dia en que se abrio.
        $openRegardlessOfDay = $this->sessionsQuery()
            ->with('cashRegister')
            ->where('status', CashSessionStatus::Open->value)
            ->get()
            ->groupBy('cash_register_id')
            ->map(fn (Collection $group) => $group->sortByDesc('opened_at')->first());

        // Y al reves: si una caja se abrio OTRO dia pero se cerro HOY, ese
        // cierre tambien es algo que paso hoy — sin esto, una caja abierta
        // el 11 y cerrada el 13 solo aparecia en el popover del 11, como si
        // cerrarla hoy no hubiera sido una accion de hoy.
        $closedToday = $this->sessionsQuery()
            ->with('cashRegister')
            ->whereNotNull('closed_at')
            ->whereDate('closed_at', now())
            ->get()
            ->groupBy('cash_register_id')
            ->map(fn (Collection $group) => $group->sortByDesc('closed_at')->first());

        $canViewDifference = $this->canViewDifference();

        $cells = [];

        // Relleno inicial para que el dia 1 caiga en su columna (semana inicia en lunes).
        for ($i = 0; $i < $start->dayOfWeekIso - 1; $i++) {
            $cells[] = ['date' => null, 'day' => null, 'hasSessions' => false, 'isToday' => false, 'registers' => []];
        }

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();

            // Si una caja se abrio/cerro (o reabrio) varias veces el mismo
            // dia, la sesion mas reciente es la representativa para el
            // resumen del hover — no tiene sentido listar reaperturas.
            $sessionsForCell = ($sessionsByDay->get($dateString) ?? new Collection())
                ->groupBy('cash_register_id')
                ->map(fn (Collection $group) => $group->sortByDesc('opened_at')->first());

            if ($isToday) {
                // union() conserva la sesion de $sessionsForCell cuando la
                // misma caja ya aparece ahi (abierta hoy mismo) y solo
                // agrega las que faltan. Orden importa: si una caja sigue
                // abierta ahora mismo, eso pesa mas que un cierre viejo de
                // hoy mismo para la MISMA caja (caso raro, pero por si acaso).
                $sessionsForCell = $sessionsForCell->union($openRegardlessOfDay)->union($closedToday);
            }

            $registers = $sessionsForCell
                ->map(fn (CashSession $session) => $this->calendarCellRegisterSummary($session, $canViewDifference))
                ->values()
                ->all();

            $cells[] = [
                'date' => $dateString,
                'day' => $date->day,
                'hasSessions' => count($registers) > 0,
                'isToday' => $isToday,
                'registers' => $registers,
            ];
        }

        return $cells;
    }

    /**
     * Resumen de una sesion para la fila de "esta caja este dia" del
     * popover del calendario: nombre, estado y — solo si ya cerro y el
     * usuario tiene permiso para verla — el conteo y la diferencia.
     */
    protected function calendarCellRegisterSummary(CashSession $session, bool $canViewDifference): array
    {
        $isOpen = $session->status === CashSessionStatus::Open->value;
        $difference = null;
        $negativeExpected = false;

        if ($canViewDifference && ! $isOpen) {
            $diffValue = (float) $session->difference_amount;
            $difference = $diffValue !== 0.0 ? $diffValue : null;

            // Un "esperado" negativo no es un simple "sobrante" de caja —
            // significa que los pagos registrados superaron las bases mas
            // las ventas del dia, o sea que el problema esta en lo que se
            // registro como pago, no en el conteo fisico. Contarlo como
            // "sobra" (como si el diferencia positivo fuera buena noticia)
            // esconde ese problema real.
            $negativeExpected = (float) $session->closing_expected_amount < 0;
        }

        return [
            'sessionId' => $session->id,
            'name' => $session->cashRegister?->name ?? 'Caja',
            'status' => $session->status,
            'closingCounted' => $isOpen ? null : (float) $session->closing_counted_amount,
            'difference' => $difference,
            'negativeExpected' => $negativeExpected,
        ];
    }

    public function historyCashRegisterOptions(): Collection
    {
        if ($this->historyDate === '') {
            return new Collection();
        }

        $registerIds = $this->sessionsQuery()
            ->where($this->historyDateScope($this->historyDate))
            ->pluck('cash_register_id');

        return CashRegister::query()
            ->whereIn('id', $registerIds->unique())
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
            ->where('cash_register_id', $this->historyCashRegisterId)
            ->where($this->historyDateScope($this->historyDate))
            ->orderByDesc('opened_at')
            ->first();
    }

    /**
     * Que sesiones cuentan para un dia dado del historial. Normalmente,
     * las que se abrieron ese dia exacto. Si el dia es HOY, tambien
     * cuentan las que siguen abiertas de otro dia (sin cerrar todavia) y
     * las que se cerraron hoy mismo aunque se hayan abierto otro dia —
     * "hoy" debe reflejar el estado actual de cada caja, no solo lo que
     * se abrio hoy. Se usa tanto para listar cajas seleccionables como
     * para resolver la sesion real al elegir una.
     */
    protected function historyDateScope(string $date): \Closure
    {
        $isToday = \Illuminate\Support\Carbon::parse($date)->isToday();

        return function (Builder $query) use ($date, $isToday) {
            $query->whereDate('opened_at', $date);

            if ($isToday) {
                $query->orWhere('status', CashSessionStatus::Open->value)
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('closed_at')->whereDate('closed_at', $date));
            }
        };
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

    /**
     * Monto contado "en vivo" mientras la sesion sigue abierta: prioriza el
     * desglose por denominacion si tiene algo cargado (misma prioridad que
     * closeSession()), y si no cae al monto escrito a mano.
     */
    public function currentCountedAmount(): string
    {
        $denominationTotal = $this->denominationTotal();

        if (bccomp($denominationTotal, '0.00', 2) !== 0) {
            return $denominationTotal;
        }

        return $this->blankToNull($this->closingCountedAmount) !== null
            ? (string) round((float) $this->closingCountedAmount)
            : '0';
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

    /**
     * Desglose de venta diaria por medio de pago: un solo total "todos los
     * medios de pago" mezclaba efectivo con transferencia/tarjeta/credito,
     * lo que no sirve para saber cuanto deberia haber realmente en el
     * cajon de efectivo. `payment_method_code` sigue siendo texto libre
     * (no hay catalogo formal todavia, ver docs/modelo-datos.md), asi que
     * cualquier codigo que no reconozcamos se muestra tal cual.
     *
     * @return array<string, string>
     */
    public function dailySalesByPaymentMethod(CashSession $session): array
    {
        $session->loadMissing('payments');

        return $session->payments
            ->where('status', PaymentStatus::Confirmed->value)
            ->groupBy(fn ($payment) => $payment->payment_method_code ?: 'otro')
            ->map(fn ($group) => (string) round((float) $group->sum('amount')))
            ->all();
    }

    public function paymentMethodLabel(string $code): string
    {
        return match ($code) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'credit' => 'Credito',
            'otro' => 'Otro',
            default => ucfirst($code),
        };
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
        $this->creatingSessionForDate = '';
        $this->resetValidation();
    }

    protected function resetCloseForm(): void
    {
        $this->closingSessionId = null;
        $this->closingCountedAmount = '';
        $this->denominationCounts = [];
        $this->recountingSessionId = null;
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
        $this->selectedPurchasePaymentIds = [];
        $this->recountingSessionId = null;
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
        return $this->canManageRules()
            || (auth()->user()?->hasCurrentCompanyPermission('cash.edit_closed') ?? false);
    }

    /**
     * Controla si se muestra el boton "Crear cuadre de caja" en el
     * day_view de un dia sin ninguna sesion registrada. Hoy no exige mas
     * que el permiso normal de abrir caja (es lo mismo que "Crear caja"
     * desde el menu principal); un dia pasado exige ademas el mismo
     * permiso que editar un cierre ya cerrado, porque backdatear una
     * apertura es igual de sensible.
     */
    public function canCreateForHistoryDate(): bool
    {
        if (! $this->canOpenCash() || $this->historyDate === '') {
            return false;
        }

        $date = \Illuminate\Support\Carbon::parse($this->historyDate);

        if ($date->isFuture() && ! $date->isToday()) {
            return false;
        }

        return $date->isToday() || $this->canEditHistoricalCuadres();
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

    /**
     * addFund()/addExpense() se usan tanto desde el modal de cuadre
     * (cuadreSessionId) como desde la vista de un dia del historial
     * (day_view, que solo conoce historySession()) — sin este fallback,
     * "agregar base/pago" no hacia nada al editar un cierre pasado
     * seleccionando el dia directo en el calendario, en vez de pasar por
     * el icono de lapiz del popover.
     */
    protected function editableCuadreSessionId(): ?int
    {
        return $this->cuadreSessionId ?? $this->historySession()?->id;
    }

    public function addFund(ManageCashSessionFunds $manageCashSessionFunds): void
    {
        $this->ensurePermission('cash.open');

        $sessionId = $this->editableCuadreSessionId();

        if (! $sessionId) {
            return;
        }

        $validated = $this->validate([
            'newFundLabel' => ['required', 'string', 'max:255'],
            'newFundAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $session = $this->sessionsQuery()->findOrFail($sessionId);

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

    /**
     * Abre el mismo formulario de conteo (denominaciones + monto suelto)
     * que se usa para cerrar una caja por primera vez, pero para CORREGIR
     * el contado de una sesion YA cerrada — precargado con lo que ya
     * quedo guardado, para que se sienta como la misma pantalla en vez de
     * un input suelto sin contexto. Reutiliza denominationCounts/
     * closingCountedAmount (los mismos campos del cierre normal) porque
     * solo un cuadre se muestra a la vez.
     */
    public function startRecountingClosedSession(int $sessionId): void
    {
        $this->ensurePermission('cash.open');

        $session = $this->sessionsQuery()->findOrFail($sessionId);

        $this->recountingSessionId = $session->id;
        $this->denominationCounts = $this->denominationCountsFromBreakdown($session->closing_denomination_breakdown);
        $this->closingCountedAmount = (string) round((float) $session->closing_counted_amount);
        $this->resetErrorBag();
    }

    public function cancelRecountingClosedSession(): void
    {
        $this->recountingSessionId = null;
        $this->denominationCounts = [];
        $this->closingCountedAmount = '';
    }

    public function saveRecountedAmount(UpdateCashSessionCount $updateCashSessionCount): void
    {
        $this->ensurePermission('cash.open');

        if (! $this->recountingSessionId) {
            return;
        }

        $session = $this->sessionsQuery()->findOrFail($this->recountingSessionId);
        $payload = [];
        $denominationRows = $this->denominationBreakdownPayload();

        if ($denominationRows !== []) {
            $payload['denomination_breakdown'] = $denominationRows;
        } elseif ($this->blankToNull($this->closingCountedAmount) !== null) {
            $payload['closing_counted_amount'] = $this->closingCountedAmount;
        } else {
            $this->toast('Ingresa el monto contado o cuenta el efectivo por denominacion.', 'error');

            return;
        }

        try {
            $updateCashSessionCount->handle($this->currentCompany(), $session, $payload, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->cancelRecountingClosedSession();
        $this->toast('Efectivo contado actualizado.');
    }

    /**
     * Vuelve a mapear un desglose guardado ({value, quantity} sin llave) a
     * las llaves que usa denominationCounts (coin_50, bill_1000, ...) para
     * precargar el formulario de recuento — el desglose guardado no
     * conserva la llave, solo el valor de la denominacion.
     */
    protected function denominationCountsFromBreakdown(?array $breakdown): array
    {
        if (! $breakdown) {
            return [];
        }

        $valueToKey = array_flip(array_merge(...array_values($this->denominations())));
        $counts = [];

        foreach ($breakdown as $row) {
            $key = $valueToKey[(int) ($row['value'] ?? 0)] ?? null;

            if ($key !== null) {
                $counts[$key] = (string) $row['quantity'];
            }
        }

        return $counts;
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

        $sessionId = $this->editableCuadreSessionId();

        if (! $sessionId) {
            return;
        }

        $validated = $this->validate([
            'newExpenseDescription' => ['required', 'string', 'max:255'],
            'newExpenseAmount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $session = $this->sessionsQuery()->findOrFail($sessionId);

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

    /**
     * Pagos de compras registrados el mismo dia de la sesion (mismo
     * `branch_id`) que todavia no se han convertido en un pago de caja —
     * candidatas para que el usuario las marque en vez de volver a
     * digitarlas a mano. Se compara por la fecha de APERTURA de la sesion,
     * no por "hoy": una sesion de un dia pasado que se esta corrigiendo
     * debe ver las compras de ESE dia, no las de hoy.
     *
     * No se filtra por `payment_method_code`: "Pagos de caja" ya no
     * alimenta ningun calculo de esperado/diferencia (se quito esa
     * comparacion), es solo un registro informativo de plata que salio ese
     * dia — asi que una compra pagada por transferencia es igual de
     * relevante para no tener que volver a escribirla a mano que una
     * pagada en efectivo.
     */
    public function purchasePaymentCandidates(CashSession $session): Collection
    {
        return PayableMovement::query()
            ->where('company_id', $session->company_id)
            ->where('movement_type', PayableMovementType::Payment->value)
            ->whereDate('occurred_at', $session->opened_at->toDateString())
            ->whereDoesntHave('cashSessionExpense')
            ->whereHas('purchase', fn ($query) => $query->where('branch_id', $session->branch_id))
            ->with('purchase')
            ->orderBy('occurred_at')
            ->get();
    }

    public function addSelectedPurchasePayments(ManageCashSessionExpenses $manageCashSessionExpenses): void
    {
        $this->ensurePermission('cash.open');

        $sessionId = $this->editableCuadreSessionId();

        if (! $sessionId || $this->selectedPurchasePaymentIds === []) {
            return;
        }

        $session = $this->sessionsQuery()->findOrFail($sessionId);

        try {
            $expenses = $manageCashSessionExpenses->recordFromPurchasePayments(
                $this->currentCompany(),
                $session,
                $this->selectedPurchasePaymentIds,
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->selectedPurchasePaymentIds = [];
        $this->toast($expenses->count() > 1
            ? $expenses->count() . ' compras agregadas como pago de caja.'
            : 'Compra agregada como pago de caja.');
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

