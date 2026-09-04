<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class NavigationVisibilityTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_basic_plan_hides_navigation_entries_and_feature_tabs_not_available(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $response = $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('masters.categories'));

        $response->assertOk();
        $response->assertDontSee('&lt;div&gt;', false);
        $response->assertDontSee(route('products.variants'), false);
        $response->assertDontSee(route('products.imports'), false);
        $response->assertDontSee(route('inventory.imports'), false);
        $response->assertDontSee('Promociones');
        $response->assertDontSee('Fidelizacion');
        $response->assertDontSee('Credito');
    }

    public function test_basic_plan_hides_frozen_sales_entry_from_sales_navigation(): void
    {
        [$owner, $company] = $this->fixture('basic');

        $response = $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.index'));

        // Ventas congeladas ya no tiene ruta propia (se uso Ventas + POS en
        // un solo workspace, ver App\Livewire\Sales\SalesWorkspacePage) —
        // solo queda por verificar que el texto del tab no aparezca.
        $response->assertOk();
        $response->assertDontSee('Congeladas');
    }

    protected function fixture(string $planCode): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Navegacion Plan '.$planCode,
        ]);

        $this->assignCompanyPlan($company, $planCode);

        return [$owner, $company];
    }
}
