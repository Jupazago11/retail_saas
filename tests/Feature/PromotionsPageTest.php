<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Promotions\PromotionsPage;
use App\Models\Category;
use App\Models\CompanyRole;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class PromotionsPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_promotions_page_can_create_product_discount_promotion(): void
    {
        [$owner, $company, $category, $productA] = $this->promotionUiFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PromotionsPage::class)
            ->assertSee('Nueva promocion')
            ->set('name', 'Promo arroz UI')
            ->set('code', 'PR-ARZ')
            ->set('status', 'active')
            ->set('promotionType', 'product_discount')
            ->set('discountType', 'percentage')
            ->set('discountValue', '10')
            ->set('priority', '20')
            ->set('targets.0.target_type', 'product')
            ->set('targets.0.target_id', (string) $productA->id)
            ->set('targets.0.min_quantity', '1')
            ->call('savePromotion')
            ->assertHasNoErrors()
            ->assertSee('Promo arroz UI');

        $this->assertDatabaseHas('promotions', [
            'company_id' => $company->id,
            'name' => 'Promo arroz UI',
            'promotion_type' => 'product_discount',
            'discount_type' => 'percentage',
            'discount_value' => '10.0000',
        ]);
        $this->assertDatabaseHas('promotion_targets', [
            'target_type' => 'product',
            'target_id' => $productA->id,
            'min_quantity' => '1.000000',
        ]);
    }

    public function test_promotions_page_can_create_combo_promotion(): void
    {
        [$owner, $company, $category, $productA, $productB] = $this->promotionUiFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PromotionsPage::class)
            ->set('promotionType', 'combo_price')
            ->set('name', 'Combo UI')
            ->set('discountValue', '4200')
            ->set('priority', '10')
            ->set('comboItems.0.product_id', (string) $productA->id)
            ->set('comboItems.0.required_quantity', '1')
            ->set('comboItems.1.product_id', (string) $productB->id)
            ->set('comboItems.1.required_quantity', '1')
            ->call('savePromotion')
            ->assertHasNoErrors()
            ->assertSee('Combo UI');

        $this->assertDatabaseHas('promotions', [
            'company_id' => $company->id,
            'name' => 'Combo UI',
            'promotion_type' => 'combo_price',
            'discount_type' => 'fixed_price',
            'discount_value' => '4200.0000',
        ]);
        $this->assertDatabaseCount('promotion_combo_items', 2);
    }

    public function test_promotions_page_can_edit_and_archive_promotion(): void
    {
        [$owner, $company, $category, $productA, $productB] = $this->promotionUiFixture();

        $promotion = $company->promotions()->create([
            'name' => 'Promo inicial',
            'code' => 'INI',
            'status' => 'active',
            'promotion_type' => 'product_discount',
            'discount_type' => 'percentage',
            'discount_value' => '10.0000',
            'priority' => 50,
        ]);
        $promotion->targets()->create([
            'target_type' => 'product',
            'target_id' => $productA->id,
            'min_quantity' => '1.000000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PromotionsPage::class)
            ->call('startEditingPromotion', $promotion->id)
            ->set('name', 'Promo editada')
            ->set('discountValue', '15')
            ->set('targets.0.target_id', (string) $productB->id)
            ->call('savePromotion')
            ->assertHasNoErrors()
            ->assertSee('Promo editada')
            ->call('archivePromotion', $promotion->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'company_id' => $company->id,
            'name' => 'Promo editada',
            'discount_value' => '15.0000',
            'status' => 'archived',
        ]);
        $this->assertDatabaseHas('promotion_targets', [
            'promotion_id' => $promotion->id,
            'target_id' => $productB->id,
        ]);
    }

    public function test_promotions_page_can_duplicate_and_filter_by_effective_state(): void
    {
        [$owner, $company, $category, $productA] = $this->promotionUiFixture();

        $promotion = $company->promotions()->create([
            'name' => 'Promo programada',
            'code' => 'SCH',
            'status' => 'active',
            'promotion_type' => 'product_discount',
            'discount_type' => 'percentage',
            'discount_value' => '10.0000',
            'priority' => 30,
            'starts_at' => now()->addDay(),
        ]);
        $promotion->targets()->create([
            'target_type' => 'product',
            'target_id' => $productA->id,
            'min_quantity' => '1.000000',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(PromotionsPage::class)
            ->set('effectiveStateFilter', 'upcoming')
            ->assertSee('Promo programada')
            ->call('duplicatePromotion', $promotion->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promotions', [
            'company_id' => $company->id,
            'name' => 'Promo programada copia',
            'status' => 'inactive',
        ]);
    }

    public function test_promotions_page_route_is_forbidden_without_manage_permission(): void
    {
        [, $company] = $this->promotionUiFixture();
        $viewer = User::factory()->create();
        $companyRole = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'promotions_view_only',
            'display_name' => 'Sin promocion manage',
            'status' => RecordStatus::Active->value,
        ]);

        $companyRole->permissions()->attach(
            Permission::query()->where('code', 'sales.view')->value('id')
        );

        $company->users()->attach($viewer->id, [
            'company_role' => 'promotions_view_only',
            'company_role_id' => $companyRole->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->withSession([CurrentCompany::SESSION_KEY => $company->id])
            ->get(route('promotions.index'))
            ->assertForbidden();
    }

    protected function promotionUiFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Promotions UI SAS',
        ]);
        $this->assignCompanyPlan($company, 'premium');
        $unit = Unit::query()->create([
            'company_id' => $company->id,
            'code' => 'UND',
            'name' => 'Unidad',
            'precision_scale' => 0,
            'status' => RecordStatus::Active->value,
        ]);
        $category = Category::query()->create([
            'company_id' => $company->id,
            'name' => 'Abarrotes',
            'code' => 'ABA',
            'status' => RecordStatus::Active->value,
        ]);
        $productA = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Arroz UI',
            'cost' => 1000,
            'price_1' => 1800,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);
        $productB = Product::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'name' => 'Frijol UI',
            'cost' => 1200,
            'price_1' => 2200,
            'tracks_inventory' => true,
            'minimum_stock' => 1,
            'status' => RecordStatus::Active->value,
        ]);

        return [$owner, $company, $category, $productA, $productB];
    }
}
