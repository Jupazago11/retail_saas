<?php

namespace App\Services\Credit;

use App\Enums\CreditAccountStatus;
use App\Enums\CreditMovementType;
use App\Models\CreditAccount;
use App\Models\CreditMovement;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditLedger
{
    public function recordSaleCharge(CreditAccount $account, Sale $sale, string $amount, ?string $notes = null): CreditMovement
    {
        return $this->apply($account, $sale, CreditMovementType::SaleCharge, $amount, $notes);
    }

    public function recordPayment(CreditAccount $account, string $amount, ?string $notes = null): CreditMovement
    {
        return $this->apply($account, null, CreditMovementType::Payment, $amount, $notes);
    }

    public function recordSaleReturnAdjustment(CreditAccount $account, Sale $sale, string $amount, ?string $notes = null): CreditMovement
    {
        return $this->apply($account, $sale, CreditMovementType::SaleReturnAdjustment, $amount, $notes);
    }

    public function recordSaleCancellationAdjustment(CreditAccount $account, Sale $sale, string $amount, ?string $notes = null): CreditMovement
    {
        return $this->apply($account, $sale, CreditMovementType::SaleCancellationAdjustment, $amount, $notes);
    }

    public function outstandingForSale(Sale $sale): string
    {
        $movements = CreditMovement::query()
            ->where('sale_id', $sale->id)
            ->get(['movement_type', 'amount']);

        return $movements->reduce(function (string $carry, CreditMovement $movement) {
            return $this->direction($movement->movement_type) === 1
                ? bcadd($carry, (string) $movement->amount, 2)
                : bcsub($carry, (string) $movement->amount, 2);
        }, '0.00');
    }

    public function hasOverdueBalance(CreditAccount $account, ?CarbonInterface $asOf = null): bool
    {
        $asOf ??= now();

        return Sale::query()
            ->select('sales.id')
            ->leftJoin('credit_movements', 'credit_movements.sale_id', '=', 'sales.id')
            ->where('sales.company_id', $account->company_id)
            ->where('sales.credit_account_id', $account->id)
            ->where('sales.sale_type', 'credit')
            ->whereNotNull('sales.credit_due_at')
            ->where('sales.credit_due_at', '<', $asOf)
            ->groupBy('sales.id')
            ->havingRaw(
                "COALESCE(SUM(CASE WHEN credit_movements.movement_type = ? THEN credit_movements.amount ELSE -credit_movements.amount END), 0) > 0",
                [CreditMovementType::SaleCharge->value]
            )
            ->exists();
    }

    protected function apply(
        CreditAccount $account,
        ?Sale $sale,
        CreditMovementType $movementType,
        string $amount,
        ?string $notes = null,
    ): CreditMovement {
        $amount = $this->normalizeAmount($amount);

        return DB::transaction(function () use ($account, $sale, $movementType, $amount, $notes) {
            $account = CreditAccount::query()
                ->lockForUpdate()
                ->findOrFail($account->id);

            if ($sale !== null) {
                if ($account->company_id !== $sale->company_id) {
                    throw new InvalidArgumentException('La cuenta de credito no pertenece a la empresa de la venta.');
                }

                if ($sale->credit_account_id && $sale->credit_account_id !== $account->id) {
                    throw new InvalidArgumentException('La venta no pertenece a la cuenta de credito indicada.');
                }
            }

            if ($movementType === CreditMovementType::SaleCharge && $account->status !== CreditAccountStatus::Active->value) {
                throw new InvalidArgumentException('La cuenta de credito no esta disponible para nuevos cargos.');
            }

            if ($movementType === CreditMovementType::SaleCharge && bccomp((string) $account->available_credit, $amount, 2) === -1) {
                throw new InvalidArgumentException('El cliente no tiene cupo disponible suficiente.');
            }

            if ($sale !== null && $this->direction($movementType->value) === -1) {
                $outstanding = $this->outstandingForSale($sale);

                if (bccomp($outstanding, $amount, 2) === -1) {
                    throw new InvalidArgumentException('El movimiento excede el saldo pendiente de la venta.');
                }
            }

            $currentBalance = (string) $account->balance_due;
            $newBalance = $this->direction($movementType->value) === 1
                ? bcadd($currentBalance, $amount, 2)
                : bcsub($currentBalance, $amount, 2);

            if (bccomp($newBalance, '0.00', 2) === -1) {
                throw new InvalidArgumentException('El movimiento excede el saldo total de la cuenta.');
            }

            $account->update([
                'balance_due' => $newBalance,
                'available_credit' => bcsub((string) $account->credit_limit, $newBalance, 2),
            ]);

            return CreditMovement::query()->create([
                'company_id' => $account->company_id,
                'credit_account_id' => $account->id,
                'sale_id' => $sale?->id,
                'movement_type' => $movementType->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'notes' => $notes,
                'occurred_at' => now(),
            ]);
        });
    }

    protected function direction(string $movementType): int
    {
        return match ($movementType) {
            CreditMovementType::SaleCharge->value => 1,
            CreditMovementType::Payment->value,
            CreditMovementType::SaleReturnAdjustment->value,
            CreditMovementType::SaleCancellationAdjustment->value => -1,
            default => throw new InvalidArgumentException('Tipo de movimiento de credito no soportado.'),
        };
    }

    protected function normalizeAmount(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto de credito invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El monto de credito debe ser mayor a cero.');
        }

        return $normalized;
    }
}
