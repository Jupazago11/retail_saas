<?php

namespace App\Actions\Customers;

use App\Enums\CreditAccountStatus;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\RecordStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Person;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateCustomer
{
    public function handle(Company $company, array $attributes): Customer
    {
        $firstName = $this->blankToNull($attributes['first_name'] ?? null);

        if ($firstName === null) {
            throw new InvalidArgumentException('El cliente debe tener al menos un nombre.');
        }

        $creditEnabled = (bool) ($attributes['credit_enabled'] ?? false);
        $loyaltyEnabled = (bool) ($attributes['loyalty_enabled'] ?? false);
        $creditLimit = $this->normalizeMoney($attributes['credit_limit'] ?? '0');

        if (bccomp($creditLimit, '0.00', 2) === 1) {
            $creditEnabled = true;
        }

        return DB::transaction(function () use ($company, $attributes, $firstName, $creditEnabled, $loyaltyEnabled, $creditLimit) {
            $person = Person::query()->create([
                'document_type' => $this->blankToNull($attributes['document_type'] ?? null),
                'document_number' => $this->blankToNull($attributes['document_number'] ?? null),
                'first_name' => $firstName,
                'last_name' => $this->blankToNull($attributes['last_name'] ?? null),
                'phone' => $this->blankToNull($attributes['phone'] ?? null),
                'email' => $this->blankToNull($attributes['email'] ?? null),
            ]);

            $customer = Customer::query()->create([
                'company_id' => $company->id,
                'person_id' => $person->id,
                'status' => $attributes['status'] ?? RecordStatus::Active->value,
                'credit_enabled' => $creditEnabled,
                'loyalty_enabled' => $loyaltyEnabled,
            ]);

            if ($creditEnabled) {
                $customer->creditAccount()->create([
                    'company_id' => $company->id,
                    'status' => CreditAccountStatus::Active->value,
                    'credit_limit' => $creditLimit,
                    'available_credit' => $creditLimit,
                    'balance_due' => '0.00',
                ]);
            }

            if ($loyaltyEnabled) {
                $customer->loyaltyAccount()->create([
                    'company_id' => $company->id,
                    'status' => LoyaltyAccountStatus::Active->value,
                    'points_balance' => '0.0000',
                ]);
            }

            return $customer->fresh(['person', 'creditAccount', 'loyaltyAccount']);
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

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
