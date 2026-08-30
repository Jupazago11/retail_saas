<?php

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashSessionFund;
use App\Models\Company;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\CompanySettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpenCashSession
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, array $attributes): CashSession
    {
        $branch = $this->resolveBranch($company, (int) ($attributes['branch_id'] ?? 0));
        $cashRegister = $this->resolveCashRegister($company, $branch, (int) ($attributes['cash_register_id'] ?? 0));
        $opener = $this->resolveUser($company, (int) ($attributes['opened_by'] ?? 0));
        $funds = $this->resolveFunds($attributes);

        if ($funds === []) {
            $funds = [['label' => 'Base inicial', 'amount' => $this->resolveOpeningAmount($company, $attributes)]];
        }

        $openingAmount = array_reduce($funds, fn (string $carry, array $fund) => bcadd($carry, $fund['amount'], 2), '0.00');
        $openedAt = $this->resolveOpenedAt($attributes);

        return DB::transaction(function () use ($company, $branch, $cashRegister, $opener, $openingAmount, $funds, $openedAt) {
            $existing = CashSession::query()
                ->where('company_id', $company->id)
                ->where('cash_register_id', $cashRegister->id)
                ->where('status', CashSessionStatus::Open->value)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new InvalidArgumentException('La caja ya tiene una sesion abierta.');
            }

            $lastSequence = (int) CashSession::query()
                ->where('company_id', $company->id)
                ->orderByDesc('company_sequence')
                ->lockForUpdate()
                ->value('company_sequence');
            $nextSequence = $lastSequence + 1;

            $cashSession = CashSession::query()->create([
                'company_id' => $company->id,
                'company_sequence' => $nextSequence,
                'branch_id' => $branch->id,
                'cash_register_id' => $cashRegister->id,
                'opened_by' => $opener->id,
                'status' => CashSessionStatus::Open->value,
                'opening_amount' => $openingAmount,
                'closing_expected_amount' => $openingAmount,
                'opened_at' => $openedAt,
            ]);

            foreach ($funds as $fund) {
                CashSessionFund::query()->create([
                    'company_id' => $company->id,
                    'cash_session_id' => $cashSession->id,
                    'label' => $fund['label'],
                    'amount' => $fund['amount'],
                    'created_by' => $opener->id,
                ]);
            }

            $cashSession = $cashSession->fresh(['branch', 'cashRegister', 'opener', 'funds']);

            $this->auditLogger->logCreated($company, 'cash_session.opened', $cashSession, $opener);

            return $cashSession;
        });
    }

    /**
     * Por defecto una caja se abre "ahora", pero el modulo de caja tambien
     * permite crear el cuadre de un dia pasado que se quedo sin registrar
     * (ver CashSessionsPage::startCreateForHistoryDate()) — en ese caso se
     * pide una fecha concreta y se le pega la hora actual, para no perder
     * el orden cronologico si ese dia termina con varias sesiones.
     */
    protected function resolveOpenedAt(array $attributes): \Illuminate\Support\Carbon
    {
        if (! array_key_exists('opened_at', $attributes) || $attributes['opened_at'] === null || $attributes['opened_at'] === '') {
            return now();
        }

        $date = \Illuminate\Support\Carbon::parse($attributes['opened_at']);

        if (! $date->isToday()) {
            $date->setTimeFromTimeString(now()->format('H:i:s'));
        }

        return $date;
    }

    protected function resolveBranch(Company $company, int $branchId): Branch
    {
        return Branch::query()
            ->where('company_id', $company->id)
            ->findOrFail($branchId);
    }

    protected function resolveCashRegister(Company $company, Branch $branch, int $cashRegisterId): CashRegister
    {
        return CashRegister::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->findOrFail($cashRegisterId);
    }

    protected function resolveUser(Company $company, int $userId): User
    {
        return User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->findOrFail($userId);
    }

    protected function resolveFunds(array $attributes): array
    {
        if (! array_key_exists('funds', $attributes) || ! is_array($attributes['funds'])) {
            return [];
        }

        $funds = [];

        foreach ($attributes['funds'] as $fund) {
            $label = trim((string) ($fund['label'] ?? ''));
            $amount = $this->normalizeOpeningAmount($fund['amount'] ?? '0');

            if ($label === '' || bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }

            $funds[] = ['label' => $label, 'amount' => $amount];
        }

        return $funds;
    }

    protected function resolveOpeningAmount(Company $company, array $attributes): string
    {
        if (array_key_exists('opening_amount', $attributes)) {
            return $this->normalizeOpeningAmount($attributes['opening_amount']);
        }

        if (! $this->companySettings->get($company, 'cash', 'opening_required')) {
            return '0.00';
        }

        return $this->normalizeOpeningAmount(
            $this->companySettings->get($company, 'cash', 'default_opening_amount')
        );
    }

    protected function normalizeOpeningAmount(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto de apertura invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0', 2) < 0) {
            throw new InvalidArgumentException('El monto de apertura no puede ser negativo.');
        }

        return $normalized;
    }
}
