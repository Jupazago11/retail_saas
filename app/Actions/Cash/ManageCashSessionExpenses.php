<?php

namespace App\Actions\Cash;

use App\Actions\Cash\Concerns\RecomputesClosedSessionTotals;
use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\CashSessionExpense;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManageCashSessionExpenses
{
    use RecomputesClosedSessionTotals;

    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function record(Company $company, CashSession $cashSession, array $attributes, User $actor): CashSessionExpense
    {
        if ($cashSession->company_id !== $company->id) {
            throw new InvalidArgumentException('La sesion de caja no pertenece a la empresa indicada.');
        }

        $description = trim((string) ($attributes['description'] ?? ''));
        $amount = $this->normalizeAmount($attributes['amount'] ?? null);

        if ($description === '') {
            throw new InvalidArgumentException('Describe en que se uso el dinero.');
        }

        return DB::transaction(function () use ($company, $cashSession, $description, $amount, $actor) {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);
            $this->ensureCanModify($cashSession, $actor);

            $lastSequence = (int) CashSessionExpense::query()
                ->where('company_id', $company->id)
                ->orderByDesc('company_sequence')
                ->lockForUpdate()
                ->value('company_sequence');

            $expense = CashSessionExpense::query()->create([
                'company_id' => $company->id,
                'company_sequence' => $lastSequence + 1,
                'cash_session_id' => $cashSession->id,
                'description' => $description,
                'amount' => $amount,
                'created_by' => $actor->id,
            ]);

            $this->recomputeClosedTotalsIfNeeded($cashSession);
            $this->auditLogger->logCreated($company, 'cash_session_expense.created', $expense, $actor);

            return $expense;
        });
    }

    public function delete(Company $company, CashSessionExpense $expense, User $actor): void
    {
        if ($expense->company_id !== $company->id) {
            throw new InvalidArgumentException('El pago de caja no pertenece a la empresa indicada.');
        }

        DB::transaction(function () use ($company, $expense, $actor) {
            $expense = CashSessionExpense::query()->lockForUpdate()->findOrFail($expense->id);
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($expense->cash_session_id);
            $this->ensureCanModify($cashSession, $actor);

            $beforeSnapshot = $expense->withoutRelations()->attributesToArray();
            $expense->delete();

            $this->recomputeClosedTotalsIfNeeded($cashSession);
            $this->auditLogger->logSnapshot(
                $company,
                'cash_session_expense.deleted',
                CashSessionExpense::class,
                $expense->id,
                $beforeSnapshot,
                null,
                $actor,
            );
        });
    }

    protected function ensureCanModify(CashSession $cashSession, User $actor): void
    {
        if ($cashSession->status === CashSessionStatus::Open->value) {
            return;
        }

        if ($actor->hasCurrentCompanyPermission('settings.manage')) {
            return;
        }

        throw new InvalidArgumentException('Esta sesion ya esta cerrada. Solo un administrador de la empresa puede corregir sus pagos de caja.');
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('El valor del pago de caja es invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El valor del pago de caja debe ser mayor a cero.');
        }

        return $normalized;
    }
}
