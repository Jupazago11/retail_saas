<?php

namespace Tests\Feature;

use App\Actions\Companies\CreateCompany;
use App\Livewire\Platform\UsersPage;
use App\Models\BusinessType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformUsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_reset_a_users_password(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();
        $originalHash = $target->password;

        $this->actingAs($platformAdmin);

        Livewire::test(UsersPage::class)
            ->call('startResetPassword', $target->id)
            ->set('newPassword', 'a-brand-new-password')
            ->call('confirmResetPassword')
            ->assertHasNoErrors()
            ->assertSet('passwordSaved', true);

        $target->refresh();
        $this->assertNotSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('a-brand-new-password', $target->password));
    }

    public function test_reset_password_modal_warns_when_targeting_own_account(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin);

        Livewire::test(UsersPage::class)
            ->call('startResetPassword', $platformAdmin->id)
            ->assertSee('tu propia contraseña');
    }

    public function test_reset_password_modal_does_not_warn_for_other_users(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($platformAdmin);

        Livewire::test(UsersPage::class)
            ->call('startResetPassword', $target->id)
            ->assertDontSee('tu propia contraseña');
    }

    public function test_reset_password_requires_at_least_eight_characters(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($platformAdmin);

        Livewire::test(UsersPage::class)
            ->call('startResetPassword', $target->id)
            ->set('newPassword', 'short')
            ->call('confirmResetPassword')
            ->assertHasErrors(['newPassword']);
    }

    public function test_non_platform_admin_cannot_access_the_page(): void
    {
        $viewer = User::factory()->create(['is_platform_admin' => false]);

        $this->actingAs($viewer);

        Livewire::test(UsersPage::class)->assertStatus(403);
    }

    public function test_platform_admin_can_impersonate_a_regular_user(): void
    {
        $admin  = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(UsersPage::class)
            ->call('impersonate', $target->id)
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_cannot_impersonate_own_account(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(UsersPage::class)->call('impersonate', $admin->id);

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_cannot_impersonate_another_platform_admin(): void
    {
        $admin       = User::factory()->create(['is_platform_admin' => true]);
        $otherAdmin  = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(UsersPage::class)->call('impersonate', $otherAdmin->id);

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_cannot_impersonate_an_inactive_user(): void
    {
        $admin  = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create(['status' => 'inactive']);

        $this->actingAs($admin);

        Livewire::test(UsersPage::class)->call('impersonate', $target->id);

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_users_page_filters_by_status_business_type_and_role(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $generalOwner = User::factory()->create(['name' => 'Dueño General']);
        $generalCompany = app(CreateCompany::class)->handle($generalOwner, ['legal_name' => 'Tienda General SAS']);
        $generalCompany->update(['business_type_id' => BusinessType::where('code', 'general')->value('id')]);

        $restaurantOwner = User::factory()->create(['name' => 'Dueño Restaurante']);
        $restaurantCompany = app(CreateCompany::class)->handle($restaurantOwner, ['legal_name' => 'Restaurante Prueba SAS']);
        $restaurantCompany->update(['business_type_id' => BusinessType::where('code', 'restaurant')->value('id')]);

        $inactiveUser = User::factory()->create(['status' => 'inactive']);

        $this->actingAs($platformAdmin);

        Livewire::test(UsersPage::class)
            ->call('setStatusFilter', 'inactive')
            ->assertSee($inactiveUser->name)
            ->assertDontSee($generalOwner->name)
            ->call('setStatusFilter', 'all')
            ->call('setBusinessTypeFilter', 'restaurant')
            ->assertSee($restaurantOwner->name)
            ->assertDontSee($generalOwner->name)
            ->call('setBusinessTypeFilter', 'all')
            ->call('setRoleFilter', 'admin')
            ->assertSee($platformAdmin->name)
            ->assertDontSee($generalOwner->name);
    }
}
