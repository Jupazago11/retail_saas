<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\Concerns\InteractsWithCompanyPlans;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithCompanyPlans, RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    // El login dejo de ser un componente Livewire (Volt) con un objeto
    // $form: hoy resources/views/livewire/pages/auth/login.blade.php es un
    // <form> HTML plano que hace POST a route('login.store'), atendido por
    // AuthenticatedSessionController::store() (patron Breeze clasico).
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect();
        $response->assertSessionHas('toast');
    }

    // El sidebar (livewire.layout.navigation) ya no se incluye desde ningun
    // layout — layouts/app.blade.php se rediseño a una barra superior simple
    // + el lanzador de modulos del propio dashboard (dashboard.blade.php,
    // $launcherItems). Esto se confirmo grep-eando "layout.navigation" en
    // todo resources/views: cero resultados fuera del propio componente.
    public function test_dashboard_launcher_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $company = app(\App\Actions\Companies\CreateCompany::class)->handle($user, [
            'legal_name' => 'Prueba Retail SAS',
        ]);
        $this->assignCompanyPlan($company, 'basic');

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Modulos operativos')
            ->assertSee('Catalogos');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
