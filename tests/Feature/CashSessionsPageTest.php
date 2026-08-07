<?php

namespace Tests\Feature;

use App\Actions\Cash\OpenCashSession;
use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Cash\CashSessionsPage;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\RoleTemplate;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashSessionsPageTest extends TestCase
{
    use RefreshDatabase;

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

    protected function companyWithTemplateUser(string $templateCode): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Caja UI SAS',
        ]);
        $user = User::factory()->create();

        $company->users()->attach($user->id, [
            'company_role' => $templateCode,
            'role_template_id' => RoleTemplate::query()->where('code', $templateCode)->value('id'),
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
