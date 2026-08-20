<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Customers\CreateCustomer;
use App\Enums\RecordStatus;
use App\Livewire\Customers\CustomersPage;
use App\Models\Customer;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CustomersPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_customers_page_can_create_update_toggle_and_scope_customers(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();
        $otherOwner = User::factory()->create();
        $otherCompany = app(CreateCompany::class)->handle($otherOwner, [
            'legal_name' => 'Otra Empresa SAS',
        ]);

        app(CreateCustomer::class)->handle($otherCompany, [
            'first_name' => 'Cliente',
            'last_name' => 'Ajeno',
        ]);

        $this->actingAs($user);

        Livewire::test(CustomersPage::class)
            ->set('documentType', 'CC')
            ->set('documentNumber', '1012345678')
            ->set('firstName', 'Laura')
            ->set('lastName', 'Gomez')
            ->set('phone', '3009998877')
            ->set('email', 'laura@example.test')
            ->call('saveCustomer')
            ->assertHasNoErrors();

        $customer = Customer::query()
            ->where('company_id', $company->id)
            ->with('person')
            ->firstOrFail();

        $this->assertSame('Laura', $customer->person->first_name);
        $this->assertSame('1012345678', $customer->person->document_number);

        Livewire::test(CustomersPage::class)
            ->call('editCustomer', $customer->id)
            ->set('lastName', 'Gomez Rios')
            ->set('status', RecordStatus::Inactive->value)
            ->call('saveCustomer')
            ->assertHasNoErrors();

        $customer->refresh();
        $customer->load('person');

        $this->assertSame('Gomez Rios', $customer->person->last_name);
        $this->assertSame(RecordStatus::Inactive->value, $customer->status);

        Livewire::test(CustomersPage::class)
            ->call('toggleCustomerStatus', $customer->id);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => RecordStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('Laura');
        $response->assertDontSee('Cliente Ajeno');
    }

    public function test_customers_page_filters_by_status_pill(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        // Nombres sin relacion con "Activos"/"Inactivos" (las propias
        // etiquetas de los pills de filtro), para que assertDontSee no
        // choque con texto que siempre esta en la pagina.
        app(CreateCustomer::class)->handle($company, [
            'first_name' => 'ClienteUno',
        ]);
        $customerDos = app(CreateCustomer::class)->handle($company, [
            'first_name' => 'ClienteDos',
        ]);
        $customerDos->update(['status' => RecordStatus::Inactive->value]);

        $this->actingAs($user);

        Livewire::test(CustomersPage::class)
            ->assertSee('ClienteUno')
            ->assertSee('ClienteDos')
            ->call('setStatusFilter', 'active')
            ->assertSee('ClienteUno')
            ->assertDontSee('ClienteDos')
            ->call('setStatusFilter', 'inactive')
            ->assertDontSee('ClienteUno')
            ->assertSee('ClienteDos');
    }

    public function test_customers_page_paginates_results(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        $letters = ['Alfa', 'Beta', 'Gama', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa', 'Lambda', 'Sigma'];

        foreach ($letters as $letter) {
            app(CreateCustomer::class)->handle($company, [
                'first_name' => 'ClientePag'.$letter,
            ]);
        }

        $this->actingAs($user);

        // Orden por id descendente: pagina 1 trae los ultimos 10 creados
        // (Sigma..Gama), pagina 2 trae los 2 mas antiguos (Beta, Alfa).
        $component = Livewire::test(CustomersPage::class)
            ->set('perPage', 10)
            ->assertSee('ClientePagSigma')
            ->assertSee('ClientePagGama')
            ->assertDontSee('ClientePagBeta')
            ->assertDontSee('ClientePagAlfa');

        $this->assertCount(10, $component->instance()->customers()->items());

        $component->call('nextPage')
            ->assertSee('ClientePagBeta')
            ->assertSee('ClientePagAlfa');

        $this->assertCount(2, $component->instance()->customers()->items());
    }

    public function test_customers_page_route_is_forbidden_without_customers_permission(): void
    {
        [, $company] = $this->actingUserWithCurrentCompany();
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('customers.index'))
            ->assertForbidden();
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Clientes Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'pro');

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }
}
