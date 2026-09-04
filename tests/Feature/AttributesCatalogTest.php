<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Products\AttributesPage;
use App\Models\Attribute;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class AttributesCatalogTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_attributes_page_can_create_update_archive_and_restore_attributes_and_values(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        $this->actingAs($user);

        Livewire::test(AttributesPage::class)
            ->set('name', 'Color')
            ->set('code', 'COL')
            ->call('saveAttribute')
            ->assertHasNoErrors();

        $attribute = Attribute::query()->where('company_id', $company->id)->firstOrFail();

        Livewire::test(AttributesPage::class)
            ->call('editAttribute', $attribute->id)
            ->set('name', 'Color Comercial')
            ->call('saveAttribute')
            ->assertHasNoErrors();

        Livewire::test(AttributesPage::class)
            ->call('selectAttribute', $attribute->id)
            ->set('value', 'Rojo')
            ->call('saveValue')
            ->assertHasNoErrors();

        $value = $attribute->values()->firstOrFail();

        Livewire::test(AttributesPage::class)
            ->call('selectAttribute', $attribute->id)
            ->call('editValue', $value->id)
            ->set('value', 'Rojo Vivo')
            ->call('saveValue')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->id,
            'name' => 'Color Comercial',
        ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->id,
            'value' => 'Rojo Vivo',
        ]);

        Livewire::test(AttributesPage::class)
            ->call('archiveValue', $value->id);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->id,
            'status' => RecordStatus::Archived->value,
        ]);

        Livewire::test(AttributesPage::class)
            ->call('restoreValue', $value->id);

        Livewire::test(AttributesPage::class)
            ->call('archiveAttribute', $attribute->id);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->id,
            'status' => RecordStatus::Archived->value,
        ]);

        Livewire::test(AttributesPage::class)
            ->call('restoreAttribute', $attribute->id);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->id,
            'status' => RecordStatus::Inactive->value,
        ]);
    }

    public function test_attributes_page_only_shows_records_for_current_company(): void
    {
        [$user, $company] = $this->actingUserWithCurrentCompany();

        Attribute::query()->create([
            'company_id' => $company->id,
            'name' => 'Local',
            'code' => 'LOC',
            'status' => RecordStatus::Active->value,
        ]);

        // Dueño distinto: la empresa del usuario principal ya esta en el
        // limite de 1 empresa del plan basic (ver assignCompanyPlan arriba).
        $otherCompany = app(CreateCompany::class)->handle(User::factory()->create(), [
            'legal_name' => 'Segunda Empresa SAS',
        ]);

        Attribute::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Ajeno',
            'code' => 'AJE',
            'status' => RecordStatus::Active->value,
        ]);

        $response = $this->actingAs($user)->get(route('products.attributes'));

        $response->assertOk();
        $response->assertSee('Local');
        $response->assertDontSee('Ajeno');
    }

    protected function actingUserWithCurrentCompany(): array
    {
        $user = User::factory()->create();
        $company = app(CreateCompany::class)->handle($user, [
            'legal_name' => 'Atributos Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');

        session([CurrentCompany::SESSION_KEY => $company->id]);

        return [$user, $company];
    }
}
