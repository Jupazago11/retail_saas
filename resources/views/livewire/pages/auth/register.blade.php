<?php

use App\Livewire\Concerns\InteractsWithToast;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use InteractsWithToast;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $accessCode = '';

    /**
     * Codigo de invitacion fijo, solo para frenar registros no deseados.
     * A proposito no vive en base de datos ni en configuracion editable:
     * nunca se envia al navegador (se valida en el servidor), asi que no
     * es descubrible viendo el HTML/JS de la pagina.
     */
    private const ACCESS_CODE = '1998';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'lowercase', 'max:255', 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'accessCode' => ['required', 'in:'.self::ACCESS_CODE],
        ], [
            'accessCode.required' => 'El código de acceso es obligatorio.',
            'accessCode.in' => 'El código de acceso no es correcto.',
        ]);

        unset($validated['accessCode']);

        $plainPassword = $validated['password'];
        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        // El envio de correos nunca debe bloquear ni tumbar el registro:
        // si el SMTP falla, solo queda en el log. El aviso al administrador
        // se envia despues, cuando el usuario crea su empresa y ya se sabe
        // que plan le quedo asignado (ver SelectCompanyPage::createCompany).
        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user, $plainPassword));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de bienvenida al usuario nuevo.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        Auth::login($user);

        $this->redirect(route('companies.select', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" value="Nombre completo" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
        </div>

        <div class="mt-4">
            <x-input-label for="username" value="Usuario" />
            <x-text-input wire:model="username" id="username" class="block mt-1 w-full" type="text" name="username" required autocomplete="username" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" value="Correo electronico" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

        </div>

        <!-- Access code -->
        <div class="mt-4">
            <x-input-label for="accessCode" value="Código de acceso" />
            <x-text-input wire:model="accessCode" id="accessCode" class="block mt-1 w-full"
                            type="password"
                            name="accessCode"
                            required autocomplete="off" />
            <p class="mt-1 text-xs text-gray-500">Solicítalo al administrador de la plataforma.</p>
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                Ya tengo cuenta
            </a>

            <x-primary-button class="ms-4">
                Crear cuenta
            </x-primary-button>
        </div>
    </form>
</div>
