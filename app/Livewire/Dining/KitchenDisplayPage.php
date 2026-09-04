<?php

namespace App\Livewire\Dining;

use App\Actions\Dining\AdvanceDiningOrderItemStatus;
use App\Actions\Dining\DismissDiningOrderItem;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Company;
use App\Models\DiningOrderItem;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Component;

class KitchenDisplayPage extends Component
{
    use InteractsWithToast;

    public function mount(): void
    {
        $this->ensurePermission('kitchen.manage');
    }

    public function advance(int $itemId, string $targetStatus, AdvanceDiningOrderItemStatus $advanceDiningOrderItemStatus): void
    {
        $this->ensurePermission('kitchen.manage');

        $item = $this->itemsQuery()->findOrFail($itemId);

        try {
            $advanceDiningOrderItemStatus->handle($item, $targetStatus);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'warning');
        }
    }

    public function dismiss(int $itemId, DismissDiningOrderItem $dismissDiningOrderItem): void
    {
        $this->ensurePermission('kitchen.manage');

        $item = $this->itemsQuery()->findOrFail($itemId);
        $dismissDiningOrderItem->handle($item);
    }

    public function pendingItems(): Collection
    {
        return $this->itemsQuery()
            ->whereIn('kitchen_status', ['pending', 'preparing', 'on_hold', 'ready', 'cancelled'])
            ->with(['product', 'creator', 'frozenSale.diningTable'])
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (DiningOrderItem $item) => $item->frozenSale->diningTable?->name ?? 'Mesa');
    }

    /**
     * Botones disponibles para un plato segun su estado actual: pending
     * solo puede empezar, preparing puede pausarse o marcarse listo, etc.
     * "cancelled" no avanza, solo se descarta (ver dismiss()).
     */
    public function availableActions(DiningOrderItem $item): array
    {
        return match ($item->kitchen_status) {
            'pending' => [['status' => 'preparing', 'label' => 'Empezar']],
            'preparing' => [
                ['status' => 'on_hold', 'label' => 'En espera'],
                ['status' => 'ready', 'label' => 'Marcar listo'],
            ],
            'on_hold' => [['status' => 'preparing', 'label' => 'Reanudar']],
            'ready' => [['status' => 'served', 'label' => 'Marcar servido']],
            default => [],
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'preparing' => 'En preparacion',
            'on_hold' => 'En espera',
            'ready' => 'Listo',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    public function statusClasses(string $status): string
    {
        return match ($status) {
            'pending' => 'bg-gray-200 text-gray-700',
            'preparing' => 'bg-amber-100 text-amber-700',
            'on_hold' => 'bg-sky-100 text-sky-700',
            'cancelled' => 'bg-rose-100 text-rose-700',
            default => 'bg-emerald-100 text-emerald-700',
        };
    }

    /**
     * Minutos desde que se agrego el plato a la comanda — se recalcula en
     * cada wire:poll (cada 5s), no en vivo segundo a segundo.
     */
    public function elapsedMinutes(DiningOrderItem $item): int
    {
        // Carbon 3 retorna la diferencia con signo (created_at - now, ya
        // que created_at es el "otro" extremo); abs() para el conteo de
        // minutos transcurridos, que nunca es negativo.
        return (int) abs(now()->diffInMinutes($item->created_at));
    }

    public function elapsedLabel(int $minutes): string
    {
        return $minutes < 1 ? '<1m' : "{$minutes}m";
    }

    /**
     * Colorea el temporizador: al principio neutral, a los 7 min de alerta
     * (amber) y a los 15 en rojo — para que la cocina priorice visualmente
     * lo que lleva mas tiempo esperando.
     */
    public function urgencyClasses(int $minutes): string
    {
        return match (true) {
            $minutes >= 15 => 'bg-rose-100 text-rose-700',
            $minutes >= 7 => 'bg-amber-100 text-amber-700',
            default => 'bg-gray-100 text-gray-500',
        };
    }

    public function render(): View
    {
        return view('livewire.dining.kitchen-display-page', [
            'groupedItems' => $this->pendingItems(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Cocina',
                'description' => 'Platos pendientes de preparar, agrupados por mesa.',
            ]),
        ]);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function itemsQuery()
    {
        return DiningOrderItem::query()
            ->whereHas('frozenSale', fn ($q) => $q->where('company_id', $this->currentCompany()->id));
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
