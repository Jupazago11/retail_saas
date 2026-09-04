<?php

namespace App\Http\Controllers\Dining;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dining\Concerns\ValidatesJsonRequests;
use App\Models\Company;
use App\Models\DiningObstacle;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Crear/mover/eliminar un obstaculo (rectangulo oscuro en el plano — una
 * columna, una escalera, una pared — sin ninguna relacion con ventas u
 * ordenes) se guarda de inmediato, igual que las mesas — ver
 * DiningFloorPlanTablesController para el porque de que esto NO sea una
 * accion de Livewire. El tamaño (width/height, para estirarlo a la forma
 * real del obstaculo) se sigue guardando en el lote de "Guardar plano"
 * (DiningFloorPlanPage::save()), igual que el tamaño de una mesa.
 */
class DiningFloorPlanObstaclesController extends Controller
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
        ]);

        abort_unless(
            $company->branches()
                ->where('id', $validated['branch_id'])
                ->where('status', RecordStatus::Active->value)
                ->exists(),
            404
        );

        $obstacle = DiningObstacle::query()->create([
            'company_id' => $company->id,
            'branch_id' => $validated['branch_id'],
            'pos_x' => round($validated['x'], 2),
            'pos_y' => round($validated['y'], 2),
            'width' => 10,
            'height' => 10,
        ]);

        return response()->json($this->obstaclePayload($obstacle));
    }

    public function updatePosition(Request $request, DiningObstacle $obstacle): JsonResponse
    {
        $company = $this->currentCompany();
        $this->ensureOwner($company);
        abort_unless($obstacle->company_id === $company->id, 404);

        $validated = $this->validateJson($request, [
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
        ]);

        $obstacle->update([
            'pos_x' => min(100, max(0, round($validated['x'], 2))),
            'pos_y' => min(100, max(0, round($validated['y'], 2))),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(DiningObstacle $obstacle): JsonResponse
    {
        $company = $this->currentCompany();
        $this->ensureOwner($company);
        abort_unless($obstacle->company_id === $company->id, 404);

        // Borrado fisico a proposito: es solo un marcador de layout, sin
        // historial de ninguna venta/orden que preservar (a diferencia de
        // una mesa, que se archiva).
        $obstacle->delete();

        return response()->json(['ok' => true]);
    }

    // Un solo color por empresa (no por obstaculo) — el input junto al
    // titulo "Obstaculos" en el editor lo guarda de inmediato, igual que
    // crear/mover/eliminar un obstaculo.
    public function updateColor(Request $request, CompanySettings $companySettings): JsonResponse
    {
        $company = $this->currentCompany();
        $this->ensureOwner($company);

        $validated = $this->validateJson($request, [
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $companySettings->set($company, 'dining', 'obstacle_color', $validated['color']);

        return response()->json(['ok' => true]);
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

    protected function obstaclePayload(DiningObstacle $obstacle): array
    {
        return [
            'id' => $obstacle->id,
            'width' => (float) $obstacle->width,
            'height' => (float) $obstacle->height,
            'x' => (float) $obstacle->pos_x,
            'y' => (float) $obstacle->pos_y,
        ];
    }
}
