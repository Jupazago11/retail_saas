<?php

namespace App\Livewire\Dining;

use App\Actions\Dining\AddDishToDiningOrder;
use App\Actions\Dining\SplitDiningTableBill;
use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\DiningTable;
use App\Models\Product;
use App\Services\Sales\SaleCalculator;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

class TableOrderPage extends Component
{
    use InteractsWithToast;

    public int $tableId;
    public string $productId = '';
    public string $quantity = '1';

    // Estado del panel de cobro: divide la comanda en "pagadores" (1 por
    // defecto, N si se activa "Dividir cuenta"), cada uno con su propia
    // asignacion de platos y sus propios pagos mixtos. Un pagador siempre
    // existe (indice 0) aunque no se divida la cuenta.
    public bool $showCheckout = false;
    public bool $splitBill = false;
    public array $payerLabels = [];
    public array $itemPayer = [];
    public array $payments = [];
    public array $completedSaleIds = [];

    public function mount(int $table): void
    {
        $this->ensureDiningPermission();

        $this->tableId = $this->tablesQuery()->findOrFail($table)->id;
    }

    public function table(): DiningTable
    {
        return $this->tablesQuery()->findOrFail($this->tableId);
    }

    public function addDish(AddDishToDiningOrder $addDishToDiningOrder): void
    {
        $this->ensureDiningPermission();

        $company = $this->currentCompany();
        $validated = $this->validate([
            'productId' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('company_id', $company->id)->whereNull('deleted_at')),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = $this->products()->firstWhere('id', (int) $validated['productId']);

        try {
            $addDishToDiningOrder->handle($company, $this->table(), [
                'product_id' => (int) $validated['productId'],
                'quantity' => (string) $validated['quantity'],
                'unit_price' => (string) $product->price_1,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('productId', $exception->getMessage());

            return;
        }

        $this->productId = '';
        $this->quantity = '1';
        $this->toast('Plato agregado a la comanda.');
    }

    public function openCheckout(): void
    {
        $this->ensurePermission('sales.create');

        if (! $this->orderTotal()) {
            $this->toast('Agrega al menos un plato para poder cobrar.', 'warning');

            return;
        }

        $this->payerLabels = ['Cliente'];
        $this->itemPayer = $this->orderItems()->mapWithKeys(fn ($item) => [$item->id => 0])->all();
        $this->payments = [0 => [$this->newPaymentLine()]];
        $this->splitBill = false;
        $this->showCheckout = true;
    }

    public function closeCheckout(): void
    {
        $this->showCheckout = false;
    }

    public function toggleSplitBill(): void
    {
        $this->splitBill = ! $this->splitBill;
    }

    public function addPayer(): void
    {
        $index = $this->payerLabels === [] ? 0 : max(array_keys($this->payerLabels)) + 1;
        $this->payerLabels[$index] = 'Persona '.(count($this->payerLabels) + 1);
        $this->payments[$index] = [$this->newPaymentLine()];
    }

    public function removePayer(int $payerIndex): void
    {
        if ($payerIndex === 0 || ! isset($this->payerLabels[$payerIndex])) {
            return;
        }

        foreach ($this->itemPayer as $itemId => $assignedIndex) {
            if ($assignedIndex === $payerIndex) {
                $this->itemPayer[$itemId] = 0;
            }
        }

        unset($this->payerLabels[$payerIndex], $this->payments[$payerIndex]);
    }

    public function assignItemPayer(int $itemId, int $payerIndex): void
    {
        if (! isset($this->payerLabels[$payerIndex])) {
            return;
        }

        $this->itemPayer[$itemId] = $payerIndex;
    }

    public function addPaymentRow(int $payerIndex): void
    {
        $this->payments[$payerIndex][] = $this->newPaymentLine();
    }

    public function removePaymentRow(int $payerIndex, int $rowIndex): void
    {
        if (! isset($this->payments[$payerIndex])) {
            return;
        }

        unset($this->payments[$payerIndex][$rowIndex]);
        $this->payments[$payerIndex] = array_values($this->payments[$payerIndex]);
    }

    public function payerItemIds(int $payerIndex): array
    {
        return collect($this->itemPayer)
            ->filter(fn (int $assignedIndex) => $assignedIndex === $payerIndex)
            ->keys()
            ->all();
    }

    public function payerSubtotal(int $payerIndex): string
    {
        $itemIds = $this->payerItemIds($payerIndex);
        $lines = $this->payloadLines()->whereIn('dining_order_item_id', $itemIds)->values()->all();

        if ($lines === []) {
            return '0.00';
        }

        return app(SaleCalculator::class)->calculateTotals($lines)['grand_total'];
    }

    public function payerPaidTotal(int $payerIndex): string
    {
        return collect($this->payments[$payerIndex] ?? [])
            ->reduce(fn (string $carry, array $row) => bcadd($carry, is_numeric($row['amount'] ?? null) ? $row['amount'] : '0', 2), '0.00');
    }

    public function submitCheckout(SplitDiningTableBill $splitDiningTableBill): void
    {
        $this->ensurePermission('sales.create');

        $groups = collect($this->payerLabels)
            ->map(function (string $label, int $payerIndex) {
                return [
                    'label' => $this->splitBill ? $label : null,
                    'item_ids' => $this->payerItemIds($payerIndex),
                    'payments' => collect($this->payments[$payerIndex] ?? [])
                        ->filter(fn (array $row) => trim((string) ($row['amount'] ?? '')) !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        try {
            $sales = $splitDiningTableBill->handle($this->currentCompany(), $this->table(), $groups, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');

            return;
        }

        $this->showCheckout = false;

        if (count($sales) === 1) {
            $this->redirectRoute('sales.ticket', $sales[0], navigate: false);

            return;
        }

        // Con la cuenta dividida no hay un unico ticket al que redirigir —
        // se queda en la pagina (la mesa ya quedo libre) mostrando el
        // enlace de impresion de cada venta generada.
        $this->completedSaleIds = collect($sales)->pluck('id')->all();
        $this->toast('Cuenta dividida y cobrada correctamente.');
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
        $openFrozenSale = $this->table()->openFrozenSale();

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
        return $this->table()->openFrozenSale()?->payload_snapshot['totals'] ?? null;
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

    public function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
        ];
    }

    public function render(): View
    {
        $table = $this->table();

        return view('livewire.dining.table-order-page', [
            'table' => $table,
            'products' => $this->products(),
            'orderItems' => $this->orderItems(),
            'orderTotal' => $this->orderTotal(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => "Comanda - {$table->name}",
                'description' => 'Agrega platos a la mesa y cobra cuando el cliente pida la cuenta.',
            ]),
        ]);
    }

    protected function newPaymentLine(): array
    {
        return ['payment_method_code' => 'cash', 'amount' => '', 'reference' => ''];
    }

    protected function payloadLines(): BaseCollection
    {
        return collect($this->table()->openFrozenSale()?->payload_snapshot['items'] ?? []);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function tablesQuery()
    {
        return DiningTable::query()->where('company_id', $this->currentCompany()->id);
    }

    protected function ensurePermission(string $permissionCode): void
    {
        abort_unless(
            auth()->user()?->hasCurrentCompanyPermission($permissionCode),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }

    // dining.manage (dueño/administrador, entra desde el CRUD de mesas) o
    // dining.orders (mesero/cajero, entra desde su modulo de pedidos) — ver
    // App\Support\Authorization\PermissionCatalog para la distincion.
    protected function ensureDiningPermission(): void
    {
        abort_unless(
            auth()->user()?->hasAnyCurrentCompanyPermission(['dining.manage', 'dining.orders']),
            403,
            'No tienes permiso para acceder a este modulo.'
        );
    }
}
