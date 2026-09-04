<?php

namespace App\Http\Controllers\Dining;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dining\Concerns\ValidatesJsonRequests;
use App\Models\Company;
use App\Models\DiningTable;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Crear/mover/eliminar una mesa en el editor visual del plano se guarda de
 * inmediato, sin esperar al boton "Guardar plano" (ver
 * dining-floor-plan-page.blade.php y Alpine.data('diningFloorPlanEditor')
 * en resources/js/app.js).
 *
 * A proposito NO son acciones de Livewire, aunque el resto del editor si lo
 * es: cada llamada a un metodo de Livewire dispara un re-render + remorfeo
 * de TODO el componente, y el x-for anidado de las sillas (mesa > silla)
 * demostro en pruebas reales de navegador no sobrevivir ese remorfeo de
 * forma confiable en cada interaccion (aun con wire:ignore en el contenedor
 * — los elementos creados DESPUES del montaje inicial seguian quedando sin
 * poder arrastrarse hasta refrescar la pagina). Un fetch() liso a un
 * endpoint JSON normal nunca dispara el ciclo de vida de Livewire, asi que
 * esa clase de problema queda eliminada de raiz, no parchada.
 */
class DiningFloorPlanTablesController extends Controller
{
    use ValidatesJsonRequests;

    public function store(Request $request): JsonResponse
    {
        $company = $this->currentCompany();
        $this->ensureOwner($company);

        $validated = $this->validateJson($request, [
            'branch_id' => ['required', 'integer'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'shape' => ['required', 'string'],
            'size' => ['required', 'numeric'],
            'height' => ['nullable', 'numeric'],
        ]);

        abort_unless(
            $company->branches()
                ->where('id', $validated['branch_id'])
                ->where('status', RecordStatus::Active->value)
                ->exists(),
            404
        );

        $size = min(20, max(4, round($validated['size'], 2)));

        $table = DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $validated['branch_id'],
            'name' => DiningTable::nextNumberFor($company->id, (int) $validated['branch_id']),
            'capacity' => null,
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
            'pos_x' => round($validated['x'], 2),
            'pos_y' => round($validated['y'], 2),
            'shape' => in_array($validated['shape'], ['round', 'square'], true) ? $validated['shape'] : 'square',
            'size' => $size,
            // Nueva mesa siempre nace uniforme (ancho = alto); estirarla a
            // rectangular es una accion aparte (arrastrar el asa, solo
            // valida para shape=square) que se guarda con "Guardar plano".
            'height' => isset($validated['height']) ? min(20, max(4, round($validated['height'], 2))) : $size,
        ]);

        return response()->json($this->tablePayload($table));
    }

    public function updatePosition(Request $request, DiningTable $table): JsonResponse
    {
        $company = $this->currentCompany();
        $this->ensureOwner($company);
        abort_unless($table->company_id === $company->id, 404);

        $validated = $this->validateJson($request, [
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
        ]);

        $table->update([
            'pos_x' => min(100, max(0, round($validated['x'], 2))),
            'pos_y' => min(100, max(0, round($validated['y'], 2))),
        ]);

        return response()->json(['ok' => true]);
    }

    // Archiva la mesa y renumera de una vez — el arreglo que devuelve es la
    // fuente de verdad que el cliente usa para reemplazar su `tables`, asi
    // los numeros de las mesas restantes quedan correctos de inmediato (no
    // solo despues de "Guardar plano").
    public function destroy(DiningTable $table): JsonResponse
    {
        $company = $this->currentCompany();
        $this->ensureOwner($company);
        abort_unless($table->company_id === $company->id, 404);

        $table->update(['status' => RecordStatus::Inactive->value]);
        DiningTable::renumberActiveTables($company->id, $table->branch_id);

        $remaining = DiningTable::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $table->branch_id)
            ->where('status', RecordStatus::Active->value)
            ->get()
            ->sortBy(fn (DiningTable $t) => is_numeric($t->name) ? (int) $t->name : PHP_INT_MAX)
            ->values()
            ->map(fn (DiningTable $t) => $this->tablePayload($t))
            ->all();

        return response()->json($remaining);
    }

    protected function currentCompany(): Company
    {
        return app(CurrentCompany::class)->company()
            ?? abort(404, 'No hay una empresa activa seleccionada.');
    }

    protected function ensureOwner(Company $company): void
    {
        abort_unless(
            auth()->id() === $company->owner_user_id,
            403,
            'Solo el dueño de la empresa puede editar el plano del salon.'
        );
    }

    protected function tablePayload(DiningTable $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
            'capacity' => $table->capacity,
            'shape' => $table->shape,
            'size' => (float) $table->size,
            'height' => (float) ($table->height ?? $table->size),
            'x' => (float) ($table->pos_x ?? 50),
            'y' => (float) ($table->pos_y ?? 50),
        ];
    }
}
