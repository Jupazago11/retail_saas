<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Enums\RecordStatus;
use App\Livewire\Dining\DiningFloorPlanPage;
use App\Models\BusinessType;
use App\Models\CashRegister;
use App\Models\CompanyRole;
use App\Models\DiningFloorPlan;
use App\Models\DiningObstacle;
use App\Models\DiningTable;
use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class DiningFloorPlanPageTest extends TestCase
{
    use InteractsWithCompanyPlans;
    use RefreshDatabase;

    public function test_owner_can_save_outline_and_table_positions(): void
    {
        [$owner, $company, $table] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [
                ['x' => 10, 'y' => 10],
                ['x' => 90, 'y' => 10],
                ['x' => 90, 'y' => 90],
                ['x' => 10, 'y' => 90],
            ], [
                ['id' => $table->id, 'x' => 25.5, 'y' => 40.25, 'shape' => 'round'],
            ])
            ->assertHasNoErrors();

        $floorPlan = DiningFloorPlan::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertCount(4, $floorPlan->outline_points);

        $table->refresh();
        $this->assertEquals(25.5, (float) $table->pos_x);
        $this->assertEquals(40.25, (float) $table->pos_y);
        $this->assertSame('round', $table->shape);
    }

    public function test_save_persists_a_rectangular_table_height_independent_of_width(): void
    {
        [$owner, $company, $table] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [
                ['id' => $table->id, 'x' => 50, 'y' => 50, 'shape' => 'square', 'size' => 16, 'height' => 6],
            ])
            ->assertHasNoErrors();

        $table->refresh();
        $this->assertEquals(16, (float) $table->size);
        $this->assertEquals(6, (float) $table->height);
    }

    public function test_save_defaults_height_to_size_when_not_sent(): void
    {
        [$owner, $company, $table] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [
                ['id' => $table->id, 'x' => 50, 'y' => 50, 'shape' => 'square', 'size' => 12],
            ])
            ->assertHasNoErrors();

        $table->refresh();
        $this->assertEquals(12, (float) $table->size);
        $this->assertEquals(12, (float) $table->height);
    }

    public function test_save_persists_obstacle_dimensions(): void
    {
        [$owner, $company, , $branch] = $this->diningFixture();
        $obstacle = DiningObstacle::query()->create([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'pos_x' => 50, 'pos_y' => 50, 'width' => 10, 'height' => 10,
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [], [], [
                ['id' => $obstacle->id, 'width' => 40, 'height' => 5],
            ])
            ->assertHasNoErrors();

        $obstacle->refresh();
        $this->assertEquals(40, (float) $obstacle->width);
        $this->assertEquals(5, (float) $obstacle->height);
    }

    public function test_save_rejects_an_outline_with_fewer_than_three_points(): void
    {
        [$owner, $company, $table] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [
                ['x' => 10, 'y' => 10],
                ['x' => 90, 'y' => 10],
            ], []);

        $this->assertSame(0, DiningFloorPlan::query()->where('company_id', $company->id)->count());
    }

    public function test_non_owner_cannot_access_the_floor_plan_editor_even_with_dining_permission(): void
    {
        [, $company] = $this->diningFixture();

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

        Livewire::test(DiningFloorPlanPage::class)->assertForbidden();
    }

    public function test_save_archives_a_table_that_is_no_longer_submitted(): void
    {
        [$owner, $company, $table] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // El cliente quito la mesa del arreglo antes de guardar (boton
        // "Quitar mesa") — el servidor la archiva en vez de dejarla activa
        // y huerfana del plano.
        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [])
            ->assertHasNoErrors();

        $table->refresh();
        $this->assertSame(RecordStatus::Inactive->value, $table->status);
    }

    public function test_save_creates_a_new_table_from_a_temporary_client_side_id(): void
    {
        [$owner, $company, $existingTable] = $this->diningFixture();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // El guardado real siempre manda el estado completo del plano: la
        // mesa que ya existia (por su id real) y la nueva (id temporal).
        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [
                ['id' => $existingTable->id, 'x' => 10, 'y' => 10, 'shape' => 'square', 'size' => 8],
                ['id' => 'new-0-123', 'name' => '2', 'capacity' => 6, 'shape' => 'round', 'size' => 10, 'x' => 33, 'y' => 66],
            ])
            ->assertHasNoErrors();

        $table = DiningTable::query()->where('company_id', $company->id)->where('name', '2')->firstOrFail();
        $this->assertSame(6, $table->capacity);
        $this->assertSame('round', $table->shape);
        $this->assertEquals(10, (float) $table->size);
        $this->assertEquals(33, (float) $table->pos_x);
        $this->assertEquals(66, (float) $table->pos_y);
    }

    public function test_save_bumps_a_new_table_name_that_collides_with_an_active_one(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Restaurante Colision SAS']);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');
        $branch = $company->branches()->firstOrFail();

        $existing = DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => '1',
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
        ]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // Se envian ambas: la mesa "1" que ya existia (por su id real) y una
        // nueva que tambien pide llamarse "1" — la nueva debe ceder el paso.
        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [
                ['id' => $existing->id, 'name' => '1', 'shape' => 'square', 'size' => 8, 'x' => 50, 'y' => 50],
                ['id' => 'new-0-1', 'name' => '1', 'shape' => 'square', 'size' => 8, 'x' => 50, 'y' => 50],
            ])
            ->assertHasNoErrors();

        $this->assertSame(2, DiningTable::query()->where('company_id', $company->id)->count());
        $this->assertTrue(DiningTable::query()->where('company_id', $company->id)->where('name', '2')->exists());
    }

    public function test_save_renumbers_remaining_tables_without_gaps_after_removing_one(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Restaurante Renumeracion SAS']);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');
        $branch = $company->branches()->firstOrFail();

        $tables = collect(range(1, 8))->mapWithKeys(fn ($n) => [$n => DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => (string) $n,
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
        ])]);

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        // El cliente quita la mesa "3" del arreglo (boton "Quitar mesa") y
        // guarda con las 7 restantes.
        $remaining = $tables->except([3])->map(fn (DiningTable $t) => [
            'id' => $t->id, 'x' => 50, 'y' => 50, 'shape' => 'square', 'size' => 8,
        ])->values()->all();

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], $remaining)
            ->assertHasNoErrors();

        $this->assertSame(RecordStatus::Inactive->value, $tables[3]->fresh()->status);

        // Las que eran 4..8 bajan un numero: 3..7. Las 1 y 2 no se tocan.
        $this->assertSame('1', $tables[1]->fresh()->name);
        $this->assertSame('2', $tables[2]->fresh()->name);
        $this->assertSame('3', $tables[4]->fresh()->name);
        $this->assertSame('4', $tables[5]->fresh()->name);
        $this->assertSame('5', $tables[6]->fresh()->name);
        $this->assertSame('6', $tables[7]->fresh()->name);
        $this->assertSame('7', $tables[8]->fresh()->name);

        $active = DiningTable::query()->where('company_id', $company->id)->where('status', RecordStatus::Active->value)->pluck('name')->sort()->values();
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7'], $active->all());

        // El siguiente numero para una mesa nueva continua desde 7, no 8.
        $this->assertSame('8', DiningTable::nextNumberFor($company->id, $branch->id));
    }

    public function test_save_persists_cash_register_size(): void
    {
        [$owner, $company] = $this->diningFixture();
        $register = CashRegister::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [], [
                ['id' => $register->id, 'placed' => true, 'size' => 12, 'x' => 20, 'y' => 30],
            ])
            ->assertHasNoErrors();

        $this->assertEquals(12, (float) $register->fresh()->size);
    }

    public function test_save_places_and_unplaces_a_cash_register_on_the_map(): void
    {
        [$owner, $company] = $this->diningFixture();
        $register = CashRegister::query()->where('company_id', $company->id)->firstOrFail();

        $this->actingAs($owner);
        session([CurrentCompany::SESSION_KEY => $company->id]);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [], [
                ['id' => $register->id, 'placed' => true, 'x' => 12.5, 'y' => 87.25],
            ])
            ->assertHasNoErrors();

        $register->refresh();
        $this->assertEquals(12.5, (float) $register->pos_x);
        $this->assertEquals(87.25, (float) $register->pos_y);

        Livewire::test(DiningFloorPlanPage::class)
            ->call('save', [], [], [
                ['id' => $register->id, 'placed' => false, 'x' => 12.5, 'y' => 87.25],
            ])
            ->assertHasNoErrors();

        $register->refresh();
        $this->assertNull($register->pos_x);
        $this->assertNull($register->pos_y);
    }

    public function test_next_number_for_reflects_active_count_and_reuses_numbers_after_archiving(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Restaurante Numeracion SAS']);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $branch = $company->branches()->firstOrFail();

        $this->assertSame('1', DiningTable::nextNumberFor($company->id, $branch->id));

        $table = DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => '1',
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
        ]);
        $this->assertSame('2', DiningTable::nextNumberFor($company->id, $branch->id));

        // El negocio no permite huecos: al archivar la unica mesa activa, el
        // numero "1" queda libre de nuevo para la siguiente (a diferencia
        // del comportamiento anterior, que lo reservaba para siempre).
        $table->update(['status' => RecordStatus::Inactive->value]);
        $this->assertSame('1', DiningTable::nextNumberFor($company->id, $branch->id));
    }

    public function test_renumber_active_tables_closes_the_gap_and_ignores_inactive_ones(): void
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, ['legal_name' => 'Restaurante Renumeracion Directa SAS']);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $branch = $company->branches()->firstOrFail();

        $one = DiningTable::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => '1', 'status' => RecordStatus::Active->value, 'occupancy_status' => 'free']);
        $two = DiningTable::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => '2', 'status' => RecordStatus::Inactive->value, 'occupancy_status' => 'free']);
        $three = DiningTable::query()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => '3', 'status' => RecordStatus::Active->value, 'occupancy_status' => 'free']);

        DiningTable::renumberActiveTables($company->id, $branch->id);

        $this->assertSame('1', $one->fresh()->name);
        $this->assertSame('2', $three->fresh()->name);
        // La inactiva no se toca aunque su numero quede "en el medio".
        $this->assertSame('2', $two->fresh()->name);
        $this->assertSame(RecordStatus::Inactive->value, $two->fresh()->status);
    }

    protected function diningFixture(): array
    {
        $owner = User::factory()->create();
        $company = app(CreateCompany::class)->handle($owner, [
            'legal_name' => 'Restaurante Plano SAS',
        ]);
        $company->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);
        $this->assignCompanyPlan($company, 'basic');

        $branch = $company->branches()->firstOrFail();

        $table = DiningTable::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => RecordStatus::Active->value,
            'occupancy_status' => 'free',
        ]);

        return [$owner, $company, $table, $branch];
    }
}
