<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Models\BusinessType;
use App\Models\CompanyRole;
use App\Models\DiningTable;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

/**
 * Crear/mover/eliminar una mesa en el editor del plano NO pasan por
 * Livewire (ver el comentario en DiningFloorPlanTablesController) — por
 * eso se prueban como endpoints HTTP normales, no via Livewire::test().
 */
class DiningFloorPlanTablesControllerTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_owner_can_create_a_table_and_gets_the_next_free_number_back(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->postJson(route('dining.floor-plan.tables.store'), [
            'branch_id' => $branch->id,
            'x' => 33.5,
            'y' => 66.25,
            'shape' => 'round',
            'size' => 10,
        ]);

        $response->assertOk();
        $created = $response->json();

        $this->assertSame('1', $created['name']);
        $this->assertSame('round', $created['shape']);
        $this->assertEquals(10.0, $created['size']);
        $this->assertEquals(10.0, $created['height']);
        $this->assertEquals(33.5, $created['x']);
        $this->assertEquals(66.25, $created['y']);

        $table = DiningTable::query()->findOrFail($created['id']);
        $this->assertSame($company->id, $table->company_id);
        $this->assertSame(RecordStatus::Active->value, $table->status);
        $this->assertSame('free', $table->occupancy_status);
    }

    public function test_it_returns_a_clean_422_for_invalid_input_instead_of_a_redirect(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // bootstrap/app.php restringe shouldRenderJsonWhen() a rutas
        // 'api/*' — sin ValidatesJsonRequests, esto redirigia 302 en vez
        // de devolver un 422 en JSON, y csrfFetch() no sabe interpretar eso.
        $this->postJson(route('dining.floor-plan.tables.store'), [
            'branch_id' => $branch->id,
            'x' => 'not-a-number',
            'y' => 50,
            'shape' => 'square',
            'size' => 8,
        ])->assertUnprocessable();
    }

    public function test_owner_can_create_a_rectangular_table_with_an_explicit_height(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->postJson(route('dining.floor-plan.tables.store'), [
            'branch_id' => $branch->id,
            'x' => 50,
            'y' => 50,
            'shape' => 'square',
            'size' => 14,
            'height' => 6,
        ]);

        $response->assertOk();
        $created = $response->json();

        $this->assertEquals(14.0, $created['size']);
        $this->assertEquals(6.0, $created['height']);

        $table = DiningTable::query()->findOrFail($created['id']);
        $this->assertEquals(14, (float) $table->size);
        $this->assertEquals(6, (float) $table->height);
    }

    public function test_owner_can_update_a_table_position(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();
        $table = DiningTable::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'name' => '1',
            'status' => RecordStatus::Active->value, 'occupancy_status' => 'free',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->patchJson(route('dining.floor-plan.tables.update-position', $table), [
            'x' => 12.5,
            'y' => 87.25,
        ]);

        $response->assertOk();
        $table->refresh();
        $this->assertEquals(12.5, (float) $table->pos_x);
        $this->assertEquals(87.25, (float) $table->pos_y);
    }

    public function test_owner_can_delete_a_table_and_gets_the_renumbered_list_back(): void
    {
        [$owner, $company, $branch] = $this->diningFixture();

        $tables = collect(range(1, 3))->mapWithKeys(fn ($n) => [$n => DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => (string) $n,
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
        ])]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $response = $this->deleteJson(route('dining.floor-plan.tables.destroy', $tables[1]));

        $response->assertOk();
        $remaining = $response->json();

        // La mesa "1" se borro; la "2" y la "3" bajan un numero de inmediato
        // (no hace falta pasar por "Guardar plano" para que se vea).
        $this->assertCount(2, $remaining);
        $this->assertSame(['1', '2'], collect($remaining)->pluck('name')->all());
        $this->assertSame(RecordStatus::Inactive->value, $tables[1]->fresh()->status);
        $this->assertSame('1', $tables[2]->fresh()->name);
        $this->assertSame('2', $tables[3]->fresh()->name);
    }

    public function test_a_non_owner_cannot_create_move_or_delete_a_table(): void
    {
        [, $company, $branch] = $this->diningFixture();
        $table = DiningTable::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'name' => '1',
            'status' => RecordStatus::Active->value, 'occupancy_status' => 'free',
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

        $this->postJson(route('dining.floor-plan.tables.store'), [
            'branch_id' => $branch->id, 'x' => 50, 'y' => 50, 'shape' => 'square', 'size' => 8,
        ])->assertForbidden();

        $this->patchJson(route('dining.floor-plan.tables.update-position', $table), [
            'x' => 10, 'y' => 10,
        ])->assertForbidden();

        $this->deleteJson(route('dining.floor-plan.tables.destroy', $table))->assertForbidden();

        $table->refresh();
        $this->assertSame(RecordStatus::Active->value, $table->status);
    }

    public function test_it_rejects_creating_a_table_on_a_branch_from_another_company(): void
    {
        [$owner, $company, ] = $this->diningFixture();
        [, , $otherBranch] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        $this->postJson(route('dining.floor-plan.tables.store'), [
            'branch_id' => $otherBranch->id, 'x' => 50, 'y' => 50, 'shape' => 'square', 'size' => 8,
        ])->assertNotFound();
    }

    protected function diningFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Restaurante Plano HTTP SAS '.uniqid(),
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');

        $branch = $company->branches()->firstOrFail();

        return [$owner, $company, $branch];
    }
}
