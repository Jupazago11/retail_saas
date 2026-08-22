<?php

use App\Livewire\Concerns\InteractsWithToast;
use App\Mail\PasswordRecoveredMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use InteractsWithToast;

    public string $login = '';

    /**
     * Genera una contraseña nueva para la cuenta encontrada (por usuario o
     * correo) y la envia al correo registrado. Las contraseñas se guardan
     * con hash de un solo sentido, asi que la contraseña anterior no se
     * puede leer ni reenviar: en su lugar se reemplaza por una nueva.
     */
    public function sendNewPassword(): void
    {
        $this->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        $identifier = Str::lower(trim($this->login));
        $throttleKey = Str::transliterate($identifier.'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('login', 'Demasiados intentos. Intenta de nuevo en '.ceil($seconds / 60).' minuto(s).');

            return;
        }

        RateLimiter::hit($throttleKey, 300);

        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if ($user) {
            $newPassword = Str::password(12);

            $user->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
                'must_change_password' => true,
            ])->save();

            try {
                Mail::to($user->email)->send(new PasswordRecoveredMail($user, $newPassword));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el correo de recuperacion de contraseña.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        $this->reset('login');

        // Mensaje generico a proposito, exista o no el usuario/correo: evita
        // que alguien pueda usar este formulario para averiguar que cuentas existen.
        session()->flash('status', 'Si el usuario o correo existe, enviamos una contraseña nueva a su correo registrado.');
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        Ingresa tu usuario o correo electronico y te enviaremos una contraseña nueva al correo registrado en tu cuenta.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendNewPassword">
        <div>
            <x-input-label for="login" value="Usuario o correo electronico" />
            <x-text-input wire:model="login" id="login" class="block mt-1 w-full" type="text" name="login" required autofocus />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Enviar contraseña nueva
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        ¿Ya la recordaste?
        <a class="font-semibold text-blue-600 hover:text-blue-700" href="{{ route('login') }}" wire:navigate>
            Iniciar sesion
        </a>
    </p>
</div>
