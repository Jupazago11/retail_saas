<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Services\Plans\PlanCatalogBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogBootstrapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_full_catalog_on_an_empty_database(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $this->assertDatabaseHas('plans', ['code' => 'basic']);
        $this->assertDatabaseHas('modules', ['code' => 'pos']);
        $this->assertDatabaseHas('features', ['code' => 'pos.mixed_payments']);

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $this->assertTrue($basic->modules()->wherePivot('enabled', true)->where('code', 'products')->exists());
        $this->assertSame(3, PlanLimit::query()->where('plan_id', $basic->id)->where('limit_key', 'max_users')->value('limit_value'));
    }

    public function test_it_does_not_revert_manual_edits_on_a_second_run(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $posModule = Module::query()->where('code', 'pos')->firstOrFail();

        // Simulate a prior manual save from the UI: disable a module that the
        // hardcoded catalog says should be enabled, rename the plan, and edit a limit.
        $basic->modules()->updateExistingPivot($posModule->id, ['enabled' => false]);
        $basic->update(['name' => 'Basic Renombrado']);
        PlanLimit::query()->where('plan_id', $basic->id)->where('limit_key', 'max_users')->update(['limit_value' => 999]);

        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $basic->refresh();
        $this->assertSame('Basic Renombrado', $basic->name);
        $this->assertFalse((bool) $basic->modules()->where('code', 'pos')->first()?->pivot->enabled);
        $this->assertSame(999, PlanLimit::query()->where('plan_id', $basic->id)->where('limit_key', 'max_users')->value('limit_value'));
    }

    public function test_it_still_seeds_a_missing_pivot_row_without_touching_an_edited_one(): void
    {
        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $basic = Plan::query()->where('code', 'basic')->firstOrFail();
        $posModule = Module::query()->where('code', 'pos')->firstOrFail();
        $cashModule = Module::query()->where('code', 'cash')->firstOrFail();

        $basic->modules()->updateExistingPivot($posModule->id, ['enabled' => false]);
        // Simulate a catalog entry the plan never got a row for yet (e.g. added after go-live).
        $basic->modules()->detach($cashModule->id);

        app(PlanCatalogBootstrapper::class)->ensureDefaults();

        $this->assertFalse((bool) $basic->modules()->where('code', 'pos')->first()?->pivot->enabled);
        $this->assertTrue($basic->modules()->wherePivot('enabled', true)->where('code', 'cash')->exists());
    }
}
