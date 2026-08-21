<?php

namespace App\Livewire\Sales;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class SalesWorkspacePage extends Component
{
    public string $activeTab = 'history';

    public function mount(): void
    {
        $this->activeTab = request()->routeIs('sales.pos') ? 'pos' : 'history';
    }

    public function canViewSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.view') ?? false;
    }

    public function canCreateSales(): bool
    {
        return auth()->user()?->hasCurrentCompanyPermission('sales.create') ?? false;
    }

    public function render(): View
    {
        return view('livewire.sales.sales-workspace-page')->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => $this->activeTab === 'pos' ? 'POS' : 'Ventas',
                'description' => 'Registra ventas en el punto de venta y consulta el historial, sin salir de esta pantalla.',
            ]),
        ]);
    }
}
