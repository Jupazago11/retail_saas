<?php

namespace Tests\Feature;

use App\Livewire\Platform\UsersPage;
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
}
