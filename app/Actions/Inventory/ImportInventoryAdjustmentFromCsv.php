<?php

namespace App\Actions\Inventory;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImportInventoryAdjustmentFromCsv
{
    protected const REQUIRED_HEADERS = [
        'product_sku',
        'quantity',
    ];

    public function __construct(
        protected CreateInventoryAdjustment $createInventoryAdjustment,
        protected AuditLogger $auditLogger,
    ) {
    }

    public function handle(Company $company, UploadedFile $file, array $context, ?User $actor = null): array
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
        $validItems = [];
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $payload = $this->combineRow($normalizedHeaders, $row);

            try {
                $validItems[] = $this->mapRowToItem($company, $payload, $context['adjustment_type'] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors[] = 'Fila '.$rowNumber.': '.$exception->getMessage();
            }
        }

        fclose($handle);

        if ($validItems === []) {
            return [
                'file_name' => $file->getClientOriginalName(),
                'adjustment_id' => null,
                'created_count' => 0,
                'error_count' => count($errors),
                'errors' => $errors,
            ];
        }

        $adjustment = $this->createInventoryAdjustment->handle($company, [
            'branch_id' => $context['branch_id'],
            'warehouse_id' => $context['warehouse_id'],
            'adjustment_type' => $context['adjustment_type'],
            'reason' => $context['reason'],
            'notes' => $context['notes'] ?? null,
            'adjusted_at' => $context['adjusted_at'] ?? null,
            'items' => $validItems,
        ]);

        $summary = [
            'file_name' => $file->getClientOriginalName(),
            'adjustment_id' => $adjustment->id,
            'created_count' => count($validItems),
            'error_count' => count($errors),
            'errors' => $errors,
        ];

        $this->auditLogger->logSnapshot(
            $company,
            'inventory.adjustment.imported',
            $adjustment::class,
            $adjustment->id,
            null,
            $summary,
            $actor,
        );

        return $summary;
    }

    protected function mapRowToItem(Company $company, array $row, ?string $adjustmentType): array
    {
        $productSku = $this->normalizeUpper($row['product_sku'] ?? null);

        if ($productSku === null) {
            throw new InvalidArgumentException('El SKU del producto es obligatorio.');
        }

        $product = Product::query()
            ->where('company_id', $company->id)
            ->where('sku', $productSku)
            ->whereNull('deleted_at')
            ->first();

        if (! $product) {
            throw new InvalidArgumentException('El producto indicado no existe para la empresa.');
        }

        $variant = $this->resolveVariant($company, $product, $row['variant_sku'] ?? null);
        $quantity = $this->normalizePositiveDecimal($row['quantity'] ?? null, 'La cantidad debe ser mayor a cero.');
        $unitCost = $this->normalizeUnitCost($row['unit_cost'] ?? null, $adjustmentType);

        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ];
    }

    protected function resolveVariant(Company $company, Product $product, ?string $variantSku): ?ProductVariant
    {
        $variantSku = $this->normalizeUpper($variantSku);

        if ($variantSku === null) {
            return null;
        }

        $variant = ProductVariant::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->where('sku', $variantSku)
            ->first();

        if (! $variant) {
            throw new InvalidArgumentException('La variante indicada no existe para el producto referenciado.');
        }

        return $variant;
    }

    protected function normalizePositiveDecimal(mixed $value, string $message, int $scale = 6): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            throw new InvalidArgumentException('Uno de los valores numericos no es valido.');
        }

        $normalized = number_format((float) $value, $scale, '.', '');

        if (bccomp($normalized, '0', $scale) <= 0) {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    protected function normalizeUnitCost(mixed $value, ?string $adjustmentType): string
    {
        $normalizedType = trim((string) $adjustmentType);

        if ($normalizedType === 'increase') {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                throw new InvalidArgumentException('Las entradas por ajuste requieren un costo unitario mayor a cero.');
            }

            $normalized = number_format((float) $value, 4, '.', '');

            if (bccomp($normalized, '0', 4) <= 0) {
                throw new InvalidArgumentException('Las entradas por ajuste requieren un costo unitario mayor a cero.');
            }

            return $normalized;
        }

        if ($value === null || $value === '') {
            return '0.0000';
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Uno de los valores numericos no es valido.');
        }

        $normalized = number_format((float) $value, 4, '.', '');

        if (bccomp($normalized, '0', 4) < 0) {
            throw new InvalidArgumentException('El costo unitario no puede ser negativo.');
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

    protected function normalizeUpper(mixed $value): ?string
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
}
