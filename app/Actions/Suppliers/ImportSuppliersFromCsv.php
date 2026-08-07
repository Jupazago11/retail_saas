<?php

namespace App\Actions\Suppliers;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImportSuppliersFromCsv
{
    protected const REQUIRED_HEADERS = [
        'first_name',
    ];

    public function __construct(
        protected CreateSupplier $createSupplier,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, UploadedFile $file, ?User $actor = null): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if (! $handle) {
            throw new InvalidArgumentException('No se pudo abrir el archivo CSV para importacion.');
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers) || $headers === []) {
            fclose($handle);

            throw new InvalidArgumentException('El archivo CSV no contiene encabezados validos.');
        }

        $normalizedHeaders = collect($headers)
            ->map(fn ($header) => $this->normalizeHeader($header))
            ->all();

        $missingHeaders = collect(self::REQUIRED_HEADERS)
            ->reject(fn (string $header) => in_array($header, $normalizedHeaders, true))
            ->values()
            ->all();

        if ($missingHeaders !== []) {
            fclose($handle);

            throw new InvalidArgumentException('Faltan columnas obligatorias: '.implode(', ', $missingHeaders).'.');
        }

        $rowNumber = 1;
        $createdCount = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $payload = $this->combineRow($normalizedHeaders, $row);

            try {
                $attributes = $this->mapRowToAttributes($company, $payload);
                $this->createSupplier->handle($company, $attributes);
                $createdCount++;
            } catch (InvalidArgumentException $exception) {
                $errors[] = 'Fila '.$rowNumber.': '.$exception->getMessage();
            }
        }

        fclose($handle);

        $summary = [
            'file_name' => $file->getClientOriginalName(),
            'created_count' => $createdCount,
            'error_count' => count($errors),
            'errors' => $errors,
        ];

        $this->auditLogger->logSnapshot(
            $company,
            'suppliers.imported',
            Company::class,
            $company->id,
            null,
            $summary,
            $actor,
        );

        return $summary;
    }

    protected function mapRowToAttributes(Company $company, array $row): array
    {
        $firstName = $this->blankToNull($row['first_name'] ?? null);

        if ($firstName === null) {
            throw new InvalidArgumentException('El proveedor debe tener al menos un nombre.');
        }

        $documentNumber = $this->blankToNull($row['document_number'] ?? null);
        $email = $this->blankToNull($row['email'] ?? null);

        if ($documentNumber !== null && $this->documentAlreadyExists($company, $documentNumber)) {
            throw new InvalidArgumentException('Ya existe un proveedor con el mismo documento.');
        }

        if ($email !== null && $this->emailAlreadyExists($company, $email)) {
            throw new InvalidArgumentException('Ya existe un proveedor con el mismo correo.');
        }

        return [
            'document_type' => $this->blankToNull($row['document_type'] ?? null),
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $this->blankToNull($row['last_name'] ?? null),
            'phone' => $this->blankToNull($row['phone'] ?? null),
            'email' => $email,
            'status' => $this->normalizeStatus($row['status'] ?? null),
            'payment_term_days' => $this->normalizePaymentTermDays($row['payment_term_days'] ?? null),
            'notes' => $this->blankToNull($row['notes'] ?? null),
        ];
    }

    protected function documentAlreadyExists(Company $company, string $documentNumber): bool
    {
        return Supplier::query()
            ->where('company_id', $company->id)
            ->whereHas('person', fn ($query) => $query->where('document_number', $documentNumber))
            ->exists();
    }

    protected function emailAlreadyExists(Company $company, string $email): bool
    {
        return Supplier::query()
            ->where('company_id', $company->id)
            ->whereHas('person', fn ($query) => $query->whereRaw('lower(email) = ?', [mb_strtolower($email)]))
            ->exists();
    }

    protected function normalizeStatus(?string $value): string
    {
        $value = strtolower((string) $this->blankToNull($value));

        return match ($value) {
            'inactive', 'inactivo' => 'inactive',
            default => 'active',
        };
    }

    protected function normalizePaymentTermDays(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('El plazo del proveedor debe ser un entero valido.');
        }

        if ((int) $value < 0) {
            throw new InvalidArgumentException('El plazo del proveedor no puede ser negativo.');
        }

        return $value;
    }

    protected function combineRow(array $headers, array $row): array
    {
        $normalizedRow = array_pad($row, count($headers), null);

        return collect($headers)
            ->mapWithKeys(fn (string $header, int $index) => [$header => $this->blankToNull($normalizedRow[$index] ?? null)])
            ->all();
    }

    protected function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => $this->blankToNull($value) === null);
    }

    protected function normalizeHeader(mixed $value): string
    {
        return strtolower(trim((string) $value));
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
