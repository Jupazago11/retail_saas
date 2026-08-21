<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class SalesWorkspacePageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_sales_index_route_mounts_both_tabs_for_a_user_with_both_permissions(): void
    {
        [$owner, $company] = $this->fixture();

        $response = $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.index'));

        $response->assertOk();
        // Las dos pestañas del contenedor.
        $response->assertSee('Ventas');
        $response->assertSee('POS');
        // Contenido propio de cada componente hijo, ambos montados a la vez.
        $response->assertSee('Ventas registradas');
        $response->assertSee('Escanea o busca un producto');
        // /sales entra con la pestaña Ventas activa.
        $response->assertSee("tab: 'history'", false);
    }

    public function test_sales_pos_route_mounts_both_tabs_with_pos_active(): void
    {
        [$owner, $company] = $this->fixture();

        $response = $this->actingAs($owner)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.pos'));

        $response->assertOk();
        $response->assertSee('Ventas registradas');
        $response->assertSee('Escanea o busca un producto');
        $response->assertSee("tab: 'pos'", false);
    }

    public function test_sales_index_hides_pos_tab_and_child_for_a_view_only_user(): void
    {
        [, $company] = $this->fixture();
        $viewer = User::factory()->create();

        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_view_only',
            'display_name' => 'Solo consulta ventas',
            'status' => RecordStatus::Active->value,
        ]);
        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );
        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('sales.index'));

        $response->assertOk();
        $response->assertSee('Ventas registradas');
        // Sin sales.create no se monta PosPage en absoluto (no solo se
        // esconde con CSS): ni el boton "POS" ni su contenido aparecen.
        $response->assertDontSee('Escanea o busca un producto');
        $response->assertDontSee('Cobrar');
    }

    protected function fixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Sales Workspace SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');

        return [$owner, $company];
    }
}
