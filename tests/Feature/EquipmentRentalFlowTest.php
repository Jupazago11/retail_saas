<?php

namespace Tests\Feature;

use App\Enums\EquipmentRentalStatus;
use App\Livewire\Platform\SubscriptionsPage;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\EquipmentRental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentRentalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_manage_equipment_through_the_livewire_component(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($admin);

        $component = Livewire::test(SubscriptionsPage::class)
            ->call('openEquipmentModal', $company->id)
            ->assertSet('showEquipmentModal', true)
            ->assertSet('equipmentCompanyId', $company->id)
            ->call('requestEquipment', 'barcode_scanner')
            ->call('addEquipment', 'thermal_printer');

        $requested = EquipmentRental::where('company_id', $company->id)
            ->where('equipment_type', 'barcode_scanner')->firstOrFail();
        $printer1 = EquipmentRental::where('company_id', $company->id)
            ->where('equipment_type', 'thermal_printer')->firstOrFail();

        $this->assertSame(EquipmentRentalStatus::Requested, $requested->status);
        $this->assertNull($requested->started_at);
        $this->assertSame(EquipmentRentalStatus::Active, $printer1->status);
        $this->assertNotNull($printer1->started_at);

        $component->call('fulfillEquipment', $requested->id)
            ->call('addEquipment', 'thermal_printer');

        $this->assertSame(EquipmentRentalStatus::Active, $requested->fresh()->status);

        $printerCount = EquipmentRental::where('company_id', $company->id)
            ->where('equipment_type', 'thermal_printer')->where('status', 'active')->count();
        $scannerCount = EquipmentRental::where('company_id', $company->id)
            ->where('equipment_type', 'barcode_scanner')->where('status', 'active')->count();

        $this->assertSame(2, $printerCount);
        $this->assertSame(1, $scannerCount);

        // reemplazo: la unidad original queda 'returned', se crea una nueva 'active' enlazada
        $component->call('replaceEquipment', $printer1->id);
        $replacement = EquipmentRental::where('replaces_rental_id', $printer1->id)->firstOrFail();

        $this->assertSame(EquipmentRentalStatus::Returned, $printer1->fresh()->status);
        $this->assertSame(EquipmentRentalStatus::Active, $replacement->status);
        $this->assertEquals($printer1->monthly_price, $replacement->monthly_price);

        // cancelar el escaner: deja de facturarse de inmediato, pero requiere devolucion fisica
        $component->call('requestEquipmentReturn', $requested->id);
        $this->assertSame(EquipmentRentalStatus::PendingReturn, $requested->fresh()->status);

        $component->call('markEquipmentReturned', $requested->id);
        $this->assertSame(EquipmentRentalStatus::Returned, $requested->fresh()->status);

        $actions = AuditLog::where('company_id', $company->id)
            ->where('auditable_type', EquipmentRental::class)
            ->pluck('action')->all();

        $this->assertContains('equipment.requested', $actions);
        $this->assertContains('equipment.added', $actions);
        $this->assertContains('equipment.fulfilled', $actions);
        $this->assertContains('equipment.replaced', $actions);
        $this->assertContains('equipment.replacement_created', $actions);
        $this->assertContains('equipment.return_requested', $actions);
        $this->assertContains('equipment.returned', $actions);
    }

    public function test_non_platform_admin_cannot_manage_equipment(): void
    {
        $regularUser = User::factory()->create(['is_platform_admin' => false]);
        $company = Company::factory()->create(['owner_user_id' => $regularUser->id]);

        $this->actingAs($regularUser);

        Livewire::test(SubscriptionsPage::class)
            ->assertForbidden();
    }
}
