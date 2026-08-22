<?php

namespace Tests\Feature;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Company\CreateCashRegister;
use App\Enums\RecordStatus;
use App\Livewire\Cash\CashSessionsPage;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CashSessionsPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_cashier_can_open_and_close_cash_session_from_cash_page(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('openingAmount', '45000')
            ->call('openSession')
            ->assertHasNoErrors();

        $session = $company->cashSessions()->firstOrFail();

        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('closingCountedAmount', '45000')
            ->call('closeSession')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_sessions', [
            'company_id' => $company->id,
            'cash_register_id' => $cashRegister->id,
            'status' => 'reconciled',
            'opening_amount' => '45000.00',
            'closing_counted_amount' => '45000.00',
            'difference_amount' => '0.00',
        ]);
    }

    public function test_cash_page_route_is_forbidden_without_cash_permissions(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Caja Route SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'sales_view_only',
            'display_name' => 'Solo ventas lectura',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'sales_view_only',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('cash.sessions'))
            ->assertForbidden();
    }

    public function test_open_cash_action_is_forbidden_without_cash_open_permission(): void
    {
        [$company, $user] = $this->companyWithCustomCashPermissions(['cash.close']);
        $branch = $company->branches()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('openingAmount', '10000')
            ->call('openSession')
            ->assertForbidden();
    }

    public function test_close_cash_action_is_forbidden_without_cash_close_permission(): void
    {
        [$company, $user] = $this->companyWithCustomCashPermissions(['cash.open']);
        $branch = $company->branches()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();
        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by' => $user->id,
            'opening_amount' => '22000',
        ]);

        $this->actingAs($user);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('closingSessionId', $session->id)
            ->set('closingCountedAmount', '22000')
            ->call('closeSession')
            ->assertForbidden();
    }

    public function test_calendar_cell_registers_list_every_register_with_its_closing_status(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $mainRegister = $company->cashRegisters()->firstOrFail();
        $secondRegister = app(CreateCashRegister::class)->handle($company, [
            'branch_id' => $branch->id,
            'name' => 'Caja 2',
            'code' => 'CAJA-2',
            'printer_type' => 'thermal_58mm',
        ], $cashier);

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Caja principal: se abre y se cierra el mismo dia, sin diferencia.
        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $mainRegister->id)
            ->set('openingAmount', '45000')
            ->call('openSession')
            ->assertHasNoErrors();
        $mainSession = $company->cashSessions()->where('cash_register_id', $mainRegister->id)->firstOrFail();
        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $mainSession->id)
            ->set('closingCountedAmount', '45000')
            ->call('closeSession')
            ->assertHasNoErrors();

        // Caja 2: se abre y se queda sin cerrar.
        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $secondRegister->id,
            'opened_by' => $cashier->id,
            'opening_amount' => '20000',
        ]);

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');
        $cell = collect($component->instance()->calendarCells())->firstWhere('date', $today);

        $this->assertNotNull($cell);
        $this->assertTrue($cell['hasSessions']);

        $registers = collect($cell['registers'])->keyBy('name');

        // Sin diferencia (contado == esperado), CloseCashSession la deja
        // directamente en 'reconciled', no en 'closed' — ver el test de
        // apertura/cierre de arriba, que confirma el mismo status.
        $this->assertSame('reconciled', $registers['Caja Principal']['status']);
        $this->assertSame(45000.0, $registers['Caja Principal']['closingCounted']);
        $this->assertNull($registers['Caja Principal']['difference']);

        $this->assertSame('open', $registers['Caja 2']['status']);
        $this->assertNull($registers['Caja 2']['closingCounted']);
    }

    public function test_a_register_left_open_from_a_previous_day_still_shows_up_today(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $session = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $register->id,
            'opened_by' => $cashier->id,
            'opening_amount' => '15000',
        ]);
        // Se abrio "ayer" y nadie la cerro — sigue open, solo que su
        // opened_at ya no cae en el dia de hoy.
        $session->update(['opened_at' => now()->subDay()]);

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');

        // Se ve en el popover de hoy...
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);
        $this->assertTrue($todayCell['hasSessions']);
        $this->assertSame('open', collect($todayCell['registers'])->firstWhere('name', $register->name)['status']);

        // ...y tambien se puede seleccionar y abrir desde "hoy" en el
        // historial, no solo desde el dia en que se abrio de verdad.
        $component->set('historyDate', $today);
        $options = $component->instance()->historyCashRegisterOptions();
        $this->assertTrue($options->contains('id', $register->id));

        $component->set('historyCashRegisterId', $register->id);
        $resolved = $component->instance()->historySession();
        $this->assertNotNull($resolved);
        $this->assertSame($session->id, $resolved->id);
    }

    public function test_a_register_opened_earlier_but_closed_today_shows_up_in_todays_cell(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Se abrio "hace dos dias" y se cierra hoy — exactamente el caso
        // real: Caja Principal se abrio el 11 y se cerro el 13.
        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '45000')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();
        $session->update(['opened_at' => now()->subDays(2)]);

        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('closingCountedAmount', '45000')
            ->call('closeSession')
            ->assertHasNoErrors();

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');

        // Aparece en el popover de hoy, no solo en el dia en que se abrio.
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);
        $this->assertTrue($todayCell['hasSessions']);
        $todayRegister = collect($todayCell['registers'])->firstWhere('name', $register->name);
        $this->assertNotNull($todayRegister);
        $this->assertSame('reconciled', $todayRegister['status']);

        // Y se puede seleccionar/ver desde "hoy" en el historial tambien.
        $component->set('historyDate', $today);
        $options = $component->instance()->historyCashRegisterOptions();
        $this->assertTrue($options->contains('id', $register->id));

        $component->set('historyCashRegisterId', $register->id);
        $resolved = $component->instance()->historySession();
        $this->assertNotNull($resolved);
        $this->assertSame($session->id, $resolved->id);
    }

    public function test_owner_can_open_the_cuadre_of_a_closed_register_with_a_difference_for_editing(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Edit Icon SAS']);
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '50000')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();

        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            // Contado por debajo de lo esperado (50000) — queda con
            // diferencia (falta), no reconciled.
            ->set('closingCountedAmount', '40000')
            ->call('closeSession')
            ->assertHasNoErrors();

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);
        $registerSummary = collect($todayCell['registers'])->first();

        $this->assertSame('closed', $registerSummary['status']);
        $this->assertSame($session->id, $registerSummary['sessionId']);
        $this->assertSame(-10000.0, $registerSummary['difference']);
        $this->assertTrue($component->instance()->canEditHistoricalCuadres());

        // El icono de editar del popover llama exactamente a esto.
        $component->call('openCuadre', $session->id);
        $this->assertSame($session->id, $component->get('cuadreSessionId'));
        $this->assertTrue($component->get('showCuadreModal'));
    }

    public function test_a_negative_expected_amount_is_flagged_as_negative_collection_not_as_a_surplus(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Recaudo Negativo SAS']);
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '180000')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();

        // Un pago (cuota del gas) mas grande que la base + ventas del dia
        // ($180.000 + $0): esperado = 180000 - 800000 = -620000.
        Livewire::test(CashSessionsPage::class)
            ->call('openCuadre', $session->id)
            ->set('newExpenseDescription', 'cuota del gas')
            ->set('newExpenseAmount', '800000')
            ->call('addExpense')
            ->assertHasNoErrors();

        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('closingCountedAmount', '15000')
            ->call('closeSession')
            ->assertHasNoErrors();

        $this->assertSame('-620000.00', $session->fresh()->closing_expected_amount);

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);
        $registerSummary = collect($todayCell['registers'])->first();

        $this->assertTrue($registerSummary['negativeExpected']);
        // El signo aritmetico de la diferencia sigue siendo positivo
        // (635000), pero eso ya no se debe leer/mostrar como "sobra".
        $this->assertSame(635000.0, $registerSummary['difference']);
    }

    public function test_a_user_can_create_a_cash_register_from_the_cash_module_choosing_a_printer(): void
    {
        [$company, $manager] = $this->companyWithCustomCashPermissions(['cash.open', 'cash.close', 'cash.view_difference', 'settings.manage']);
        $this->assignCompanyPlan($company, 'pro');

        $this->actingAs($manager);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->call('openRulesModal')
            ->set('newRegisterName', 'Caja 2')
            ->set('newRegisterPrinterType', 'thermal_80mm')
            ->call('addCashRegister')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_registers', [
            'company_id' => $company->id,
            'name' => 'Caja 2',
            'printer_type' => 'thermal_80mm',
        ]);
    }

    public function test_closing_a_register_opened_today_keeps_it_visible_in_todays_cell(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $mainRegister = $company->cashRegisters()->firstOrFail();
        $secondRegister = app(CreateCashRegister::class)->handle($company, [
            'branch_id' => $branch->id,
            'name' => 'Caja 2',
            'code' => 'CAJA-2',
            'printer_type' => 'thermal_58mm',
        ], $cashier);

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Caja 2 quedo abierta desde ayer, sin cerrar.
        $carriedOverSession = app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $secondRegister->id,
            'opened_by' => $cashier->id,
            'opening_amount' => '15000',
        ]);
        $carriedOverSession->update(['opened_at' => now()->subDay()]);

        // Caja Principal se abre y se cierra hoy mismo.
        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $mainRegister->id)
            ->set('openingAmount', '45000')
            ->call('openSession')
            ->assertHasNoErrors();
        $mainSession = $company->cashSessions()->where('cash_register_id', $mainRegister->id)->firstOrFail();
        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $mainSession->id)
            ->set('closingCountedAmount', '45000')
            ->call('closeSession')
            ->assertHasNoErrors();

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);

        $this->assertTrue($todayCell['hasSessions']);
        $registers = collect($todayCell['registers'])->keyBy('name');
        $this->assertSame('reconciled', $registers['Caja Principal']['status']);
        $this->assertSame('open', $registers['Caja 2']['status']);
    }

    protected function companyWithTemplateUser(string $templateCode): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Caja UI SAS',
        ]);
        $user = User::factory()->create();

        $company->users()->attach($user->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, $templateCode)->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        return [$company, $user];
    }

    protected function companyWithCustomCashPermissions(array $permissionCodes): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Caja Permisos SAS',
        ]);
        $user = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'cash_custom_' . md5(implode('|', $permissionCodes)),
            'display_name' => 'Caja custom',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->whereIn('code', $permissionCodes)->pluck('id')->all()
        );

        $company->users()->attach($user->id, [
            'company_role' => $companyRole->code,
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        return [$company, $user];
    }
}
