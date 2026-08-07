<?php

namespace App\Actions\Products;

use App\Enums\RecordStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Plans\CompanyOperationalLimitGuard;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImportProductsFromCsv
{
    protected const REQUIRED_HEADERS = [
        'name',
        'category_code',
        'unit_code',
        'cost',
        'price_1',
    ];

    public function __construct(
        protected CompanyOperationalLimitGuard $companyOperationalLimitGuard,
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
        $errorCount = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $payload = $this->combineRow($normalizedHeaders, $row);

            try {
                $this->companyOperationalLimitGuard->ensureCanCreateProduct($company);
                $attributes = $this->mapRowToAttributes($company, $payload);

                Product::query()->create([
                    'company_id' => $company->id,
                    'status' => $attributes['status'],
                    ...$attributes,
                ]);

                $createdCount++;
            } catch (InvalidArgumentException $exception) {
                $errorCount++;
                $errors[] = 'Fila '.$rowNumber.': '.$exception->getMessage();
            }
        }

        fclose($handle);

        $summary = [
            'file_name' => $file->getClientOriginalName(),
            'created_count' => $createdCount,
            'error_count' => $errorCount,
            'errors' => $errors,
        ];

        $this->auditLogger->logSnapshot(
            $company,
            'products.imported',
            Company::class,
            $company->id,
            null,
            $summary,
            $actor,
        );

        return $summary;
    }

    protected function combineRow(array $headers, array $row): array
    {
        $normalizedRow = array_pad($row, count($headers), null);

        return collect($headers)
            ->mapWithKeys(fn (string $header, int $index) => [$header => $this->blankToNull($normalizedRow[$index] ?? null)])
            ->all();
    }

    protected function mapRowToAttributes(Company $company, array $row): array
    {
        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('El nombre del producto es obligatorio.');
        }

        $category = Category::query()
            ->where('company_id', $company->id)
            ->whereRaw('upper(code) = ?', [mb_strtoupper((string) ($row['category_code'] ?? ''))])
            ->whereNull('deleted_at')
            ->first();

        if (! $category) {
            throw new InvalidArgumentException('La categoria indicada no existe o no esta activa en la empresa.');
        }

        $unit = Unit::query()
            ->where('company_id', $company->id)
            ->whereRaw('upper(code) = ?', [mb_strtoupper((string) ($row['unit_code'] ?? ''))])
            ->first();

        if (! $unit) {
            throw new InvalidArgumentException('La unidad indicada no existe en la empresa.');
        }

        $brand = $this->resolveBrand($company, $row['brand_name'] ?? null);
        $sku = $this->normalizeOptionalUpper($row['sku'] ?? null);
        $barcode = $this->blankToNull($row['barcode'] ?? null);
        $cost = $this->normalizeDecimal($row['cost'] ?? null, 'El costo es obligatorio.');
        $price1 = $this->normalizeDecimal($row['price_1'] ?? null, 'El precio 1 es obligatorio.');
        $price2 = $this->normalizeNullableDecimal($row['price_2'] ?? null);
        $price3 = $this->normalizeNullableDecimal($row['price_3'] ?? null);
        $minimumStock = $this->normalizeNullableDecimal($row['minimum_stock'] ?? null, 3) ?? '0.000';

        if ($sku !== null && Product::query()->where('company_id', $company->id)->where('sku', $sku)->exists()) {
            throw new InvalidArgumentException('Ya existe otro producto con el mismo SKU.');
        }

        if ($barcode !== null && Product::query()->where('company_id', $company->id)->where('barcode', $barcode)->exists()) {
            throw new InvalidArgumentException('Ya existe otro producto con el mismo codigo de barras.');
        }

        return [
            'category_id' => $category->id,
            'brand_id' => $brand?->id,
            'base_unit_id' => $unit->id,
            'tax_id' => $this->blankToNull($row['tax_id'] ?? null),
            'name' => $name,
            'sku' => $sku,
            'barcode' => $barcode,
            'description' => $this->blankToNull($row['description'] ?? null),
            'cost' => $cost,
            'price_1' => $price1,
            'price_2' => $price2,
            'price_3' => $price3,
            'margin_1' => $this->marginFrom($cost, $price1),
            'margin_2' => $this->marginFrom($cost, $price2),
            'margin_3' => $this->marginFrom($cost, $price3),
            'tracks_inventory' => $this->parseBoolean($row['tracks_inventory'] ?? null),
            'minimum_stock' => $minimumStock,
            'status' => $this->parseStatus($row['status'] ?? null),
        ];
    }

    protected function resolveBrand(Company $company, ?string $brandName): ?Brand
    {
        $brandName = $this->blankToNull($brandName);

        if ($brandName === null) {
            return null;
        }

        $brand = Brand::query()
            ->where('company_id', $company->id)
            ->whereRaw('upper(name) = ?', [mb_strtoupper($brandName)])
            ->whereNull('deleted_at')
            ->first();

        if (! $brand) {
            throw new InvalidArgumentException('La marca indicada no existe o no esta activa en la empresa.');
        }

        return $brand;
    }

    protected function parseBoolean(?string $value): bool
    {
        $value = strtolower((string) $this->blankToNull($value));

        return match ($value) {
            '0', 'false', 'no', 'n' => false,
            default => true,
        };
    }

    protected function parseStatus(?string $value): string
    {
        $value = strtolower((string) $this->blankToNull($value));

        return match ($value) {
            'inactive', 'inactivo' => RecordStatus::Inactive->value,
            'archived', 'archivado' => RecordStatus::Archived->value,
            default => RecordStatus::Active->value,
        };
    }

    protected function normalizeDecimal(mixed $value, string $requiredMessage, int $scale = 2): string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            throw new InvalidArgumentException($requiredMessage);
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Uno de los valores numericos no es valido.');
        }

        if ((float) $value < 0) {
            throw new InvalidArgumentException('Los valores numericos no pueden ser negativos.');
        }

        return number_format((float) $value, $scale, '.', '');
    }

    protected function normalizeNullableDecimal(mixed $value, int $scale = 2): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        return $this->normalizeDecimal($value, 'Valor numerico obligatorio.', $scale);
    }

    protected function normalizeOptionalUpper(mixed $value): ?string
    {
        $value = $this->blankToNull($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    protected function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeHeader(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => $this->blankToNull($value) === null);
    }

    protected function marginFrom(string $cost, ?string $price): ?string
    {
        if ($price === null) {
            return null;
        }

        if ((float) $cost <= 0 || (float) $price <= 0) {
            return null;
        }

        return number_format((((float) $price - (float) $cost) / (float) $cost) * 100, 2, '.', '');
    }
}
