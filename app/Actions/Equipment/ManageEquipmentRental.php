<?php

namespace App\Actions\Equipment;

use App\Enums\EquipmentRentalStatus;
use App\Models\Company;
use App\Models\EquipmentRental;
use App\Models\EquipmentType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * El alquiler de hardware (impresora termica, lector de codigo de barras) lo
 * gestiona unicamente la plataforma: el equipo es fisico, lo compra y lo
 * entrega el dueno del negocio, nunca es autoservicio desde la empresa
 * cliente. Cada unidad es su propia fila (no un simple on/off) para poder
 * llevar cantidades, reemplazos por dano y solicitudes aun no entregadas,
 * todo con fecha y auditado.
 */
class ManageEquipmentRental
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {
    }

    /**
     * La empresa pidio una unidad, pero todavia no se le entrega ni se le
     * factura.
     */
    public function requestUnit(Company $company, EquipmentType $type, ?User $actor = null, ?string $notes = null): EquipmentRental
    {
        $rental = DB::transaction(function () use ($company, $type, $notes) {
            return EquipmentRental::create([
                'company_id' => $company->id,
                'equipment_type' => $type->code,
                'company_sequence' => $this->nextCompanySequence($company, $type->code),
                'status' => EquipmentRentalStatus::Requested->value,
                'unit_cost' => $type->unit_cost,
                'monthly_price' => $type->monthly_price,
                'requested_at' => now(),
                'notes' => $notes,
            ]);
        });

        $this->auditLogger->logCreated($company, 'equipment.requested', $rental, $actor);

        return $rental;
    }

    /**
     * Se entrega una unidad nueva directamente (sin pasar por "solicitado"),
     * por ejemplo cuando el dueno la lleva de una vez sin que la pidieran
     * antes. Empieza a facturarse desde hoy.
     */
    public function addUnit(Company $company, EquipmentType $type, ?User $actor = null, ?string $notes = null): EquipmentRental
    {
        $rental = DB::transaction(function () use ($company, $type, $notes) {
            return EquipmentRental::create([
                'company_id' => $company->id,
                'equipment_type' => $type->code,
                'company_sequence' => $this->nextCompanySequence($company, $type->code),
                'status' => EquipmentRentalStatus::Active->value,
                'unit_cost' => $type->unit_cost,
                'monthly_price' => $type->monthly_price,
                'requested_at' => now(),
                'started_at' => now(),
                'notes' => $notes,
            ]);
        });

        $this->auditLogger->logCreated($company, 'equipment.added', $rental, $actor);

        return $rental;
    }

    /**
     * Se entrega fisicamente una unidad que estaba en estado "solicitado".
     * Aqui arranca la facturacion.
     */
    public function fulfillRequest(EquipmentRental $rental, ?User $actor = null): EquipmentRental
    {
        $before = $rental->replicate()->forceFill(['id' => $rental->id]);

        $rental->update([
            'status' => EquipmentRentalStatus::Active->value,
            'started_at' => now(),
        ]);

        $this->auditLogger->logUpdated($rental->company, 'equipment.fulfilled', $before, $rental->fresh(), $actor);

        return $rental;
    }

    /**
     * El alquiler es "de por vida" mientras la empresa siga activa: al
     * cancelar se deja de facturar de inmediato, pero el equipo sigue
     * siendo del negocio y debe devolverse antes de poder reasignarse.
     */
    public function requestReturn(EquipmentRental $rental, ?User $actor = null, ?string $notes = null): EquipmentRental
    {
        $before = $rental->replicate()->forceFill(['id' => $rental->id]);

        $rental->update([
            'status' => EquipmentRentalStatus::PendingReturn->value,
            'pending_return_at' => now(),
            'notes' => $notes ?? $rental->notes,
        ]);

        $this->auditLogger->logUpdated($rental->company, 'equipment.return_requested', $before, $rental->fresh(), $actor);

        return $rental;
    }

    public function markReturned(EquipmentRental $rental, ?User $actor = null): EquipmentRental
    {
        $before = $rental->replicate()->forceFill(['id' => $rental->id]);

        $rental->update([
            'status' => EquipmentRentalStatus::Returned->value,
            'returned_at' => now(),
        ]);

        $this->auditLogger->logUpdated($rental->company, 'equipment.returned', $before, $rental->fresh(), $actor);

        return $rental;
    }

    /**
     * Cambia una unidad danada/perdida por una nueva. La empresa sigue
     * pagando lo mismo (no es un alquiler nuevo), pero la unidad nueva tiene
     * su propio costo para llevar la recuperacion de inversion por separado.
     */
    public function markReplaced(EquipmentRental $rental, ?User $actor = null, ?string $notes = null): EquipmentRental
    {
        $company = $rental->company;
        $before = $rental->replicate()->forceFill(['id' => $rental->id]);

        $rental->update([
            'status' => EquipmentRentalStatus::Returned->value,
            'returned_at' => now(),
            'notes' => $notes ?? $rental->notes,
        ]);

        $this->auditLogger->logUpdated($company, 'equipment.replaced', $before, $rental->fresh(), $actor);

        $replacement = DB::transaction(function () use ($company, $rental, $notes) {
            $unitCost = EquipmentType::where('code', $rental->equipment_type)->value('unit_cost') ?? $rental->unit_cost;

            return EquipmentRental::create([
                'company_id' => $company->id,
                'equipment_type' => $rental->equipment_type,
                'company_sequence' => $this->nextCompanySequence($company, $rental->equipment_type),
                'status' => EquipmentRentalStatus::Active->value,
                'replaces_rental_id' => $rental->id,
                'unit_cost' => $unitCost,
                'monthly_price' => $rental->monthly_price,
                'requested_at' => now(),
                'started_at' => now(),
                'notes' => $notes,
            ]);
        });

        $this->auditLogger->logCreated($company, 'equipment.replacement_created', $replacement, $actor);

        return $replacement;
    }

    /**
     * Numeracion "#1, #2, #3..." independiente por empresa y por tipo de
     * equipo (no el id global de la tabla, que salta entre empresas y no
     * dice nada por si solo). Mismo patron que ya usa OpenCashSession para
     * el consecutivo de cajas.
     */
    protected function nextCompanySequence(Company $company, string $typeCode): int
    {
        $lastSequence = (int) EquipmentRental::query()
            ->where('company_id', $company->id)
            ->where('equipment_type', $typeCode)
            ->orderByDesc('company_sequence')
            ->lockForUpdate()
            ->value('company_sequence');

        return $lastSequence + 1;
    }
}
