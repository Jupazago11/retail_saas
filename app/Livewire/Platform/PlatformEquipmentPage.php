<?php

namespace App\Livewire\Platform;

use App\Livewire\Concerns\InteractsWithToast;
use App\Models\EquipmentType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class PlatformEquipmentPage extends Component
{
    use InteractsWithToast;

    public bool $showModal = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $unitCost = '';
    public string $monthlyPrice = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'unitCost', 'monthlyPrice']);
        $this->showModal = true;
    }

    public function startEdit(int $id): void
    {
        $type = EquipmentType::findOrFail($id);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->unitCost = (string) (int) $type->unit_cost;
        $this->monthlyPrice = (string) (int) $type->monthly_price;
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'unitCost' => ['required', 'numeric', 'min:0'],
            'monthlyPrice' => ['required', 'numeric', 'min:0'],
        ]);

        $existing = $this->editingId ? EquipmentType::find($this->editingId) : null;

        EquipmentType::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'code' => $existing?->code ?? $this->uniqueCode($validated['name']),
                'name' => $validated['name'],
                'unit_cost' => $validated['unitCost'],
                'monthly_price' => $validated['monthlyPrice'],
                'status' => $existing?->status ?? 'active',
            ]
        );

        $this->showModal = false;
        $this->toast($this->editingId ? 'Equipo actualizado.' : 'Equipo creado.');
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);

        $type = EquipmentType::findOrFail($id);
        $type->update(['status' => $type->status === 'active' ? 'inactive' : 'active']);

        $this->toast('Estado del equipo actualizado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    protected function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = Str::upper(Str::random(6));
        }

        $base = Str::substr($base, 0, 70);
        $code = $base;
        $n = 2;

        while (EquipmentType::where('code', $code)->exists()) {
            $code = Str::substr($base, 0, 67).'_'.$n++;
        }

        return $code;
    }

    public function render(): View
    {
        return view('livewire.platform.platform-equipment-page', [
            'equipmentTypes' => EquipmentType::orderBy('name')->get(),
        ])->layout('layouts.platform');
    }
}
