<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\CancelSale;
use App\Actions\Sales\ReturnSale;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;

class SalesPage extends Component
{
    use InteractsWithToast;

    public string $search = '';
    public string $statusFilter = '';
    public string $saleTypeFilter = '';
    public ?int $returningSaleId = null;
    public array $returnItems = [];
    public string $returnReason = '';
    public ?int $cancellingSaleId = null;
    public string $cancellationReason = '';

    public function mount(): void
    {
        $this->ensurePermission('sales.view');
    }

    public function sales(): Collection
    {
        return $this->salesQuery()
            ->with([
                'branch',
                'cashRegister',
                'customer.person',
                'user',
                'payments',
                'items.product',
                'items.presentation',
                'items.variant.attributeValues.attribute',
                'replacesSale',
                'replacedBySale',
            ])
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->saleTypeFilter !== '', fn (Builder $query) => $query->where('sale_type', $this->saleTypeFilter))
            ->when($this->search !== '', function (Builder $query) {
                $search = '%' . trim($this->search) . '%';

                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->whereLike('id', $search)
                        ->orWhereLike('document_number', $search)
                        ->orWhereLike('sale_type', $search)
                        ->orWhereLike('status', $search)
                        ->orWhereHas('customer.person', function (Builder $personQuery) use ($search) {
                            $personQuery
                                ->whereLike('first_name', $search)
                                ->orWhereLike('last_name', $search)
                                ->orWhereLike('document_number', $search);
                        })
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->whereLike('name', $search));
                });
            })
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get();
    }

    public function statusCards(): array
    {
        $sales = $this->sales();

        return [
            'total_count' => $sales->count(),
            'confirmed_count' => $sales->where('status', 'confirmed')->count(),
            'credit_count' => $sales->where('sale_type', 'credit')->count(),
            'grand_total' => \App\Support\Money::format((float) $sales->sum(fn (Sale $sale) => (float) $sale->grand_total)),
        ];
    }

    public function render(): View
    {
        return view('livewire.sales.sales-page', [
            'sales' => $this->sales(),
            'statusCards' => $this->statusCards(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Ventas',
                'description' => 'Consulta ventas registradas, revisa totales operativos y abre el ticket imprimible por documento.',
            ]),
        ]);
    }

    public function canCreateSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
    }

    public function canReturnSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.return') ?? false;
    }

    public function canCancelSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.cancel') ?? false;
    }

    public function startReturningSale(int $saleId): void
    {
        $this->ensurePermission('sales.return');

        $sale = $this->salesQuery()
            ->with(['items'])
            ->findOrFail($saleId);

        if (! in_array($sale->status, ['confirmed', 'partially_returned'], true)) {
            return;
        }

        $this->returningSaleId = $sale->id;
        $this->returnItems = $sale->items
            ->map(function (SaleItem $item) {
                $pendingQuantity = $this->pendingReturnQuantity($item);

                return [
                    'sale_item_id' => $item->id,
                    'quantity' => '',
                    'pending_quantity' => $pendingQuantity,
                ];
            })
            ->filter(fn (array $item) => bccomp($item['pending_quantity'], '0', 6) === 1)
            ->values()
            ->all();

        if ($this->returnItems === []) {
            $this->returningSaleId = null;
            $this->toast('La venta ya no tiene cantidades pendientes por devolver.', 'warning');
        }

        $this->resetValidation();
    }

    public function cancelReturningSale(): void
    {
        $this->returningSaleId = null;
        $this->returnItems = [];
        $this->returnReason = '';
        $this->resetValidation();
    }

    public function registerReturn(ReturnSale $returnSale): void
    {
        $this->ensurePermission('sales.return');

        $validated = $this->validate([
            'returningSaleId' => [
                'required',
                \Illuminate\Validation\Rule::exists('sales', 'id')->where(fn ($query) => $query->where('company_id', $this->currentCompany()->id)),
            ],
            'returnItems' => ['required', 'array', 'min:1'],
            'returnItems.*.sale_item_id' => ['required', 'integer'],
            'returnItems.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'returnReason' => ['required', 'string', 'max:500'],
        ]);

        $items = collect($validated['returnItems'])
            ->filter(fn (array $item) => isset($item['quantity']) && trim((string) $item['quantity']) !== '')
            ->map(fn (array $item) => [
                'sale_item_id' => (int) $item['sale_item_id'],
                'quantity' => (string) $item['quantity'],
            ])
            ->values()
            ->all();

        if ($items === []) {
            $this->addError('returnItems', 'Debes indicar al menos una cantidad a devolver.');

            return;
        }

        $sale = $this->salesQuery()->findOrFail((int) $validated['returningSaleId']);

        try {
            $returnSale->handle($this->currentCompany(), $sale, $items, $validated['returnReason']);
        } catch (InvalidArgumentException $exception) {
            $this->addError('returnItems', $exception->getMessage());

            return;
        }

        $this->cancelReturningSale();
        $this->toast('Devolucion registrada correctamente.');
    }

    public function startCancellingSale(int $saleId): void
    {
        $this->ensurePermission('sales.cancel');

        $sale = $this->salesQuery()->findOrFail($saleId);

        if (! in_array($sale->status, ['draft', 'confirmed'], true)) {
            return;
        }

        $this->cancellingSaleId = $sale->id;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function cancelCancellingSale(): void
    {
        $this->cancellingSaleId = null;
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function cancelSaleDocument(int $saleId, CancelSale $cancelSale): void
    {
        $this->ensurePermission('sales.cancel');

        $validated = $this->validate([
            'cancellationReason' => ['required', 'string', 'max:500'],
        ]);

        $sale = $this->salesQuery()->findOrFail($saleId);

        try {
            $cancelSale->handle($this->currentCompany(), $sale, $validated['cancellationReason']);
        } catch (InvalidArgumentException $exception) {
            $this->addError('cancellationReason', $exception->getMessage());

            return;
        }

        if ($this->returningSaleId === $saleId) {
            $this->cancelReturningSale();
        }

        if ($this->cancellingSaleId === $saleId) {
            $this->cancelCancellingSale();
        }

        $this->toast('Venta anulada correctamente.', 'info');
    }

    public function draftEditUrl(int $saleId): string
    {
        return route('sales.pos', ['edit' => $saleId]);
    }

    public function modifySaleUrl(int $saleId): string
    {
        return route('sales.pos', ['modify' => $saleId]);
    }

    public function variantSummary($variant): string
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

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function pendingReturnQuantity(SaleItem $item): string
    {
        return bcsub((string) $item->quantity, (string) $item->returned_quantity, 6);
    }

    protected function salesQuery()
    {
        return Sale::query()
            ->where('company_id', $this->currentCompany()->id);
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
