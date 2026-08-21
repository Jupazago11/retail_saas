<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Company\CreateCashRegister;
use App\Enums\RecordStatus;
use App\Livewire\Company\PrinterSetupGate;
use App\Models\CashRegister;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PrinterSetupGateTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_gate_shows_for_a_freshly_created_company(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Impresora Nueva SAS',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PrinterSetupGate::class)
            ->assertSee('Caja Principal')
            ->assertSee('Tipo de impresora');
    }

    public function test_gate_stays_hidden_when_every_register_already_has_a_printer_type(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Impresora Migrada SAS',
        ]);
        // Simula el backfill que hace la migracion sobre cajas que ya
        // existian antes de este cambio.
        $company->cashRegisters()->update(['printer_type' => 'thermal_80mm']);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PrinterSetupGate::class)
            ->assertDontSee('Tipo de impresora');
    }

    public function test_non_privileged_user_sees_a_read_only_message(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Impresora Restringida SAS',
        ]);
        $seller = User::factory()->create();
        $company->users()->attach($seller->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($seller);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PrinterSetupGate::class)
            ->assertDontSee('Guardar')
            ->assertSee('Pide a un administrador');
    }

    public function test_save_resolves_every_pending_register_in_one_submit(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Impresora Multiple SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');
        $branch = $company->branches()->firstOrFail();

        app(CreateCashRegister::class)->handle($company, [
            'branch_id' => $branch->id,
            'name' => 'Caja Norte',
            'code' => 'norte',
            'printer_type' => 'thermal_58mm',
        ], $owner);
        // Deja una segunda caja pendiente a proposito, saltando el flujo
        // normal de creacion (que ya exige printer_type) para simular el
        // caso defensivo de mas de una caja sin impresora a la vez.
        CashRegister::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Caja Sur',
            'code' => 'SUR',
            'status' => RecordStatus::Active->value,
            'is_primary' => false,
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pendingBefore = CashRegister::query()->where('company_id', $company->id)->whereNull('printer_type')->count();
        $this->assertSame(2, $pendingBefore);

        $component = Livewire::test(PrinterSetupGate::class);
        $registerIds = $company->cashRegisters()->whereNull('printer_type')->pluck('id');

        foreach ($registerIds as $id) {
            $component->set("printerTypes.{$id}", 'letter_a4');
        }

        $component->call('save')->assertHasNoErrors();

        $this->assertSame(0, CashRegister::query()->where('company_id', $company->id)->whereNull('printer_type')->count());
    }
}
