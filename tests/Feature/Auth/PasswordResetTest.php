<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordRecoveredMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertStatus(200);
    }

    public function test_a_new_password_is_emailed_when_the_username_matches(): void
    {
        Mail::fake();

        $user = User::factory()->create(['username' => 'juanperez']);
        $originalPasswordHash = $user->password;

        Volt::test('pages.auth.forgot-password')
            ->set('login', 'juanperez')
            ->call('sendNewPassword')
            ->assertHasNoErrors();

        Mail::assertSent(PasswordRecoveredMail::class, fn ($mail) => $mail->hasTo($user->email));

        $user->refresh();
        $this->assertNotSame($originalPasswordHash, $user->password);
        $this->assertTrue($user->must_change_password);
    }

    public function test_a_new_password_is_emailed_when_the_email_matches(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('login', $user->email)
            ->call('sendNewPassword')
            ->assertHasNoErrors();

        Mail::assertSent(PasswordRecoveredMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_unknown_login_shows_the_same_generic_message_without_sending_mail(): void
    {
        Mail::fake();

        Volt::test('pages.auth.forgot-password')
            ->set('login', 'esta-cuenta-no-existe')
            ->call('sendNewPassword')
            ->assertHasNoErrors();

        Mail::assertNothingSent();
    }
}
