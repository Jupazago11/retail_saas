<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Actions\Companies\ProvisionDefaultRestaurantRoles;
use App\Actions\Companies\ProvisionDefaultRestaurantUsers;
use App\Enums\RecordStatus;
use App\Models\BusinessType;
use App\Models\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class ProvisionDefaultRestaurantUsersTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_it_creates_one_user_per_base_role_with_username_as_the_initial_password(): void
    {
        $company = $this->restaurantCompany();
        app(ProvisionDefaultRestaurantRoles::class)->handle($company);

        app(ProvisionDefaultRestaurantUsers::class)->handle($company);

        $roles = CompanyRole::query()->where('company_id', $company->id)->get()->keyBy('code');

        foreach (['CAJERO' => 'cajero', 'MESERO' => 'mesero', 'COCINA' => 'cocina'] as $code => $prefix) {
            $expectedUsername = "{$prefix}.{$company->id}";
            $user = User::query()->where('username', $expectedUsername)->first();

            $this->assertNotNull($user, "Se esperaba un usuario con username {$expectedUsername}");
            $this->assertTrue($user->must_change_password);
            $this->assertTrue(Hash::check($expectedUsername, $user->password));

            $membership = $company->users()->where('users.id', $user->id)->firstOrFail()->pivot;
            $this->assertSame($roles[$code]->id, $membership->company_role_id);
            $this->assertSame(RecordStatus::Active->value, $membership->status);
        }
    }

    public function test_it_is_idempotent_and_does_not_duplicate_users_on_a_second_run(): void
    {
        $company = $this->restaurantCompany();
        app(ProvisionDefaultRestaurantRoles::class)->handle($company);

        app(ProvisionDefaultRestaurantUsers::class)->handle($company);
        app(ProvisionDefaultRestaurantUsers::class)->handle($company);

        $this->assertSame(3, User::query()->where('username', 'like', '%.'.$company->id)->count());
    }

    public function test_it_skips_a_role_that_already_has_a_manually_created_user(): void
    {
        $company = $this->restaurantCompany();
        app(ProvisionDefaultRestaurantRoles::class)->handle($company);

        $cajeroRole = CompanyRole::query()->where('company_id', $company->id)->where('code', 'CAJERO')->firstOrFail();
        $manualCashier = User::factory()->create(['username' => 'cajero_manual']);
        $company->users()->attach($manualCashier->id, [
            'company_role' => 'custom',
            'company_role_id' => $cajeroRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        app(ProvisionDefaultRestaurantUsers::class)->handle($company);

        $this->assertNull(User::query()->where('username', "cajero.{$company->id}")->first());
        $this->assertNotNull(User::query()->where('username', "mesero.{$company->id}")->first());
        $this->assertNotNull(User::query()->where('username', "cocina.{$company->id}")->first());
    }

    public function test_it_skips_roles_that_no_longer_fit_within_the_plan_user_limit_without_throwing(): void
    {
        $company = $this->restaurantCompany();
        app(ProvisionDefaultRestaurantRoles::class)->handle($company);

        // restaurant/basic trae max_users = 4 (dueño + los 3 roles base).
        // El dueño ya ocupa un cupo; un usuario extra deja solo 2 libres,
        // asi que un rol se queda sin cuenta y no debe reventar la accion.
        $filler = User::factory()->create();
        $company->users()->attach($filler->id, [
            'company_role' => 'staff',
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        app(ProvisionDefaultRestaurantUsers::class)->handle($company);

        $created = User::query()->where('username', 'like', '%.'.$company->id)->count();
        $this->assertSame(2, $created);
        $this->assertNull(User::query()->where('username', "cocina.{$company->id}")->first());
    }

    public function test_it_does_not_create_a_user_for_a_deactivated_role(): void
    {
        $company = $this->restaurantCompany();
        app(ProvisionDefaultRestaurantRoles::class)->handle($company);

        CompanyRole::query()
            ->where('company_id', $company->id)
            ->where('code', 'COCINA')
            ->update(['status' => RecordStatus::Inactive->value]);

        app(ProvisionDefaultRestaurantUsers::class)->handle($company);

        $this->assertNull(User::query()->where('username', "cocina.{$company->id}")->first());
        $this->assertNotNull(User::query()->where('username', "cajero.{$company->id}")->first());
        $this->assertNotNull(User::query()->where('username', "mesero.{$company->id}")->first());
    }

    protected function restaurantCompany()
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Provision Usuarios SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');

        return $company;
    }
}
