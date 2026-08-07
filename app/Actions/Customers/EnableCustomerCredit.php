<?php

namespace App\Actions\Customers;

use App\Enums\CreditAccountStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnableCustomerCredit
{
    public function handle(Company $company, Customer $customer, string $creditLimit): Customer
    {
        if ($customer->company_id !== $company->id) {
            throw new InvalidArgumentException('El cliente no pertenece a la empresa indicada.');
        }

        $normalizedLimit = $this->normalizeMoney($creditLimit);

        return DB::transaction(function () use ($customer, $company, $normalizedLimit) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($customer->creditAccount()->exists()) {
                throw new InvalidArgumentException('Este cliente ya tiene una cuenta de credito.');
            }

            $customer->update(['credit_enabled' => true]);

            $customer->creditAccount()->create([
                'company_id' => $company->id,
                'status' => CreditAccountStatus::Active->value,
                'credit_limit' => $normalizedLimit,
                'available_credit' => $normalizedLimit,
                'balance_due' => '0.00',
            ]);

            return $customer->fresh(['person', 'creditAccount']);
        });
    }

    protected function normalizeMoney(mixed $value): string
    {
        $value = trim((string) $value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) === -1) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        return $normalized;
    }
}
