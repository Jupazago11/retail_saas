<?php

namespace App\Actions\Customers;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateCustomer
{
    public function handle(Company $company, Customer $customer, array $attributes): Customer
    {
        if ($customer->company_id !== $company->id) {
            throw new InvalidArgumentException('El cliente no pertenece a la empresa indicada.');
        }

        $firstName = $this->blankToNull($attributes['first_name'] ?? null);

        if ($firstName === null) {
            throw new InvalidArgumentException('El cliente debe tener al menos un nombre.');
        }

        return DB::transaction(function () use ($customer, $attributes, $firstName) {
            $customer->person()->update([
                'document_type' => $this->blankToNull($attributes['document_type'] ?? null),
                'document_number' => $this->blankToNull($attributes['document_number'] ?? null),
                'first_name' => $firstName,
                'last_name' => $this->blankToNull($attributes['last_name'] ?? null),
                'phone' => $this->blankToNull($attributes['phone'] ?? null),
                'email' => $this->blankToNull($attributes['email'] ?? null),
            ]);

            $customer->update([
                'status' => $attributes['status'] ?? $customer->status,
            ]);

            return $customer->fresh('person');
        });
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
