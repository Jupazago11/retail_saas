<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CompanyProvisioningTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_creating_a_company_provisions_primary_structures_and_membership(): void
    {
        $owner = User::factory()->create();

        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Comercial San Pedro SAS',
            'display_name' => 'San Pedro Centro',
            'tax_id' => '900123456',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'owner_user_id' => $owner->id,
            'slug' => 'san-pedro-centro',
        ]);

        // El propietario tiene acceso total via la columna company_role
        // (ver CurrentCompanyPermissionResolver::has()) — no necesita
        // ningun rol personalizado ligado.
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'company_role' => 'owner',
            'company_role_id' => null,
        ]);

        $this->assertDatabaseHas('branches', [
            'company_id' => $company->id,
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'company_id' => $company->id,
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('cash_registers', [
            'company_id' => $company->id,
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        // La suscripcion nace "pending": el platform_super_admin debe elegir
        // el vertical de la empresa y activarla antes de que quede "active"
        // (ver docs/decisiones-tecnicas.md, "Tipo de negocio por empresa").
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
    }

    public function test_current_company_service_only_accepts_linked_companies(): void
    {
        $user = User::factory()->create();
        $linkedCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Vitrina Uno SAS',
        ]);
        $otherOwner = User::factory()->create();
        $otherCompany = app(CreateCompany::class)->handle($otherOwner, [
            'legal_name' => 'Vitrina Dos SAS',
        ]);

        $currentCompany = app(CurrentCompany::class);
        $currentCompany->setForUser($user, $linkedCompany);

        $this->assertSame($linkedCompany->id, $currentCompany->id());

        $this->expectException(HttpException::class);
        $currentCompany->setForUser($user, $otherCompany);
    }

    public function test_basic_owner_cannot_create_a_second_company(): void
    {
        $owner = User::factory()->create();

        $firstCompany = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Primera Basic SAS',
        ]);
        $this->assignCompanyPlan($firstCompany, 'basic');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El limite actual permite hasta 1 empresa(s) para este propietario.');

        app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Segunda Basic SAS',
        ]);
    }

    public function test_premium_owner_can_create_up_to_three_companies(): void
    {
        $owner = User::factory()->create();

        $firstCompany = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Empresa Uno Premium SAS',
        ]);
        $this->assignCompanyPlan($firstCompany, 'premium');

        $secondCompany = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Empresa Dos Premium SAS',
        ]);

        $thirdCompany = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Empresa Tres Premium SAS',
        ]);

        $this->assertDatabaseHas('companies', ['id' => $secondCompany->id]);
        $this->assertDatabaseHas('companies', ['id' => $thirdCompany->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El limite actual permite hasta 3 empresa(s) para este propietario.');

        app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Empresa Cuatro Premium SAS',
        ]);
    }
}
