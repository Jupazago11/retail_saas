<?php

namespace App\Actions\Cash;

use App\Actions\Cash\Concerns\RecomputesClosedSessionTotals;
use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\CashSessionFund;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManageCashSessionFunds
{
    use RecomputesClosedSessionTotals;

    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    public function add(Company $company, CashSession $cashSession, array $attributes, User $actor): CashSessionFund
    {
        if ($cashSession->company_id !== $company->id) {
            throw new InvalidArgumentException('La sesion de caja no pertenece a la empresa indicada.');
        }

        $label = trim((string) ($attributes['label'] ?? ''));
        $amount = $this->normalizeAmount($attributes['amount'] ?? null);

        if ($label === '') {
            throw new InvalidArgumentException('Describe de donde viene esta base.');
        }

        return DB::transaction(function () use ($company, $cashSession, $label, $amount, $actor) {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);
            $this->ensureCanModify($cashSession, $actor);

            $fund = CashSessionFund::query()->create([
                'company_id' => $company->id,
                'cash_session_id' => $cashSession->id,
                'label' => $label,
                'amount' => $amount,
                'created_by' => $actor->id,
            ]);

            $this->syncSessionAfterFundChange($cashSession);
            $this->auditLogger->logCreated($company, 'cash_session_fund.created', $fund, $actor);

            return $fund;
        });
    }

    public function update(Company $company, CashSessionFund $fund, array $attributes, User $actor): CashSessionFund
    {
        if ($fund->company_id !== $company->id) {
            throw new InvalidArgumentException('La base no pertenece a la empresa indicada.');
        }

        return DB::transaction(function () use ($company, $fund, $attributes, $actor) {
            $fund = CashSessionFund::query()->lockForUpdate()->findOrFail($fund->id);
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($fund->cash_session_id);
            $this->ensureCanModify($cashSession, $actor);

            $before = $fund->withoutRelations()->attributesToArray();

            $updates = [];

            if (array_key_exists('label', $attributes)) {
                $label = trim((string) $attributes['label']);

                if ($label === '') {
                    throw new InvalidArgumentException('Describe de donde viene esta base.');
                }

                $updates['label'] = $label;
            }

            if (array_key_exists('amount', $attributes)) {
                $updates['amount'] = $this->normalizeAmount($attributes['amount']);
            }

            $fund->update($updates);

            $this->syncSessionAfterFundChange($cashSession);
            $this->auditLogger->logSnapshot(
                $company,
                'cash_session_fund.updated',
                CashSessionFund::class,
                $fund->id,
                $before,
                $fund->fresh()->withoutRelations()->attributesToArray(),
                $actor,
            );

            return $fund->fresh();
        });
    }

    public function delete(Company $company, CashSessionFund $fund, User $actor): void
    {
        if ($fund->company_id !== $company->id) {
            throw new InvalidArgumentException('La base no pertenece a la empresa indicada.');
        }

        DB::transaction(function () use ($company, $fund, $actor) {
            $fund = CashSessionFund::query()->lockForUpdate()->findOrFail($fund->id);
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($fund->cash_session_id);
            $this->ensureCanModify($cashSession, $actor);

            if (CashSessionFund::query()->where('cash_session_id', $cashSession->id)->count() <= 1) {
                throw new InvalidArgumentException('La sesion debe conservar al menos una base.');
            }

            $beforeSnapshot = $fund->withoutRelations()->attributesToArray();
            $fund->delete();

            $this->syncSessionAfterFundChange($cashSession);
            $this->auditLogger->logSnapshot(
                $company,
                'cash_session_fund.deleted',
                CashSessionFund::class,
                $fund->id,
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

        throw new InvalidArgumentException('Esta sesion ya esta cerrada. Solo un administrador de la empresa puede corregir sus bases.');
    }

    protected function syncSessionAfterFundChange(CashSession $cashSession): void
    {
        if (in_array($cashSession->status, [CashSessionStatus::Closed->value, CashSessionStatus::Reconciled->value], true)) {
            $this->recomputeClosedTotalsIfNeeded($cashSession);

            return;
        }

        $total = CashSessionFund::query()
            ->where('cash_session_id', $cashSession->id)
            ->sum('amount');

        $cashSession->update(['opening_amount' => $total]);
    }

    protected function normalizeAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('El valor de la base es invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El valor de la base debe ser mayor a cero.');
        }

        return $normalized;
    }
}
