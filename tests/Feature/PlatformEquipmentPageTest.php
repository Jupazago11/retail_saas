<?php

namespace Tests\Feature;

use App\Livewire\Platform\PlatformEquipmentPage;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformEquipmentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_create_an_equipment_type_with_auto_generated_code(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(PlatformEquipmentPage::class)
            ->call('openCreate')
            ->set('name', 'Lector de codigo QR')
            ->set('unitCost', '95000')
            ->set('monthlyPrice', '11000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Lector de codigo QR');

        $type = EquipmentType::query()->where('name', 'Lector de codigo QR')->firstOrFail();

        $this->assertSame('LECTOR_DE_CODIGO_QR', $type->code);
        $this->assertSame('active', $type->status);
        $this->assertSame('95000.00', (string) $type->unit_cost);
        $this->assertSame('11000.00', (string) $type->monthly_price);
    }

    public function test_platform_admin_can_edit_an_equipment_type_without_changing_its_code(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $type = EquipmentType::factory()->create(['code' => 'THERMAL_PRINTER', 'monthly_price' => 15000]);

        $this->actingAs($admin);

        Livewire::test(PlatformEquipmentPage::class)
            ->call('startEdit', $type->id)
            ->set('monthlyPrice', '18000')
            ->call('save')
            ->assertHasNoErrors();

        $type->refresh();
        $this->assertSame('THERMAL_PRINTER', $type->code);
        $this->assertSame('18000.00', (string) $type->monthly_price);
    }

    public function test_toggle_status_flips_active_and_inactive(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $type = EquipmentType::factory()->create();

        $this->actingAs($admin);

        Livewire::test(PlatformEquipmentPage::class)->call('toggleStatus', $type->id);
        $this->assertSame('inactive', $type->fresh()->status);

        Livewire::test(PlatformEquipmentPage::class)->call('toggleStatus', $type->id);
        $this->assertSame('active', $type->fresh()->status);
    }

    public function test_non_platform_admin_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($viewer);

        Livewire::test(PlatformEquipmentPage::class)->assertForbidden();
    }
}
