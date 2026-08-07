<?php

namespace App\Actions\Customers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImportCustomersFromCsv
{
    protected const REQUIRED_HEADERS = [
        'first_name',
    ];

    public function __construct(
        protected CreateCustomer $createCustomer,
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
                $this->createCustomer->handle($company, $attributes);
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
            'customers.imported',
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
            throw new InvalidArgumentException('El cliente debe tener al menos un nombre.');
        }

        $documentNumber = $this->blankToNull($row['document_number'] ?? null);
        $email = $this->blankToNull($row['email'] ?? null);

        if ($documentNumber !== null && $this->documentAlreadyExists($company, $documentNumber)) {
            throw new InvalidArgumentException('Ya existe un cliente con el mismo documento.');
        }

        if ($email !== null && $this->emailAlreadyExists($company, $email)) {
            throw new InvalidArgumentException('Ya existe un cliente con el mismo correo.');
        }

        return [
            'document_type' => $this->blankToNull($row['document_type'] ?? null),
            'document_number' => $documentNumber,
            'first_name' => $firstName,
            'last_name' => $this->blankToNull($row['last_name'] ?? null),
            'phone' => $this->blankToNull($row['phone'] ?? null),
            'email' => $email,
            'status' => $this->normalizeStatus($row['status'] ?? null),
            'credit_enabled' => $this->normalizeBoolean($row['credit_enabled'] ?? null),
            'loyalty_enabled' => $this->normalizeBoolean($row['loyalty_enabled'] ?? null),
            'credit_limit' => $this->normalizeNullableMoney($row['credit_limit'] ?? null) ?? '0.00',
        ];
    }

    protected function documentAlreadyExists(Company $company, string $documentNumber): bool
    {
        return Customer::query()
            ->where('company_id', $company->id)
            ->whereHas('person', fn ($query) => $query->where('document_number', $documentNumber))
            ->exists();
    }

    protected function emailAlreadyExists(Company $company, string $email): bool
    {
        return Customer::query()
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

    protected function normalizeBoolean(mixed $value): bool
    {
        $value = strtolower((string) $this->blankToNull($value));

        return match ($value) {
            '1', 'true', 'si', 'sí', 'yes' => true,
            default => false,
        };
    }

    protected function normalizeNullableMoney(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Monto invalido.');
        }

        $normalized = bcadd($value, '0', 2);

        if (bccomp($normalized, '0.00', 2) === -1) {
            throw new InvalidArgumentException('El monto no puede ser negativo.');
        }

        return $normalized;
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
