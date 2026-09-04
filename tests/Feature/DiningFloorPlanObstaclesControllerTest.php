<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Models\BusinessType;
use App\Models\CompanyRole;
use App\Models\DiningObstacle;
use App\Models\Permission;
use App\Models\User;
use App\Services\Settings\CompanySettings;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

/**
 * Igual que DiningFloorPlanTablesControllerTest: crear/mover/eliminar un
 * obstaculo NO pasa por Livewire, se prueba como endpoint HTTP normal.
 */
class DiningFloorPlanObstaclesControllerTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_owner_can_create_an_obstacle(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->postJson(route('dining.floor-plan.obstacles.store'), [
            'branch_id' => $branch->id,
            'x' => 33.5,
            'y' => 66.25,
        ]);

        $response->assertOk();
        $created = $response->json();

        $this->assertEquals(33.5, $created['x']);
        $this->assertEquals(66.25, $created['y']);
        $this->assertEquals(10.0, $created['width']);
        $this->assertEquals(10.0, $created['height']);

        $obstacle = DiningObstacle::query()->findOrFail($created['id']);
        $this->assertSame($company->id, $obstacle->company_id);
        $this->assertSame($branch->id, $obstacle->branch_id);
    }

    public function test_owner_can_move_an_obstacle(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();
        $obstacle = DiningObstacle::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'pos_x' => 50, 'pos_y' => 50, 'width' => 10, 'height' => 10,
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->patchJson(route('dining.floor-plan.obstacles.update-position', $obstacle), [
            'x' => 12.5, 'y' => 87.25,
        ]);

        $response->assertOk();
        $obstacle->refresh();
        $this->assertEquals(12.5, (float) $obstacle->pos_x);
        $this->assertEquals(87.25, (float) $obstacle->pos_y);
    }

    public function test_owner_can_delete_an_obstacle_permanently(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();
        $obstacle = DiningObstacle::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'pos_x' => 50, 'pos_y' => 50, 'width' => 10, 'height' => 10,
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->deleteJson(route('dining.floor-plan.obstacles.destroy', $obstacle))->assertOk();

        // Borrado fisico, no archivado — a diferencia de una mesa.
        $this->assertDatabaseMissing('dining_obstacles', ['id' => $obstacle->id]);
    }

    public function test_owner_can_change_the_obstacle_color_for_the_whole_company(): void
    {
        [$owner, $company, ] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->assertSame('#404040', app(CompanySettings::class)->get($company, 'dining', 'obstacle_color'));

        $this->patchJson(route('dining.floor-plan.obstacles.update-color'), [
            'color' => '#ff0000',
        ])->assertOk();

        $this->assertSame('#ff0000', app(CompanySettings::class)->get($company, 'dining', 'obstacle_color'));
    }

    public function test_it_rejects_an_invalid_color_value(): void
    {
        [$owner, $company, ] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->patchJson(route('dining.floor-plan.obstacles.update-color'), [
            'color' => 'not-a-color',
        ])->assertUnprocessable();
    }

    public function test_a_non_owner_cannot_create_move_or_delete_an_obstacle(): void
    {
        [, $company, $branch] = $this->diningFixture();
        $obstacle = DiningObstacle::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'pos_x' => 50, 'pos_y' => 50, 'width' => 10, 'height' => 10,
        ]);

        $role = CompanyRole::query()->create([
            'company_id' => $company->id,
            'code' => 'dining_manager',
            'display_name' => 'Encargado de mesas',
            'status' => RecordStatus::Active->value,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('code', ['dining.manage', 'settings.manage'])->pluck('id')
        );

        $manager = User::factory()->create();
        $company->users()->attach($manager->id, [
            'company_role' => 'custom',
            'company_role_id' => $role->id,
            'status' => RecordStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($manager);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->postJson(route('dining.floor-plan.obstacles.store'), [
            'branch_id' => $branch->id, 'x' => 50, 'y' => 50,
        ])->assertForbidden();

        $this->patchJson(route('dining.floor-plan.obstacles.update-position', $obstacle), [
            'x' => 10, 'y' => 10,
        ])->assertForbidden();

        $this->deleteJson(route('dining.floor-plan.obstacles.destroy', $obstacle))->assertForbidden();

        $this->patchJson(route('dining.floor-plan.obstacles.update-color'), [
            'color' => '#ff0000',
        ])->assertForbidden();

        $this->assertDatabaseHas('dining_obstacles', ['id' => $obstacle->id]);
        $this->assertSame('#404040', app(CompanySettings::class)->get($company, 'dining', 'obstacle_color'));
    }

    protected function diningFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Restaurante Obstaculos SAS '.uniqid(),
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');

        $branch = $company->branches()->firstOrFail();

        return [$owner, $company, $branch];
    }
}
