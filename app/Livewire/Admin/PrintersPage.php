<?php

namespace App\Livewire\Admin;

use App\Enums\RecordStatus;
use App\Models\PrinterGuide;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class PrintersPage extends Component
{
    public function mount(): void
    {
        $this->ensurePermission('settings.manage');
    }

    public function guides(): Collection
    {
        return PrinterGuide::where('status', RecordStatus::Active->value)
            ->orderBy('title')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.printers-page', [
            'guides' => $this->guides(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Impresora',
                'description' => 'Guias de configuracion y solucion de problemas para impresoras termicas.',
            ]),
        ]);
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
