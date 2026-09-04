<?php

namespace App\Livewire\Dining;

use App\Enums\RecordStatus;
use App\Livewire\Concerns\InteractsWithToast;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\DiningFloorPlan;
use App\Models\DiningObstacle;
use App\Models\DiningTable;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Editor visual del plano del salon: contorno libre (poligono dibujado punto
// a punto) mas posicion, forma y tamaño de cada mesa. Solo el dueño de la
// empresa puede entrar aca — dining.manage sigue gobernando el uso operativo
// diario (abrir comandas), no la edicion del plano.
//
// Crear, mover y eliminar una mesa (o un obstaculo) se guardan de
// inmediato, pero NO como acciones de este componente Livewire — van por
// fetch() plano contra DiningFloorPlanTablesController/
// DiningFloorPlanObstaclesController (ver
// Alpine.data('diningFloorPlanEditor') en resources/js/app.js). save() (el
// boton "Guardar plano") sigue siendo una accion de Livewire y solo se
// ocupa de lo que de verdad queda pendiente hasta pulsarlo: el contorno del
// salon, el tamaño/forma de cada mesa, el tamaño de cada obstaculo y las
// cajas — nunca de crear/mover/eliminar mesas u obstaculos, que ya llegaron
// al servidor antes. Su rama de "id nuevo" (uniqueName()) queda como red de
// seguridad, no como el camino real de creacion.
class DiningFloorPlanPage extends Component
{
    use InteractsWithToast;

    public ?int $branchId = null;

    public function mount(): void
    {
        $this->ensureOwner();
        $this->branchId = $this->branches()->first()?->id;
    }

    public function switchBranch(int $branchId): void
    {
        abort_unless($this->branches()->pluck('id')->contains($branchId), 404);
        $this->branchId = $branchId;
    }

    public function save(array $outline, array $tables, array $registers = [], array $obstacles = []): void
    {
        $this->ensureOwner();

        $company = $this->currentCompany();

        if (count($outline) > 0 && count($outline) < 3) {
            $this->toast('El contorno del salon necesita al menos 3 puntos, o ninguno.', 'warning');

            return;
        }

        $existingIds = $this->tablesQuery()->pluck('id')->all();
        $activeExisting = $this->tablesQuery()->where('status', RecordStatus::Active->value)->get(['id', 'name']);
        $activeExistingIds = $activeExisting->pluck('id')->all();
        // Solo los nombres de mesas ACTIVAS bloquean una colision — una mesa
        // archivada ya no reserva su numero para siempre (dropUnique en
        // dining_tables, ver migracion 2026_09_03_150100).
        $usedNames = $activeExisting->pluck('name')->all();
        $registerIds = $this->registersQuery()->pluck('id')->all();
        $obstacleIds = $this->obstaclesQuery()->pluck('id')->all();

        DB::transaction(function () use ($company, $outline, $tables, $registers, $obstacles, $existingIds, $activeExistingIds, $registerIds, $obstacleIds, &$usedNames) {
            $submittedExistingIds = [];
            if (count($outline) === 0) {
                DiningFloorPlan::query()
                    ->where('company_id', $company->id)
                    ->where('branch_id', $this->branchId)
                    ->delete();
            } else {
                DiningFloorPlan::query()->updateOrCreate(
                    ['company_id' => $company->id, 'branch_id' => $this->branchId],
                    ['outline_points' => array_map(
                        fn (array $point) => ['x' => round((float) $point['x'], 2), 'y' => round((float) $point['y'], 2)],
                        $outline
                    )]
                );
            }

            foreach ($tables as $tableData) {
                $rawId = $tableData['id'] ?? null;
                $isExisting = is_numeric($rawId) && in_array((int) $rawId, $existingIds, true);

                $size = min(20, max(4, round((float) ($tableData['size'] ?? 8), 2)));

                $attributes = [
                    'pos_x' => round((float) ($tableData['x'] ?? 50), 2),
                    'pos_y' => round((float) ($tableData['y'] ?? 50), 2),
                    'shape' => in_array($tableData['shape'] ?? null, ['round', 'square'], true) ? $tableData['shape'] : 'square',
                    'size' => $size,
                    // Rectangular solo aplica a mesas cuadradas (ver
                    // migracion add_height_to_dining_tables_table); si no
                    // mandan height explicito se cae al mismo valor que
                    // size (uniforme), igual que siempre.
                    'height' => isset($tableData['height']) ? min(20, max(4, round((float) $tableData['height'], 2))) : $size,
                ];

                if ($isExisting) {
                    $submittedExistingIds[] = (int) $rawId;
                    DiningTable::query()->whereKey((int) $rawId)->update($attributes);

                    continue;
                }

                $name = $this->uniqueName(trim((string) ($tableData['name'] ?? '')), $usedNames);
                $usedNames[] = $name;

                DiningTable::query()->create(array_merge($attributes, [
                    'company_id' => $company->id,
                    'branch_id' => $this->branchId,
                    'name' => $name,
                    'capacity' => is_numeric($tableData['capacity'] ?? null) ? (int) $tableData['capacity'] : null,
                    'status' => RecordStatus::Active->value,
                    'occupancy_status' => 'free',
                ]));
            }

            // Una mesa activa que ya existia pero no vino en este guardado es
            // una mesa que el dueño quito del plano con el boton de borrar —
            // se archiva (status inactive), nunca se elimina fisicamente
            // (regla general del proyecto para registros comerciales).
            $removedIds = array_diff($activeExistingIds, $submittedExistingIds);
            if (count($removedIds) > 0) {
                DiningTable::query()->whereIn('id', $removedIds)->update(['status' => RecordStatus::Inactive->value]);
                DiningTable::renumberActiveTables($company->id, $this->branchId);
            }

            // Las cajas ya existen (se crean desde Admin > Estructura); aca
            // solo se decide si el dueño las quiere mostrar en el plano
            // (placed) y en que posicion — nunca se crea una caja nueva.
            foreach ($registers as $registerData) {
                $registerId = (int) ($registerData['id'] ?? 0);

                if (! in_array($registerId, $registerIds, true)) {
                    continue;
                }

                $placed = (bool) ($registerData['placed'] ?? false);

                CashRegister::query()->whereKey($registerId)->update([
                    'pos_x' => $placed ? round((float) ($registerData['x'] ?? 50), 2) : null,
                    'pos_y' => $placed ? round((float) ($registerData['y'] ?? 50), 2) : null,
                    'size' => min(20, max(4, round((float) ($registerData['size'] ?? 6), 2))),
                ]);
            }

            // Crear/mover/eliminar un obstaculo ya llega al servidor de
            // inmediato (DiningFloorPlanObstaclesController) — aca solo
            // queda pendiente su tamaño (ancho/alto), igual que el
            // redimensionado de una mesa.
            foreach ($obstacles as $obstacleData) {
                $obstacleId = (int) ($obstacleData['id'] ?? 0);

                if (! in_array($obstacleId, $obstacleIds, true)) {
                    continue;
                }

                DiningObstacle::query()->whereKey($obstacleId)->update([
                    'width' => min(60, max(2, round((float) ($obstacleData['width'] ?? 10), 2))),
                    'height' => min(60, max(2, round((float) ($obstacleData['height'] ?? 10), 2))),
                ]);
            }
        });

        $this->toast('Plano guardado correctamente.');
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
            ->get()
            ->sortBy(fn (DiningTable $table) => is_numeric($table->name) ? (int) $table->name : PHP_INT_MAX)
            ->values();
    }

    public function cashRegisters(): Collection
    {
        return $this->registersQuery()
            ->where('status', RecordStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    public function obstacles(): Collection
    {
        return $this->obstaclesQuery()->orderBy('id')->get();
    }

    public function floorPlan(): ?DiningFloorPlan
    {
        return DiningFloorPlan::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId)
            ->first();
    }

    public function render(): View
    {
        return view('livewire.dining.dining-floor-plan-page', [
            'branches' => $this->branches(),
            'tables' => $this->tables(),
            'cashRegisters' => $this->cashRegisters(),
            'obstacles' => $this->obstacles(),
            'obstacleColor' => app(CompanySettings::class)->get($this->currentCompany(), 'dining', 'obstacle_color'),
            'floorPlan' => $this->floorPlan(),
        ])->layout('layouts.app', [
            'header' => view('components.page-title', [
                'title' => 'Plano del salon',
                'description' => 'Dibuja el contorno de tu restaurante y ubica cada mesa como esta en la vida real.',
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

    protected function registersQuery()
    {
        return CashRegister::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId);
    }

    protected function obstaclesQuery()
    {
        return DiningObstacle::query()
            ->where('company_id', $this->currentCompany()->id)
            ->where('branch_id', $this->branchId);
    }

    protected function ensureOwner(): void
    {
        abort_unless(
            auth()->id() === $this->currentCompany()->owner_user_id,
            403,
            'Solo el dueño de la empresa puede editar el plano del salon.'
        );
    }

    protected function uniqueName(string $name, array $usedNames): string
    {
        if ($name === '') {
            $name = '1';
        }

        if (! in_array($name, $usedNames, true)) {
            return $name;
        }

        // El nombre calculado en el cliente choco con una mesa activa que ya
        // existia (por ejemplo, dos mesas nuevas agregadas en el mismo
        // guardado) — se busca el siguiente numero libre en vez de fallar.
        $number = is_numeric($name) ? (int) $name : 1;

        do {
            $number++;
            $candidate = (string) $number;
        } while (in_array($candidate, $usedNames, true));

        return $candidate;
    }
}
