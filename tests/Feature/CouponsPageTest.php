<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Admin\CouponsPage;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionBundle;
use App\Models\User;
use App\Services\Plans\PlanCatalogBootstrapper;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class CouponsPageTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_coupons_page_can_create_and_update_coupon_with_scope_and_audit(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $owner = User::factory()->create();
        $bundleOwner = User::factory()->create(['name' => 'Bundle Coupon Owner']);
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Coupon Scope SAS',
        ]);
        // plans.code ya no es unico global (unique compuesto business_type_id
        // + code, ver docs/decisiones-tecnicas.md "Planes independientes por
        // vertical de negocio") — se ancla al vertical general explicitamente.
        $proPlan = Plan::query()
            ->where('code', 'pro')
            ->where('business_type_id', \App\Models\BusinessType::where('code', 'general')->value('id'))
            ->firstOrFail();
        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);
        $bundle = SubscriptionBundle::query()->create([
            'owner_user_id' => $bundleOwner->id,
            'name' => 'Bundle Descuento',
            'status' => RecordStatus::Active->value,
            'max_companies' => 2,
            'discount_type' => 'percentage',
            'discount_value' => '15.00',
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'DESC15',
            'name' => 'Descuento de lanzamiento',
            'discount_type' => 'percentage',
            'discount_value' => '15.00',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(7),
            'total_uses_limit' => 100,
            'per_user_limit' => 1,
            'per_company_limit' => 2,
            'status' => RecordStatus::Active->value,
        ]);

        $coupon->plans()->attach($proPlan->id);
        $coupon->bundles()->attach($bundle->id);

        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'subscription_id' => $subscription->id,
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'applied_amount' => '15000.00',
            'applied_snapshot' => [
                'code' => 'DESC15',
            ],
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(CouponsPage::class)
            ->set('code', ' SAVE20 ')
            ->set('name', 'Ahorro controlado')
            ->set('discountType', 'fixed_amount')
            ->set('discountValue', '20000')
            ->set('totalUsesLimit', '50')
            ->set('perUserLimit', '2')
            ->set('perCompanyLimit', '3')
            ->set('selectedPlanIds', [(string) $proPlan->id])
            ->set('selectedBundleIds', [(string) $bundle->id])
            ->call('saveCoupon')
            ->assertHasNoErrors()
            ->assertSee('Ahorro controlado')
            ->assertSee('Descuento de lanzamiento')
            ->assertSee('DESC15')
            ->assertSee('Pro')
            ->assertSee('Bundle Descuento')
            ->assertSee('Coupon Scope SAS')
            // Money::format() usa separador de miles con punto y sin
            // decimales (estilo COP), no el formato US con coma y centavos.
            ->assertSee('15.000');

        $createdCoupon = Coupon::query()->where('code', 'SAVE20')->firstOrFail();

        $this->assertDatabaseHas('coupons', [
            'id' => $createdCoupon->id,
            'name' => 'Ahorro controlado',
            'discount_type' => 'fixed_amount',
            'discount_value' => '20000.00',
        ]);
        $this->assertDatabaseHas('coupon_plans', [
            'coupon_id' => $createdCoupon->id,
            'plan_id' => $proPlan->id,
        ]);
        $this->assertDatabaseHas('coupon_bundles', [
            'coupon_id' => $createdCoupon->id,
            'bundle_id' => $bundle->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'coupon.created',
            'auditable_type' => Coupon::class,
            'auditable_id' => $createdCoupon->id,
        ]);

        Livewire::test(CouponsPage::class)
            ->call('startEditingCoupon', $createdCoupon->id)
            ->set('name', 'Ahorro refinado')
            ->set('discountValue', '25000')
            ->set('selectedBundleIds', [])
            ->call('saveCoupon')
            ->assertHasNoErrors()
            ->assertSee('Ahorro refinado');

        $this->assertDatabaseHas('coupons', [
            'id' => $createdCoupon->id,
            'name' => 'Ahorro refinado',
            'discount_value' => '25000.00',
        ]);
        $this->assertDatabaseMissing('coupon_bundles', [
            'coupon_id' => $createdCoupon->id,
            'bundle_id' => $bundle->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'coupon.updated',
            'auditable_type' => Coupon::class,
            'auditable_id' => $createdCoupon->id,
        ]);
    }

    public function test_coupons_page_route_is_forbidden_without_settings_manage_permission(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Coupon Restriction SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');
        $viewer = User::factory()->create();

        $company->users()->attach($viewer->id, [
            'company_role' => 'custom',
            'company_role_id' => $this->companyRolePreset($company, 'seller')->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('admin.coupons'))
            ->assertForbidden();
    }
}
