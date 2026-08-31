<?php

namespace Tests\Feature;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Actions\Company\CreateCashRegister;
use App\Actions\Purchases\CreatePurchase;
use App\Actions\Purchases\RegisterPurchasePayment;
use App\Enums\PurchaseStatus;
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

    public function test_a_register_left_open_from_a_previous_day_does_not_show_up_today(): void
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
        // opened_at ya no cae en el dia de hoy. Una sesion es independiente
        // por dia: sigue perteneciendo a ayer, no se cuela en hoy.
        $session->update(['opened_at' => now()->subDay()]);
        $yesterday = now()->subDay()->format('Y-m-d');

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');

        // No aparece en el popover de hoy...
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);
        $this->assertFalse($todayCell['hasSessions']);

        // ...ni se puede seleccionar/abrir desde "hoy" en el historial.
        $component->set('historyDate', $today);
        $options = $component->instance()->historyCashRegisterOptions();
        $this->assertFalse($options->contains('id', $register->id));

        $component->set('historyCashRegisterId', $register->id);
        $this->assertNull($component->instance()->historySession());

        // Pero SI aparece bajo su dia real de apertura (ayer).
        $yesterdayCell = collect($component->instance()->calendarCells())->firstWhere('date', $yesterday);
        $this->assertTrue($yesterdayCell['hasSessions']);
        $this->assertSame('open', collect($yesterdayCell['registers'])->firstWhere('name', $register->name)['status']);

        $component->set('historyDate', $yesterday);
        $component->set('historyCashRegisterId', $register->id);
        $resolved = $component->instance()->historySession();
        $this->assertNotNull($resolved);
        $this->assertSame($session->id, $resolved->id);
    }

    public function test_a_register_opened_earlier_but_closed_today_stays_under_its_own_day(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Se abrio "hace dos dias" y se cierra hoy — el cuadre le sigue
        // perteneciendo al dia en que se abrio, no al dia en que se cerro.
        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '45000')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();
        $session->update(['opened_at' => now()->subDays(2)]);
        $openedDay = now()->subDays(2)->format('Y-m-d');

        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('closingCountedAmount', '45000')
            ->call('closeSession')
            ->assertHasNoErrors();

        $component = Livewire::test(CashSessionsPage::class);
        $today = now()->format('Y-m-d');

        // No aparece en el popover de hoy solo porque se cerro hoy...
        $todayCell = collect($component->instance()->calendarCells())->firstWhere('date', $today);
        $this->assertFalse($todayCell['hasSessions']);

        // ...sigue viviendo bajo el dia en que realmente se abrio.
        $openedCell = collect($component->instance()->calendarCells())->firstWhere('date', $openedDay);
        $this->assertTrue($openedCell['hasSessions']);
        $openedRegister = collect($openedCell['registers'])->firstWhere('name', $register->name);
        $this->assertNotNull($openedRegister);
        $this->assertSame('reconciled', $openedRegister['status']);

        $component->set('historyDate', $openedDay);
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

    public function test_owner_can_add_a_fund_and_an_expense_to_a_closed_cuadre_from_the_day_view(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Day View Edit SAS']);
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
            ->set('closingCountedAmount', '50000')
            ->call('closeSession')
            ->assertHasNoErrors();

        // Entrar por el flujo "seleccionar el dia en el calendario" (day_view,
        // via historyDate/historyCashRegisterId), NO por el icono de lapiz del
        // popover (cuadreSessionId) — antes de este fix, addFund/addExpense
        // dependian solo de cuadreSessionId y no hacian nada aqui.
        $today = now()->format('Y-m-d');
        Livewire::test(CashSessionsPage::class)
            ->set('historyDate', $today)
            ->set('historyCashRegisterId', $register->id)
            ->set('newFundLabel', 'Refuerzo de caja')
            ->set('newFundAmount', '20000')
            ->call('addFund')
            ->assertHasNoErrors()
            ->set('newExpenseDescription', 'Gas')
            ->call('addExpense')
            ->assertHasErrors('newExpenseAmount')
            ->set('newExpenseAmount', '5000')
            ->call('addExpense')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_session_funds', [
            'cash_session_id' => $session->id,
            'label' => 'Refuerzo de caja',
            'amount' => '20000.00',
        ]);
        $this->assertDatabaseHas('cash_session_expenses', [
            'cash_session_id' => $session->id,
            'description' => 'Gas',
            'amount' => '5000.00',
        ]);
    }

    public function test_owner_can_correct_the_counted_amount_of_a_closed_cuadre_with_a_manual_amount(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Corregir Contado SAS']);
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

        // Se cierra "cuadrado" (contado == esperado): antes de este fix no
        // habia ninguna forma de arreglar un monto contado mal digitado
        // salvo "reabrir y volver a cerrar", algo que este sistema nunca
        // soporto — la unica salida era dejarlo mal para siempre.
        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('closingCountedAmount', '50000')
            ->call('closeSession')
            ->assertHasNoErrors();
        $this->assertSame('reconciled', $session->fresh()->status);

        $today = now()->format('Y-m-d');
        $component = Livewire::test(CashSessionsPage::class)
            ->set('historyDate', $today)
            ->set('historyCashRegisterId', $register->id)
            ->call('startRecountingClosedSession', $session->id)
            ->assertSet('recountingSessionId', $session->id)
            // Precargado con lo que ya habia, no vacio.
            ->assertSet('closingCountedAmount', '50000')
            ->set('closingCountedAmount', '65000')
            ->call('saveRecountedAmount')
            ->assertHasNoErrors()
            ->assertSet('recountingSessionId', null);

        $session->refresh();
        $this->assertSame('65000.00', $session->closing_counted_amount);
        // El esperado no cambio (nada se toco en bases/pagos), asi que la
        // diferencia interna se recalcula sola contra el nuevo contado.
        $this->assertSame('15000.00', $session->difference_amount);
        $this->assertSame('closed', $session->status);
        $this->assertNull($session->closing_denomination_breakdown);
    }

    public function test_owner_can_correct_the_counted_amount_using_the_denomination_grid_preloaded_from_the_original_breakdown(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Recuento Denominaciones SAS']);
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

        // Cierra contando 2 billetes de 20000 + 1 de 10000 = 50000.
        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('denominationCounts.bill_20000', '2')
            ->set('denominationCounts.bill_10000', '1')
            ->call('closeSession')
            ->assertHasNoErrors();

        // El formulario de recuento precarga las mismas cantidades que ya
        // se contaron (no arranca en blanco) — se agrega 1 billete de 10000
        // mas, como si se hubiera encontrado un billete que faltaba contar.
        $component = Livewire::test(CashSessionsPage::class)
            ->call('startRecountingClosedSession', $session->id)
            ->assertSet('denominationCounts.bill_20000', '2')
            ->assertSet('denominationCounts.bill_10000', '1')
            ->set('denominationCounts.bill_10000', '2')
            ->call('saveRecountedAmount')
            ->assertHasNoErrors();

        $session->refresh();
        $this->assertSame('60000.00', $session->closing_counted_amount);
        $this->assertNotNull($session->closing_denomination_breakdown);
        $this->assertSame('10000.00', $session->difference_amount);
    }

    public function test_a_cashier_without_settings_manage_cannot_correct_the_counted_amount_of_a_closed_cuadre(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($cashier);
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
            ->set('closingCountedAmount', '50000')
            ->call('closeSession')
            ->assertHasNoErrors();

        Livewire::test(CashSessionsPage::class)
            ->call('startRecountingClosedSession', $session->id)
            ->set('closingCountedAmount', '99000')
            ->call('saveRecountedAmount');

        $this->assertSame('50000.00', $session->fresh()->closing_counted_amount);
    }

    public function test_owner_can_convert_a_cash_purchase_payment_from_the_same_day_into_a_cash_session_expense(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Compras Caja SAS']);
        $branch = $company->branches()->firstOrFail();
        $warehouse = $company->warehouses()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '100000')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();

        $purchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Distribuidora El Ahorro',
            'total' => '30000',
            'status' => PurchaseStatus::Confirmed->value,
        ]);

        app(RegisterPurchasePayment::class)->handle($company, $purchase, [
            'amount' => '30000',
            'payment_method_code' => 'cash',
        ]);

        // "Pagos de caja" ya no alimenta ningun calculo de esperado/
        // diferencia, es solo un registro informativo — asi que una compra
        // pagada por TRANSFERENCIA tambien debe poder convertirse, no solo
        // las pagadas en efectivo.
        $transferPurchase = app(CreatePurchase::class)->handle($company, [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Proveedor Transferencia SAS',
            'total' => '15000',
            'status' => PurchaseStatus::Confirmed->value,
        ]);
        app(RegisterPurchasePayment::class)->handle($company, $transferPurchase, [
            'amount' => '15000',
            'payment_method_code' => 'transfer',
        ]);

        $component = Livewire::test(CashSessionsPage::class)
            ->call('openCuadre', $session->id);

        $candidates = $component->instance()->purchasePaymentCandidates($session->fresh());
        $this->assertCount(2, $candidates);
        $this->assertEqualsCanonicalizing(
            [$purchase->id, $transferPurchase->id],
            $candidates->pluck('purchase_id')->all(),
        );

        $component->set('selectedPurchasePaymentIds', $candidates->pluck('id')->all())
            ->call('addSelectedPurchasePayments')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_session_expenses', [
            'cash_session_id' => $session->id,
            'payable_movement_id' => $candidates->firstWhere('purchase_id', $purchase->id)->id,
            'amount' => '30000.00',
        ]);
        $this->assertDatabaseHas('cash_session_expenses', [
            'cash_session_id' => $session->id,
            'payable_movement_id' => $candidates->firstWhere('purchase_id', $transferPurchase->id)->id,
            'amount' => '15000.00',
        ]);

        // Ya convertidas: no deben volver a aparecer como candidatas.
        $this->assertCount(0, $component->instance()->purchasePaymentCandidates($session->fresh()));
    }

    public function test_daily_sales_are_broken_down_by_payment_method(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');
        $branch = $company->branches()->firstOrFail();
        $cashRegister = $company->cashRegisters()->firstOrFail();

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $cashRegister->id)
            ->set('openingAmount', '0')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->firstOrFail();

        $session->payments()->create([
            'company_id' => $company->id,
            'payment_method_code' => 'cash',
            'status' => 'confirmed',
            'amount' => '50000',
            'paid_at' => now(),
            'received_by' => $cashier->id,
        ]);
        $session->payments()->create([
            'company_id' => $company->id,
            'payment_method_code' => 'transfer',
            'status' => 'confirmed',
            'amount' => '20000',
            'paid_at' => now(),
            'received_by' => $cashier->id,
        ]);

        $component = Livewire::test(CashSessionsPage::class)->call('openCuadre', $session->id);
        $breakdown = $component->instance()->dailySalesByPaymentMethod($session->fresh());

        $this->assertSame('50000', $breakdown['cash']);
        $this->assertSame('20000', $breakdown['transfer']);
        $this->assertSame('70000', (string) round((float) $component->instance()->dailySalesAmount($session->fresh())));
    }

    public function test_owner_can_create_a_backdated_cuadre_for_a_past_day_with_no_session(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Cuadre Retroactivo SAS']);
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pastDate = now()->subDays(3)->format('Y-m-d');

        $component = Livewire::test(CashSessionsPage::class)
            ->set('historyDate', $pastDate)
            ->call('startCreateForHistoryDate')
            ->assertSet('cashStep', 'create')
            ->assertSet('creatingSessionForDate', $pastDate)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '80000')
            ->call('openSession')
            ->assertHasNoErrors()
            ->assertSet('cashStep', 'day_view')
            ->assertSet('creatingSessionForDate', '')
            ->assertSet('historyDate', $pastDate);

        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();
        $this->assertSame($pastDate, $session->opened_at->format('Y-m-d'));
        $this->assertSame('open', $session->status);
        $this->assertSame('80000.00', $session->opening_amount);

        $resolved = $component->instance()->historySession();
        $this->assertNotNull($resolved);
        $this->assertSame($session->id, $resolved->id);

        // Una fecha futura nunca debe poder "cuadrarse" retroactivamente.
        $component->set('historyDate', now()->addDays(5)->format('Y-m-d'));
        $this->assertFalse($component->instance()->canCreateForHistoryDate());
    }

    public function test_only_an_admin_can_create_a_backdated_cuadre_for_a_past_day(): void
    {
        [$company, $cashier] = $this->companyWithTemplateUser('cashier');

        $this->actingAs($cashier);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pastDate = now()->subDays(2)->format('Y-m-d');
        $component = Livewire::test(CashSessionsPage::class)->set('historyDate', $pastDate);

        $this->assertFalse($component->instance()->canCreateForHistoryDate());

        $component->call('startCreateForHistoryDate');
        $this->assertSame('', $component->get('creatingSessionForDate'));
        $this->assertNotSame('create', $component->get('cashStep'));

        // El mismo cajero SI puede crear el cuadre de HOY (es lo mismo que
        // "Crear caja" desde el menu principal, no exige permiso extra).
        $component->set('historyDate', now()->format('Y-m-d'));
        $this->assertTrue($component->instance()->canCreateForHistoryDate());
    }

    public function test_creating_a_backdated_cuadre_succeeds_even_when_todays_session_is_already_open(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Caja Unica Ocupada SAS']);
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Una sesion es independiente por dia: que la unica caja ya tenga
        // sesion abierta HOY no debe impedir crear el cuadre backdateado de
        // un dia distinto sin sesion.
        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '50000')
            ->call('openSession')
            ->assertHasNoErrors();

        $pastDate = now()->subDays(1)->format('Y-m-d');
        $component = Livewire::test(CashSessionsPage::class)->set('historyDate', $pastDate);

        $this->assertTrue($component->instance()->canCreateForHistoryDate());

        $component->call('startCreateForHistoryDate');
        $this->assertSame($pastDate, $component->get('creatingSessionForDate'));
        $this->assertSame('create', $component->get('cashStep'));
    }

    public function test_creating_a_backdated_cuadre_is_blocked_with_a_clear_reason_when_that_same_day_is_already_open(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Caja Unica Ocupada SAS']);
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $pastDate = now()->subDays(1)->format('Y-m-d');

        // La empresa siempre tiene una sucursal y una caja activa (la
        // principal no se puede desactivar), pero si esa unica caja ya
        // tiene una sesion abierta para ESE MISMO dia pasado, no hay
        // ninguna caja disponible para backdatearlo de nuevo — el mensaje
        // debe explicar eso, no decir que falta sucursal/caja activa.
        app(OpenCashSession::class)->handle($company, [
            'branch_id' => $branch->id,
            'cash_register_id' => $register->id,
            'opened_by' => $owner->id,
            'opening_amount' => '50000',
            'opened_at' => $pastDate,
        ]);

        $component = Livewire::test(CashSessionsPage::class)->set('historyDate', $pastDate);

        $this->assertTrue($component->instance()->canCreateForHistoryDate());

        $component->call('startCreateForHistoryDate');
        $this->assertSame('', $component->get('creatingSessionForDate'));
        $this->assertNotSame('create', $component->get('cashStep'));
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

    public function test_the_calendar_popover_no_longer_shows_expected_or_difference(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Sin Diferencia SAS']);
        $branch = $company->branches()->firstOrFail();
        $register = $company->cashRegisters()->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CashSessionsPage::class)
            ->set('branchId', $branch->id)
            ->set('cashRegisterId', $register->id)
            ->set('openingAmount', '400000')
            ->call('openSession')
            ->assertHasNoErrors();
        $session = $company->cashSessions()->where('cash_register_id', $register->id)->firstOrFail();

        Livewire::test(CashSessionsPage::class)
            ->call('startClosingSession', $session->id)
            ->set('closingCountedAmount', '500000')
            ->call('closeSession')
            ->assertHasNoErrors();

        // Renderiza el paso 'calendar' completo (popover del dia con
        // sesiones) para verificar en tiempo de ejecucion — no solo en
        // compilacion — que ya no se muestra Diferencia/Esperado/sobra, que
        // el badge queda neutro, y que el icono de editar sigue disponible.
        Livewire::test(CashSessionsPage::class)
            ->call('startHistory')
            ->assertSet('cashStep', 'calendar')
            ->assertSee(\App\Support\Money::format(500000.0))
            ->assertDontSee('Diferencia')
            ->assertDontSee('Esperado')
            ->assertDontSee('sobra')
            ->assertDontSee('falta')
            ->assertDontSee('Recaudo negativo')
            ->assertSeeHtml('wire:click="openCuadre(' . $session->id . ')"');
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

        // Caja 2 quedo abierta desde ayer, sin cerrar — es independiente
        // por dia, asi que no debe mezclarse con el cuadre de hoy de
        // Caja Principal ni impedir que Caja Principal opere hoy.
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
        $this->assertArrayNotHasKey('Caja 2', $registers);

        // Caja 2 sigue viva bajo su propio dia (ayer), no bajo hoy.
        $yesterday = now()->subDay()->format('Y-m-d');
        $yesterdayCell = collect($component->instance()->calendarCells())->firstWhere('date', $yesterday);
        $this->assertSame('open', collect($yesterdayCell['registers'])->firstWhere('name', 'Caja 2')['status']);
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
