<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CompanySelectionTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_authenticated_user_without_companies_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('companies.select'));
    }

    public function test_company_selection_page_can_create_first_company(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Company\SelectCompanyPage::class)
            ->set('legalName', 'Comercial Aurora SAS')
            ->set('displayName', 'Aurora Centro')
            ->set('taxId', '901234567')
            ->call('createCompany')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('companies', [
            'display_name' => 'Aurora Centro',
        ]);

        $this->assertSame(
            'aurora-centro',
            \App\Models\Company::query()->firstOrFail()->slug,
        );
    }

    public function test_single_company_is_auto_selected_by_middleware(): void
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Retail Unico SAS',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee($company->display_name);
        $this->assertSame($company->id, session(CurrentCompany::SESSION_KEY));
    }

    public function test_company_selection_page_shows_validation_error_when_owner_reaches_company_limit(): void
    {
        $user = User::factory()->create();

        $firstCompany = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Primera Empresa UI SAS',
        ]);
        $this->assignCompanyPlan($firstCompany, 'basic');

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Company\SelectCompanyPage::class)
            ->set('legalName', 'Segunda Empresa UI SAS')
            ->call('createCompany')
            ->assertHasErrors(['legalName']);

        $this->assertDatabaseMissing('companies', [
            'legal_name' => 'Segunda Empresa UI SAS',
        ]);
    }
}
