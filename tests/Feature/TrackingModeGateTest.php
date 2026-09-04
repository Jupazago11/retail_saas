<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Company\TrackingModeGate;
use App\Models\BusinessType;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class TrackingModeGateTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_gate_shows_for_a_restaurant_company_without_tracking_mode_set(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Receta Nueva SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TrackingModeGate::class)
            ->assertSee('Modo de venta de tus platos');
    }

    public function test_gate_stays_hidden_for_a_general_business_type_company(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Tienda General SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'general')->value('id')]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TrackingModeGate::class)
            ->assertDontSee('Modo de venta de tus platos');
    }

    public function test_gate_stays_hidden_when_tracking_mode_already_answered(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Receta Respondida SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'group_key' => 'inventory',
            'setting_key' => 'tracking_mode',
            'value_type' => 'string',
            'value_string' => 'simple',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TrackingModeGate::class)
            ->assertDontSee('Modo de venta de tus platos');
    }

    public function test_non_privileged_user_sees_a_read_only_message(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Receta Restringida SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);

        $seller = User::factory()->create();
        $company->users()->attach($seller->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($seller);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TrackingModeGate::class)
            ->assertDontSee('Guardar')
            ->assertSee('Pide a un administrador');
    }

    public function test_save_persists_the_selected_tracking_mode(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Receta Guardada SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(TrackingModeGate::class)
            ->set('trackingMode', 'recipe')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('recipe', CompanySetting::query()
            ->where('company_id', $company->id)
            ->where('group_key', 'inventory')
            ->where('setting_key', 'tracking_mode')
            ->value('value_string'));
    }
}
