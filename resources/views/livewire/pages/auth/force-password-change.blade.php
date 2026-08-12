<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'password' => [
                    'required', 'string', Password::defaults(), 'confirmed',
                    function (string $attribute, string $value, \Closure $fail): void {
                        if (Hash::check($value, Auth::user()->password)) {
                            $fail('La nueva contraseña no puede ser igual a la actual.');
                        }
                    },
                ],
            ]);
        } catch (ValidationException $e) {
            $this->reset('password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        $this->redirectRoute('dashboard', navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        Tu contraseña fue restablecida. Antes de continuar, define una contraseña nueva que solo tu conozcas.
    </div>

    <form wire:submit="updatePassword">
        <div>
            <x-input-label for="password" value="Contraseña nueva" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Guardar y continuar
            </x-primary-button>
        </div>
    </form>
</div>
