<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Subscriptions\ChangeCompanySubscription;
use App\Livewire\Sales\FrozenSalesPage;
use App\Livewire\Sales\PosPage;
use App\Models\Plan;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesRequirementsMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_created_company_only_reports_the_missing_product_for_pos(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Abarrotes La Salida SAS',
        ]);

        // CreateCompany already provisions the primary branch, warehouse and cash
        // register automatically; only products are left for the merchant to add.
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'is_primary' => true]);
        $this->assertDatabaseHas('warehouses', ['company_id' => $company->id, 'is_primary' => true]);
        $this->assertDatabaseCount('products', 0);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PosPage::class)
            ->assertSee('Debes tener al menos un producto activo para usar el POS.')
            ->assertDontSee('una sucursal activa')
            ->assertDontSee('una bodega activa');
    }

    public function test_a_freshly_created_company_only_reports_the_missing_product_for_frozen_sales(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Abarrotes Congelados SAS',
        ]);

        // pos.frozen_sales is only included from the "pro" plan up.
        app(ChangeCompanySubscription::class)->handle($company, [
            'plan_id' => Plan::query()->where('code', 'pro')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(FrozenSalesPage::class)
            ->assertSee('Debes tener al menos un producto activo para congelar ventas.')
            ->assertDontSee('una sucursal activa')
            ->assertDontSee('una bodega activa');
    }
}
