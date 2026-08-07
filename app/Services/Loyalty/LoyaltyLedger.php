<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyMovementType;
use App\Models\Company;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyMovement;
use App\Models\Sale;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Settings\CompanySettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyLedger
{
    public function __construct(
        protected CompanySettings $companySettings,
        protected CompanyPlanResolver $companyPlanResolver,
    ) {
    }

    public function awardForSale(Company $company, LoyaltyAccount $account, Sale $sale): ?LoyaltyMovement
    {
        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            return null;
        }

        if (! $this->companySettings->get($company, 'loyalty', 'loyalty_enabled')) {
            return null;
        }

        $points = $this->calculateEarnedPoints($company, (string) $sale->grand_total);

        if (bccomp($points, '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            $sale,
            LoyaltyMovementType::Earn,
            $points,
            (string) $sale->grand_total,
        );
    }

    public function redeemForSale(Company $company, LoyaltyAccount $account, Sale $sale, string $points): ?LoyaltyMovement
    {
        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitada la feature de fidelizacion.');
        }

        if (! $this->companySettings->get($company, 'loyalty', 'loyalty_enabled')) {
            throw new InvalidArgumentException('La empresa no tiene habilitado el modulo de fidelizacion.');
        }

        $points = $this->normalizePoints($points);
        $cashEquivalent = $this->calculateCashEquivalentForPoints($company, $points);

        return $this->apply(
            $account,
            $sale,
            LoyaltyMovementType::Redeem,
            $points,
            $cashEquivalent,
        );
    }

    public function creditManually(
        Company $company,
        LoyaltyAccount $account,
        string $points,
        ?string $notes = null,
    ): ?LoyaltyMovement {
        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitada la feature de fidelizacion.');
        }

        if (! $this->companySettings->get($company, 'loyalty', 'loyalty_enabled')) {
            throw new InvalidArgumentException('La empresa no tiene habilitado el modulo de fidelizacion.');
        }

        $points = $this->normalizePoints($points);

        if (bccomp($points, '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            null,
            LoyaltyMovementType::ManualCredit,
            $points,
            $this->calculateCashEquivalentForPoints($company, $points),
            $notes,
        );
    }

    public function debitManually(
        Company $company,
        LoyaltyAccount $account,
        string $points,
        ?string $notes = null,
    ): ?LoyaltyMovement {
        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            throw new InvalidArgumentException('El plan actual no tiene habilitada la feature de fidelizacion.');
        }

        if (! $this->companySettings->get($company, 'loyalty', 'loyalty_enabled')) {
            throw new InvalidArgumentException('La empresa no tiene habilitado el modulo de fidelizacion.');
        }

        $points = $this->normalizePoints($points);

        if (bccomp($points, '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            null,
            LoyaltyMovementType::ManualDebit,
            $points,
            $this->calculateCashEquivalentForPoints($company, $points),
            $notes,
        );
    }

    public function reverseForReturnedAmount(
        LoyaltyAccount $account,
        Sale $sale,
        string $returnedAmount,
    ): ?LoyaltyMovement {
        if (bccomp($returnedAmount, '0.00', 2) <= 0) {
            return null;
        }

        $points = $this->calculateReversalPoints($sale, $returnedAmount);

        if (bccomp($points, '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            $sale,
            LoyaltyMovementType::SaleReturnReversal,
            $points,
            $returnedAmount,
        );
    }

    public function restoreRedeemedPointsForReturnedAmount(
        LoyaltyAccount $account,
        Sale $sale,
        string $returnedAmount,
    ): ?LoyaltyMovement {
        if (bccomp($returnedAmount, '0.00', 2) <= 0) {
            return null;
        }

        $summary = $this->redemptionSummaryForSale($sale);

        if (bccomp($summary['available_points'], '0.0000', 4) <= 0) {
            return null;
        }

        $proportionalPoints = bcdiv(
            bcmul($summary['redeemed_points'], $returnedAmount, 6),
            (string) $sale->grand_total,
            4
        );
        $proportionalCash = $this->roundMoney(bcdiv(
            bcmul($summary['redeemed_cash'], $returnedAmount, 6),
            (string) $sale->grand_total,
            6
        ));

        $points = bccomp($proportionalPoints, $summary['available_points'], 4) === 1
            ? $summary['available_points']
            : $proportionalPoints;
        $cashEquivalent = bccomp($proportionalCash, $summary['available_cash'], 2) === 1
            ? $summary['available_cash']
            : $proportionalCash;

        if (bccomp($points, '0.0000', 4) <= 0 || bccomp($cashEquivalent, '0.00', 2) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            $sale,
            LoyaltyMovementType::SaleReturnRedemptionRestore,
            $points,
            $cashEquivalent,
        );
    }

    public function reverseForSaleCancellation(LoyaltyAccount $account, Sale $sale): ?LoyaltyMovement
    {
        $available = $this->netEarnedPointsForSale($sale);

        if (bccomp($available, '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            $sale,
            LoyaltyMovementType::SaleCancellationReversal,
            $available,
            (string) $sale->grand_total,
        );
    }

    public function restoreRedeemedPointsForSaleCancellation(LoyaltyAccount $account, Sale $sale): ?LoyaltyMovement
    {
        $summary = $this->redemptionSummaryForSale($sale);

        if (bccomp($summary['available_points'], '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            $sale,
            LoyaltyMovementType::SaleCancellationRedemptionRestore,
            $summary['available_points'],
            $summary['available_cash'],
        );
    }

    public function expireAvailablePoints(
        Company $company,
        LoyaltyAccount $account,
        CarbonInterface $asOf,
    ): ?LoyaltyMovement {
        if (! $this->companyPlanResolver->hasFeature($company, 'loyalty.enabled')) {
            return null;
        }

        $expirationDays = (int) $this->companySettings->get($company, 'loyalty', 'points_expiration_days');

        if ($expirationDays <= 0) {
            return null;
        }

        $cutoff = $asOf->copy()->subDays($expirationDays);
        $expirablePoints = $this->expirablePointsForAccount($account, $cutoff);

        if (bccomp($expirablePoints, '0.0000', 4) <= 0) {
            return null;
        }

        return $this->apply(
            $account,
            null,
            LoyaltyMovementType::Expiration,
            $expirablePoints,
            '0.00',
            sprintf('Expiracion automatica de puntos con corte %s.', $cutoff->format('Y-m-d H:i:s')),
        );
    }

    public function netEarnedPointsForSale(Sale $sale): string
    {
        $movements = LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->get(['movement_type', 'points']);

        return $movements->reduce(function (string $carry, LoyaltyMovement $movement) {
            return $this->direction($movement->movement_type) === 1
                ? bcadd($carry, (string) $movement->points, 4)
                : bcsub($carry, (string) $movement->points, 4);
        }, '0.0000');
    }

    public function calculateCashEquivalentForPoints(Company $company, string $points): string
    {
        $points = $this->normalizePoints($points);
        $ruleType = (string) $this->companySettings->get($company, 'loyalty', 'points_rule_type');
        $rate = (string) $this->companySettings->get($company, 'loyalty', 'points_rate');

        return match ($ruleType) {
            'per_currency' => $this->roundMoney(bcdiv($points, $rate, 6)),
            default => throw new InvalidArgumentException('La regla de puntos configurada no esta soportada en esta fase.'),
        };
    }

    public function calculatePointsForCashEquivalent(Company $company, string $cashEquivalent): string
    {
        $cashEquivalent = $this->normalizeMoney($cashEquivalent);
        $ruleType = (string) $this->companySettings->get($company, 'loyalty', 'points_rule_type');
        $rate = (string) $this->companySettings->get($company, 'loyalty', 'points_rate');

        return match ($ruleType) {
            'per_currency' => bcmul($cashEquivalent, $rate, 4),
            default => throw new InvalidArgumentException('La regla de puntos configurada no esta soportada en esta fase.'),
        };
    }

    protected function calculateEarnedPoints(Company $company, string $cashEquivalent): string
    {
        $ruleType = (string) $this->companySettings->get($company, 'loyalty', 'points_rule_type');
        $rate = (string) $this->companySettings->get($company, 'loyalty', 'points_rate');

        return match ($ruleType) {
            'per_currency' => bcmul($cashEquivalent, $rate, 4),
            default => throw new InvalidArgumentException('La regla de puntos configurada no esta soportada en esta fase.'),
        };
    }

    protected function calculateReversalPoints(Sale $sale, string $cashEquivalent): string
    {
        $earnedPoints = LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->where('movement_type', LoyaltyMovementType::Earn->value)
            ->sum('points');

        $reversedPoints = LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->whereIn('movement_type', [
                LoyaltyMovementType::SaleReturnReversal->value,
                LoyaltyMovementType::SaleCancellationReversal->value,
            ])
            ->sum('points');

        $available = bcsub((string) $earnedPoints, (string) $reversedPoints, 4);

        if (bccomp($available, '0.0000', 4) <= 0) {
            return '0.0000';
        }

        $proportional = bcdiv(
            bcmul((string) $earnedPoints, $cashEquivalent, 6),
            (string) $sale->grand_total,
            4
        );

        return bccomp($proportional, $available, 4) === 1 ? $available : $proportional;
    }

    protected function apply(
        LoyaltyAccount $account,
        ?Sale $sale,
        LoyaltyMovementType $movementType,
        string $points,
        string $cashEquivalent,
        ?string $notes = null,
    ): LoyaltyMovement {
        $points = $this->normalizePoints($points);
        $cashEquivalent = $this->normalizeMoney($cashEquivalent);

        return DB::transaction(function () use ($account, $sale, $movementType, $points, $cashEquivalent, $notes) {
            $account = LoyaltyAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if ($sale && $account->company_id !== $sale->company_id) {
                throw new InvalidArgumentException('La cuenta de fidelizacion no pertenece a la empresa de la venta.');
            }

            if ($account->status !== LoyaltyAccountStatus::Active->value) {
                throw new InvalidArgumentException('La cuenta de fidelizacion no esta activa.');
            }

            $currentBalance = (string) $account->points_balance;
            $newBalance = $this->direction($movementType->value) === 1
                ? bcadd($currentBalance, $points, 4)
                : bcsub($currentBalance, $points, 4);

            if (bccomp($newBalance, '0.0000', 4) === -1) {
                throw new InvalidArgumentException('El movimiento excede el saldo de puntos disponible.');
            }

            $account->update([
                'points_balance' => $newBalance,
            ]);

            return LoyaltyMovement::query()->create([
                'company_id' => $account->company_id,
                'loyalty_account_id' => $account->id,
                'sale_id' => $sale?->id,
                'movement_type' => $movementType->value,
                'points' => $points,
                'cash_equivalent' => $cashEquivalent,
                'balance_after' => $newBalance,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);
        });
    }

    protected function direction(string $movementType): int
    {
        return match ($movementType) {
            LoyaltyMovementType::Earn->value,
            LoyaltyMovementType::ManualCredit->value,
            LoyaltyMovementType::SaleReturnRedemptionRestore->value,
            LoyaltyMovementType::SaleCancellationRedemptionRestore->value => 1,
            LoyaltyMovementType::Redeem->value,
            LoyaltyMovementType::ManualDebit->value,
            LoyaltyMovementType::Expiration->value,
            LoyaltyMovementType::SaleReturnReversal->value,
            LoyaltyMovementType::SaleCancellationReversal->value => -1,
            default => throw new InvalidArgumentException('Tipo de movimiento de fidelizacion no soportado.'),
        };
    }

    protected function redemptionSummaryForSale(Sale $sale): array
    {
        $redeemedPoints = (string) LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->where('movement_type', LoyaltyMovementType::Redeem->value)
            ->sum('points');
        $redeemedCash = (string) LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->where('movement_type', LoyaltyMovementType::Redeem->value)
            ->sum('cash_equivalent');
        $restoredPoints = (string) LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->whereIn('movement_type', [
                LoyaltyMovementType::SaleReturnRedemptionRestore->value,
                LoyaltyMovementType::SaleCancellationRedemptionRestore->value,
            ])
            ->sum('points');
        $restoredCash = (string) LoyaltyMovement::query()
            ->where('sale_id', $sale->id)
            ->whereIn('movement_type', [
                LoyaltyMovementType::SaleReturnRedemptionRestore->value,
                LoyaltyMovementType::SaleCancellationRedemptionRestore->value,
            ])
            ->sum('cash_equivalent');

        return [
            'redeemed_points' => bcadd($redeemedPoints, '0', 4),
            'redeemed_cash' => bcadd($redeemedCash, '0', 2),
            'available_points' => bcsub((string) $redeemedPoints, (string) $restoredPoints, 4),
            'available_cash' => bcsub((string) $redeemedCash, (string) $restoredCash, 2),
        ];
    }

    protected function expirablePointsForAccount(LoyaltyAccount $account, CarbonInterface $cutoff): string
    {
        $lots = $this->availableLotsForAccount($account);

        return collect($lots)->reduce(function (string $carry, array $lot) use ($cutoff) {
            if ($lot['occurred_at']->gt($cutoff)) {
                return $carry;
            }

            return bcadd($carry, $lot['points'], 4);
        }, '0.0000');
    }

    protected function availableLotsForAccount(LoyaltyAccount $account): array
    {
        $movements = LoyaltyMovement::query()
            ->where('loyalty_account_id', $account->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $lots = [];

        foreach ($movements as $movement) {
            $points = bcadd((string) $movement->points, '0', 4);

            if ($this->direction($movement->movement_type) === 1) {
                $lots[] = [
                    'points' => $points,
                    'occurred_at' => $movement->occurred_at ?? $movement->created_at,
                ];

                continue;
            }

            $remaining = $points;

            foreach ($lots as $index => $lot) {
                if (bccomp($remaining, '0.0000', 4) <= 0) {
                    break;
                }

                if (bccomp($lot['points'], '0.0000', 4) <= 0) {
                    continue;
                }

                if (bccomp($lot['points'], $remaining, 4) === 1) {
                    $lots[$index]['points'] = bcsub($lot['points'], $remaining, 4);
                    $remaining = '0.0000';

                    break;
                }

                $remaining = bcsub($remaining, $lot['points'], 4);
                $lots[$index]['points'] = '0.0000';
            }
        }

        return array_values(array_filter($lots, fn (array $lot) => bccomp($lot['points'], '0.0000', 4) === 1));
    }

    protected function normalizePoints(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Cantidad de puntos invalida.');
        }

        $normalized = bcadd($value, '0', 4);

        if (bccomp($normalized, '0.0000', 4) <= 0) {
            throw new InvalidArgumentException('Los puntos deben ser mayores a cero.');
        }

        return $normalized;
    }

    protected function normalizeMoney(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Valor monetario invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) < 0) {
            throw new InvalidArgumentException('El valor monetario no puede ser negativo.');
        }

        return $normalized;
    }

    protected function roundMoney(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
