<?php

namespace App\Livewire\Dining;

use App\Actions\Dining\AddDishToDiningOrder;
use App\Actions\Dining\RemoveDiningOrderItem;
use App\Actions\Dining\UpdateDiningOrderItemQuantity;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\DiningFloorPlan;
use App\Models\DiningObstacle;
use App\Models\DiningOrderItem;
use App\Models\DiningTable;
use App\Models\Product;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

/**
 * El unico modulo del mesero (y de la cajera, cuando el negocio no usa
 * meseros): tomar y editar pedidos sobre las mesas. Gestion de mesas
 * (crear/archivar, editor de plano) sigue siendo dining.manage, exclusivo
 * del dueño/administrador — ver App\Support\Authorization\PermissionCatalog.
 *
 * Dos vistas sobre los mismos datos, alternadas 100% en el cliente
 * (Alpine, sin round-trip): "simple" (select de mesa) y "mapa" (plano
 * visual de solo lectura, clic en una mesa). Ambas abren el mismo
 * constructor de pedido inline al seleccionar una mesa — no hay
 * navegacion a otra pagina, por eso es "un solo modulo".
 */
class WaiterOrdersPage extends Component
{
    use InteractsWithToast;

    public ?int $branchId = null;
    public ?int $selectedTableId = null;
    public string $productId = '';
    public string $quantity = '1';
    public string $notes = '';

    public function mount(): void
    {
        $this->ensurePermission();
        $this->branchId = $this->branches()->first()?->id;
    }

    public function switchBranch(int $branchId): void
    {
        abort_unless($this->branches()->pluck('id')->contains($branchId), 404);
        $this->branchId = $branchId;
        $this->selectedTableId = null;
    }

    public function selectTable(int $tableId): void
    {
        abort_unless($this->tablesQuery()->where('id', $tableId)->exists(), 404);

        $this->selectedTableId = $tableId;
        $this->productId = '';
        $this->quantity = '1';
        $this->notes = '';
        $this->resetValidation();
    }

    public function closeOrder(): void
    {
        $this->selectedTableId = null;
        $this->productId = '';
        $this->quantity = '1';
        $this->notes = '';
        $this->resetValidation();
    }

    public function addDish(AddDishToDiningOrder $addDishToDiningOrder): void
    {
        $this->ensurePermission();
        abort_unless($this->selectedTableId, 404);

        $company = $this->currentCompany();
        $validated = $this->validate([
            'productId' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::query()->findOrFail((int) $validated['productId']);

        try {
            $addDishToDiningOrder->handle($company, $this->selectedTable(), [
                'product_id' => (int) $validated['productId'],
                'quantity' => (string) $validated['quantity'],
                'unit_price' => (string) $product->price_1,
                'notes' => $validated['notes'] ?? null,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('productId', $exception->getMessage());

            return;
        }

        $this->productId = '';
        $this->quantity = '1';
        $this->notes = '';
        $this->toast('Plato agregado a la comanda.');
    }

    public function updateItemQuantity(UpdateDiningOrderItemQuantity $action, int $itemId, string $quantity): void
    {
        $this->ensurePermission();

        if (! is_numeric($quantity) || (float) $quantity <= 0) {
            $this->toast('La cantidad debe ser mayor a cero.', 'warning');

            return;
        }

        $item = $this->itemsQuery()->findOrFail($itemId);

        try {
            $action->handle($item, $quantity, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        $this->toast('Cantidad actualizada.');
    }

    public function removeItem(RemoveDiningOrderItem $action, int $itemId): void
    {
        $this->ensurePermission();

        $item = $this->itemsQuery()->findOrFail($itemId);

        try {
            $action->handle($item, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        $this->toast('Plato quitado de la comanda.');
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

    public function tables(): Collection
    {
        return $this->tablesQuery()
            ->where('status', RecordStatus::Active->value)
            ->with(['frozenSales' => fn ($q) => $q->where('status', 'open')->latest('id')->limit(1)])
            ->get()
            ->sortBy(fn (DiningTable $table) => is_numeric($table->name) ? (int) $table->name : PHP_INT_MAX)
            ->values();
    }

    public function floorPlan(): ?DiningFloorPlan
    {
        return DiningFloorPlan::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->first();
    }

    public function obstacles(): Collection
    {
        return DiningObstacle::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->get();
    }

    public function placedCashRegisters(): Collection
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->where('status', RecordStatus::Active->value)
            ->whereNotNull('pos_x')
            ->get();
    }

    public function selectedTable(): ?DiningTable
    {
        if (! $this->selectedTableId) {
            return null;
        }

        return $this->tablesQuery()->find($this->selectedTableId);
    }

    public function products(): Collection
    {
        return Product::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('status', RecordStatus::Active->value)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    public function orderItems(): Collection
    {
        $table = $this->selectedTable();
        $openFrozenSale = $table?->openFrozenSale();

        if (! $openFrozenSale) {
            return new Collection();
        }

        return $openFrozenSale->diningOrderItems()
            ->with('product')
            ->orderBy('id')
            ->get();
    }

    public function orderTotal(): ?array
    {
        return $this->selectedTable()?->openFrozenSale()?->payload_snapshot['totals'] ?? null;
    }

    public function canCharge(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
    }

    public function kitchenStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'preparing' => 'En preparacion',
            'on_hold' => 'En espera',
            'ready' => 'Listo',
            'served' => 'Servido',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    public function render(): View
    {
        return view('livewire.dining.waiter-orders-page', [
            'branches' => $this->branches(),
            'tables' => $this->tables(),
            'floorPlan' => $this->floorPlan(),
            'obstacles' => $this->obstacles(),
            'placedCashRegisters' => $this->placedCashRegisters(),
            'obstacleColor' => app(CompanySettings::class)->get($this->currentCompany(), 'dining', 'obstacle_color'),
            'selectedTable' => $this->selectedTable(),
            'products' => $this->products(),
            'orderItems' => $this->orderItems(),
            'orderTotal' => $this->orderTotal(),
            'canCharge' => $this->canCharge(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Pedidos',
                'description' => 'Toma y edita pedidos sobre las mesas del salon.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function tablesQuery()
    {
        return DiningTable::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId);
    }

    protected function itemsQuery()
    {
        return DiningOrderItem::query()
            ->whereHas('frozenSale', fn ($q) => $q->where('company_id', $this->currentCompany()->id));
    }

    protected function ensurePermission(): void
    {
        abort_unless(
            auth()->user()?->hasAnyCurrentCompanyPermission(['dining.orders', 'dining.manage']),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
