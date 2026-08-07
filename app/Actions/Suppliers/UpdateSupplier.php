<?php

namespace App\Actions\Suppliers;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateSupplier
{
    public function handle(Company $company, Supplier $supplier, array $attributes): Supplier
    {
        if ($supplier->company_id !== $company->id) {
            throw new InvalidArgumentException('El proveedor no pertenece a la empresa indicada.');
        }

        $firstName = $this->blankToNull($attributes['first_name'] ?? null);

        if ($firstName === null) {
            throw new InvalidArgumentException('El proveedor debe tener al menos un nombre.');
        }

        return DB::transaction(function () use ($supplier, $attributes, $firstName) {
            $supplier->person()->update([
                'document_type' => $this->blankToNull($attributes['document_type'] ?? null),
                'document_number' => $this->blankToNull($attributes['document_number'] ?? null),
                'first_name' => $firstName,
                'last_name' => $this->blankToNull($attributes['last_name'] ?? null),
                'phone' => $this->blankToNull($attributes['phone'] ?? null),
                'email' => $this->blankToNull($attributes['email'] ?? null),
            ]);

            $supplier->update([
                'status' => $attributes['status'] ?? $supplier->status,
                'payment_term_days' => $this->normalizePaymentTermDays($attributes['payment_term_days'] ?? null),
                'notes' => $this->blankToNull($attributes['notes'] ?? null),
            ]);

            return $supplier->fresh('person');
        });
    }

    protected function normalizePaymentTermDays(mixed $value): ?int
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('El plazo del proveedor debe ser un entero valido.');
        }

        $days = (int) $value;

        if ($days < 0) {
            throw new InvalidArgumentException('El plazo del proveedor no puede ser negativo.');
        }

        return $days;
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
