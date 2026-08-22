<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RecordStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController
{
    public function store(Request $request): RedirectResponse
    {
        try {
            $credentials = $request->validate([
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string'],
                'remember' => ['nullable', 'boolean'],
            ]);

            $credentials['username'] = Str::lower(trim($credentials['username']));
            $remember = (bool) ($credentials['remember'] ?? false);
            $throttleKey = Str::transliterate($credentials['username'].'|'.$request->ip());

            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                event(new Lockout($request));

                $seconds = RateLimiter::availableIn($throttleKey);

                throw ValidationException::withMessages([
                    'username' => trans('auth.throttle', [
                        'seconds' => $seconds,
                        'minutes' => ceil($seconds / 60),
                    ]),
                ]);
            }

            if (! Auth::attempt([
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ], $remember)) {
                RateLimiter::hit($throttleKey);

                throw ValidationException::withMessages([
                    'username' => trans('auth.failed'),
                ]);
            }

            if (Auth::user()->status !== RecordStatus::Active->value) {
                Auth::logout();

                throw ValidationException::withMessages([
                    'username' => 'Tu cuenta esta inactiva. Contacta al administrador.',
                ]);
            }
        } catch (ValidationException $e) {
            // Los errores de login se avisan como notificacion (toast), no
            // debajo del input: se flashea el toast en sesion en vez de
            // usar withErrors(), que es lo que alimentaria <x-input-error>.
            return back()->withInput($request->except('password'))->with('toast', [
                'type' => 'error',
                'message' => collect($e->errors())->flatten()->unique()->implode("\n"),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        if (Auth::user()?->is_platform_admin) {
            return redirect()->route('platform.companies');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
