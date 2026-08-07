<?php

namespace App\Actions\Purchases;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImportPurchaseFromCsv
{
    protected const REQUIRED_HEADERS = [
        'product_sku',
        'quantity',
        'unit_cost',
    ];

    public function __construct(
        protected CreatePurchase $createPurchase,
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
                $validItems[] = $this->mapRowToItem($company, $payload);
            } catch (InvalidArgumentException $exception) {
                $errors[] = 'Fila '.$rowNumber.': '.$exception->getMessage();
            }
        }

        fclose($handle);

        if ($validItems === []) {
            return [
                'file_name' => $file->getClientOriginalName(),
                'purchase_id' => null,
                'created_count' => 0,
                'error_count' => count($errors),
                'errors' => $errors,
            ];
        }

        $purchase = $this->createPurchase->handle($company, [
            'branch_id' => $context['branch_id'],
            'warehouse_id' => $context['warehouse_id'],
            'supplier_id' => $context['supplier_id'] ?? null,
            'supplier_name' => $context['supplier_name'] ?? null,
            'invoice_number' => $context['invoice_number'] ?? null,
            'purchase_type' => $context['purchase_type'] ?? 'invoice',
            'status' => $context['status'],
            'purchased_at' => $context['purchased_at'] ?? null,
            'due_at' => $context['due_at'] ?? null,
            'notes' => $context['notes'] ?? null,
            'paid_amount' => $context['paid_amount'] ?? null,
            'items' => $validItems,
        ]);

        $summary = [
            'file_name' => $file->getClientOriginalName(),
            'purchase_id' => $purchase->id,
            'created_count' => count($validItems),
            'error_count' => count($errors),
            'errors' => $errors,
        ];

        $this->auditLogger->logSnapshot(
            $company,
            'purchase.imported',
            $purchase::class,
            $purchase->id,
            null,
            $summary,
            $actor,
        );

        return $summary;
    }

    protected function mapRowToItem(Company $company, array $row): array
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

        $presentation = $this->resolvePresentation($company, $product, $row['presentation_name'] ?? null);
        $variant = $this->resolveVariant($company, $product, $row['variant_sku'] ?? null);

        return [
            'product_id' => $product->id,
            'product_presentation_id' => $presentation?->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $this->normalizePositiveDecimal($row['quantity'] ?? null, 'La cantidad debe ser mayor a cero.'),
            'unit_cost' => $this->normalizeNonNegativeDecimal($row['unit_cost'] ?? null, 2, 'El costo unitario es obligatorio.'),
            'tax_rate' => $this->normalizeNullableDecimal($row['tax_rate'] ?? null, 2) ?? '0.00',
        ];
    }

    protected function resolvePresentation(Company $company, Product $product, ?string $presentationName): ?ProductPresentation
    {
        $presentationName = $this->blankToNull($presentationName);

        if ($presentationName === null) {
            return null;
        }

        $presentation = ProductPresentation::query()
            ->where('company_id', $company->id)
            ->where('product_id', $product->id)
            ->whereRaw('upper(name) = ?', [mb_strtoupper($presentationName)])
            ->whereNull('deleted_at')
            ->first();

        if (! $presentation) {
            throw new InvalidArgumentException('La presentacion indicada no existe para el producto referenciado.');
        }

        return $presentation;
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

    protected function normalizeNonNegativeDecimal(mixed $value, int $scale, string $requiredMessage): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException($requiredMessage);
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Uno de los valores numericos no es valido.');
        }

        $normalized = number_format((float) $value, $scale, '.', '');

        if (bccomp($normalized, '0', $scale) < 0) {
            throw new InvalidArgumentException('Los valores numericos no pueden ser negativos.');
        }

        return $normalized;
    }

    protected function normalizeNullableDecimal(mixed $value, int $scale): ?string
    {
        $value = $this->blankToNull($value);

        if ($value === null) {
            return null;
        }

        return $this->normalizeNonNegativeDecimal($value, $scale, 'Valor numerico obligatorio.');
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
