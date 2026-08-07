<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\CancelFrozenSale;
use App\Actions\Sales\ConvertFrozenSaleToSale;
use App\Actions\Sales\CreateFrozenSale;
use App\Enums\FrozenSaleStatus;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FrozenSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Plans\CompanyPlanResolver;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class FrozenSalesPage extends Component
{
    use InteractsWithToast;

    public ?int $resumingFrozenSaleId = null;
    public ?int $branchId = null;
    public ?int $warehouseId = null;
    public ?int $cashRegisterId = null;
    public ?int $customerId = null;
    public string $label = '';
    public string $notes = '';
    public array $items = [];
    public int $nextLineKey = 0;

    public string $search = '';
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->ensureFrozenAccess();
        $this->resetFrozenSaleForm();
    }

    public function updatedBranchId($value): void
    {
        $value = $value ? (int) $value : null;

        $warehouseIds = $this->warehouses()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $cashRegisterIds = $this->cashRegisters()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($value === null || ! in_array((int) $this->warehouseId, $warehouseIds, true)) {
            $this->warehouseId = $warehouseIds[0] ?? null;
        }

        if ($value === null || ! in_array((int) $this->cashRegisterId, $cashRegisterIds, true)) {
            $this->cashRegisterId = $cashRegisterIds[0] ?? null;
        }
    }

    public function updatedItems($value, string $key): void
    {
        $segments = explode('.', $key);

        if (count($segments) < 2) {
            return;
        }

        $index = (int) $segments[0];
        $field = $segments[1];

        if (! isset($this->items[$index])) {
            return;
        }

        if ($field === 'product_id') {
            $product = $this->products()->firstWhere('id', (int) $value);

            $this->items[$index]['product_presentation_id'] = '';
            $this->items[$index]['product_variant_id'] = '';
            $this->items[$index]['unit_price'] = $product ? (string) $product->price_1 : '0.00';
        }

        if ($field === 'product_presentation_id') {
            $product = $this->products()->firstWhere('id', (int) ($this->items[$index]['product_id'] ?? 0));
            $presentation = $product?->presentations?->firstWhere('id', (int) $value);

            if ($presentation && $presentation->price_1 !== null) {
                $this->items[$index]['unit_price'] = (string) $presentation->price_1;
            } elseif ($product) {
                $this->items[$index]['unit_price'] = (string) $product->price_1;
            }
        }
    }

    public function clearFrozenSaleForm(): void
    {
        $this->resetFrozenSaleForm();
    }

    public function addItemLine(): void
    {
        $this->items[] = $this->newItemLine();
    }

    public function removeItemLine(int $lineKey): void
    {
        $this->items = collect($this->items)
            ->reject(fn (array $item) => (int) ($item['_key'] ?? -1) === $lineKey)
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items[] = $this->newItemLine();
        }
    }

    public function saveFrozenSale(CreateFrozenSale $createFrozenSale): void
    {
        $this->ensurePermission('sales.freeze');

        if (! $this->canCreateFrozenSales()) {
            $this->addError('items', $this->frozenSalesRequirementsMessage());

            return;
        }

        $company = $this->currentCompany();
        $validated = $this->validate([
            'branchId' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'warehouseId' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')),
            ],
            'cashRegisterId' => [
                'nullable',
                Rule::exists('cash_registers', 'id')->where(fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->whereNull('deleted_at')),
            ],
            'customerId' => [
                'nullable',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'label' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'items.*.product_presentation_id' => [
                'nullable',
                Rule::exists('product_presentations', 'id')->where(fn ($query) => $query->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'items.*.product_variant_id' => [
                'nullable',
                Rule::exists('product_variants', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $createFrozenSale->handle($company, [
                'branch_id' => (int) $validated['branchId'],
                'warehouse_id' => (int) $validated['warehouseId'],
                'cash_register_id' => $validated['cashRegisterId'] ? (int) $validated['cashRegisterId'] : null,
                'customer_id' => $validated['customerId'] ? (int) $validated['customerId'] : null,
                'created_by' => auth()->id(),
                'label' => trim($validated['label']),
                'notes' => $this->blankToNull($validated['notes']),
                'items' => collect($validated['items'])
                    ->map(fn (array $item) => [
                        'product_id' => (int) $item['product_id'],
                        'product_presentation_id' => $item['product_presentation_id'] ? (int) $item['product_presentation_id'] : null,
                        'product_variant_id' => $item['product_variant_id'] ? (int) $item['product_variant_id'] : null,
                        'quantity' => (string) $item['quantity'],
                        'unit_price' => (string) $item['unit_price'],
                        'discount_amount' => $item['discount_amount'] !== null && $item['discount_amount'] !== '' ? (string) $item['discount_amount'] : '0',
                        'tax_rate' => $item['tax_rate'] !== null && $item['tax_rate'] !== '' ? (string) $item['tax_rate'] : '0',
                    ])
                    ->all(),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        $this->resetFrozenSaleForm();
        $this->toast('Venta congelada guardada correctamente.');
    }

    public function startResumingFrozenSale(int $frozenSaleId): void
    {
        $frozenSale = $this->frozenSalesQuery()->findOrFail($frozenSaleId);

        if ($this->effectiveStatus($frozenSale) !== FrozenSaleStatus::Open->value) {
            $this->toast('Solo puedes retomar ventas congeladas abiertas.', 'warning');

            return;
        }

        $payload = $frozenSale->payload_snapshot;

        $this->resumingFrozenSaleId = $frozenSale->id;
        $this->branchId = $frozenSale->branch_id;
        $this->warehouseId = $frozenSale->warehouse_id;
        $this->cashRegisterId = $frozenSale->cash_register_id;
        $this->customerId = $frozenSale->customer_id;
        $this->label = $frozenSale->label;
        $this->notes = (string) ($payload['notes'] ?? '');
        $this->items = collect($payload['items'] ?? [])
            ->map(fn (array $item) => [
                '_key' => ++$this->nextLineKey,
                'product_id' => (string) ($item['product_id'] ?? ''),
                'product_presentation_id' => isset($item['product_presentation_id']) && $item['product_presentation_id'] !== null ? (string) $item['product_presentation_id'] : '',
                'product_variant_id' => isset($item['product_variant_id']) && $item['product_variant_id'] !== null ? (string) $item['product_variant_id'] : '',
                'quantity' => (string) ($item['quantity'] ?? '1'),
                'unit_price' => (string) ($item['unit_price'] ?? '0.00'),
                'discount_amount' => (string) ($item['discount_amount'] ?? '0.00'),
                'tax_rate' => (string) ($item['tax_rate'] ?? '0'),
            ])
            ->values()
            ->all();

        if ($this->items === []) {
            $this->items = [$this->newItemLine()];
        }

        $this->resetValidation();
    }

    public function convertFrozenSaleToSale(int $frozenSaleId, ConvertFrozenSaleToSale $convertFrozenSaleToSale): void
    {
        $this->ensurePermission('sales.create');

        $frozenSale = $this->frozenSalesQuery()->findOrFail($frozenSaleId);

        try {
            $sale = $convertFrozenSaleToSale->handle($this->currentCompany(), $frozenSale, [
                'sold_at' => now(),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        $this->toast('Venta congelada convertida correctamente.');
        $this->redirectRoute('sales.ticket', $sale, navigate: false);
    }

    public function cancelFrozenSale(int $frozenSaleId, CancelFrozenSale $cancelFrozenSale): void
    {
        $this->ensurePermission('sales.freeze');

        $frozenSale = $this->frozenSalesQuery()->findOrFail($frozenSaleId);

        try {
            $cancelFrozenSale->handle($this->currentCompany(), $frozenSale);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        if ($this->resumingFrozenSaleId === $frozenSaleId) {
            $this->resetFrozenSaleForm();
        }

        $this->toast('Venta congelada cancelada correctamente.', 'info');
    }

    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function warehouses(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
        }

        return Warehouse::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function cashRegisters(): Collection
    {
        if (! $this->branchId) {
            return new Collection();
        }

        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function customers(): Collection
    {
        return Customer::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->with(['person', 'creditAccount'])
            ->orderByDesc('id')
            ->get();
    }

    public function products(): Collection
    {
        return Product::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->with([
                'presentations' => fn ($query) => $query
                    ->where('status', RecordStatus::Active->value)
                    ->whereNull('deleted_at')
                    ->orderBy('name'),
                'variants' => fn ($query) => $query
                    ->where('status', RecordStatus::Active->value)
                    ->with(['attributeValues.attribute'])
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->get();
    }

    public function frozenSales(): Collection
    {
        return $this->frozenSalesQuery()
            ->with([
                'branch',
                'warehouse',
                'cashRegister',
                'creator',
                'customer.person',
                'convertedSale.customer.person',
            ])
            ->when($this->statusFilter !== '', function (Builder $query) {
                if ($this->statusFilter === 'expired') {
                    $query
                        ->where('status', FrozenSaleStatus::Open->value)
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now());

                    return;
                }

                $query->where('status', $this->statusFilter);
            })
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('label', $search)
                        ->orWhereLike('id', $search)
                        ->orWhereHas('creator', fn (Builder $userQuery) => $userQuery->whereLike('name', $search))
                        ->orWhereHas('customer.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        });
                });
            })
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function statusCards(): array
    {
        $sales = $this->frozenSales();

        return [
            'total_count' => $sales->count(),
            'open_count' => $sales->filter(fn (FrozenSale $sale) => $this->effectiveStatus($sale) === FrozenSaleStatus::Open->value)->count(),
            'expired_count' => $sales->filter(fn (FrozenSale $sale) => $this->effectiveStatus($sale) === FrozenSaleStatus::Expired->value)->count(),
            'converted_count' => $sales->where('status', FrozenSaleStatus::Converted->value)->count(),
        ];
    }

    public function canCreateFrozenSales(): bool
    {
        return $this->branches()->isNotEmpty()
            && $this->warehouses()->isNotEmpty()
            && $this->products()->isNotEmpty();
    }

    public function frozenSalesRequirementsMessage(): ?string
    {
        $missing = [];

        if ($this->branches()->isEmpty()) {
            $missing[] = 'una sucursal activa';
        }

        if ($this->warehouses()->isEmpty()) {
            $missing[] = 'una bodega activa';
        }

        if ($this->products()->isEmpty()) {
            $missing[] = 'un producto activo';
        }

        if ($missing === []) {
            return null;
        }

        return 'Debes tener al menos '.implode(', ', $missing).' para congelar ventas.';
    }

    public function canFreezeSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.freeze') ?? false;
    }

    public function canConvertFrozenSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
    }

    public function effectiveStatus(FrozenSale $sale): string
    {
        if (
            $sale->status === FrozenSaleStatus::Open->value
            && $sale->expires_at !== null
            && $sale->expires_at->isPast()
        ) {
            return FrozenSaleStatus::Expired->value;
        }

        return $sale->status;
    }

    public function variantSummary(?ProductVariant $variant): string
    {
        if (! $variant) {
            return 'Sin variante';
        }

        $parts = $variant->attributeValues
            ->sortBy(fn ($value) => ($value->attribute->name ?? '') . '-' . $value->value)
            ->map(fn ($value) => ($value->attribute->name ?? 'Atributo') . ': ' . $value->value)
            ->values()
            ->all();

        if ($parts === []) {
            return $variant->sku ?: 'Variante #' . $variant->id;
        }

        return implode(' / ', $parts);
    }

    public function render(): View
    {
        return view('livewire.sales.frozen-sales-page', [
            'branches' => $this->branches(),
            'warehouses' => $this->warehouses(),
            'cashRegisters' => $this->cashRegisters(),
            'customers' => $this->customers(),
            'products' => $this->products(),
            'frozenSales' => $this->frozenSales(),
            'statusCards' => $this->statusCards(),
            'canCreateFrozenSales' => $this->canCreateFrozenSales(),
            'frozenSalesRequirementsMessage' => $this->frozenSalesRequirementsMessage(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Ventas congeladas',
                'description' => 'Congela carritos sin tocar inventario, retomalos despues o conviertelos en venta real cuando el cliente confirme.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function frozenSalesQuery()
    {
        return FrozenSale::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function ensureFrozenAccess(): void
    {
        abort_unless(
            app(CompanyPlanResolver::class)->hasFeature($this->currentCompany(), 'pos.frozen_sales'),
            403,
            'El plan actual no tiene habilitadas las ventas congeladas.'
        );

        abort_unless(
            $this->canFreezeSales() || $this->canConvertFrozenSales(),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    protected function resetFrozenSaleForm(): void
    {
        $this->resumingFrozenSaleId = null;
        $branches = $this->branches();
        $this->branchId = $branches->first()?->id;
        $this->warehouseId = $this->warehouses()->first()?->id;
        $this->cashRegisterId = $this->cashRegisters()->first()?->id;
        $this->customerId = null;
        $this->label = '';
        $this->notes = '';
        $this->items = [$this->newItemLine()];
        $this->resetValidation();
    }

    protected function newItemLine(): array
    {
        return [
            '_key' => ++$this->nextLineKey,
            'product_id' => '',
            'product_presentation_id' => '',
            'product_variant_id' => '',
            'quantity' => '1',
            'unit_price' => '0.00',
            'discount_amount' => '0.00',
            'tax_rate' => '0',
        ];
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
