<?php

namespace Tests\Feature;

use App\Livewire\Platform\UsersPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StopImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_return_from_impersonation(): void
    {
        $admin  = User::factory()->create(['is_platform_admin' => true]);
        $target = User::factory()->create();

        $this->actingAs($admin);
        Livewire::test(UsersPage::class)->call('impersonate', $target->id);

        $this->assertAuthenticatedAs($target);

        $response = $this->post(route('impersonate.stop'));

        $response->assertRedirect(route('platform.companies'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_stop_impersonation_requires_an_active_impersonation_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('impersonate.stop'))->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }
}
