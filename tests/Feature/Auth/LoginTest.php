<?php

namespace Tests\Feature\Auth;

use Database\Seeders\AuthorizationCatalogSeeder;
use Database\Seeders\DemoCompaniesSeeder;
use Database\Seeders\PlanCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('Usuario')
            ->assertSee('Ingresar');
    }

    public function test_demo_user_can_login_with_username(): void
    {
        $this->seed([
            AuthorizationCatalogSeeder::class,
            PlanCatalogSeeder::class,
            DemoCompaniesSeeder::class,
        ]);

        $this->get(route('login'));

        $response = $this->post(route('login.store'), [
            '_token' => session()->token(),
            'username' => 'demo.basic',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertSame('demo.basic', auth()->user()?->username);
    }

    public function test_demo_user_can_login_with_username_even_with_spaces_and_uppercase(): void
    {
        $this->seed([
            AuthorizationCatalogSeeder::class,
            PlanCatalogSeeder::class,
            DemoCompaniesSeeder::class,
        ]);

        $this->get(route('login'));

        $response = $this->post(route('login.store'), [
            '_token' => session()->token(),
            'username' => '  Demo.Basic  ',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertSame('demo.basic', auth()->user()?->username);
    }
}
