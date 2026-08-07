<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
}; ?>

<div x-data="loginDebug()" x-init="init()">
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login.store') }}" x-on:submit="onSubmit">
        @csrf

        <div>
            <x-input-label for="username" value="Usuario" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" x-on:input="onUsernameInput($event)" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Contrasena" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            x-on:input="onPasswordInput($event)" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember" value="1" @checked(old('remember')) x-on:change="onRememberChange($event)">
                <span class="ms-2 text-sm text-gray-600">Recordarme</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                    Olvide mi contrasena
                </a>
            @endif

            <x-primary-button class="ms-3">
                Ingresar
            </x-primary-button>
        </div>
    </form>
</div>

<script>
    function loginDebug() {
        return {
            storageKey: 'retail_saas_login_debug_history',
            init() {
                this.flushHistory();

                this.pushLog('pagina cargada', {
                    url: window.location.href,
                    timestamp: new Date().toISOString(),
                });

                this.observeErrors();
            },
            onSubmit() {
                const username = document.getElementById('username')?.value ?? '';

                this.pushLog('submit', {
                    usernameRaw: username,
                    usernameTrimmed: username.trim(),
                    usernameNormalized: username.trim().toLowerCase(),
                    remember: document.getElementById('remember')?.checked ?? false,
                });
            },
            onUsernameInput(event) {
                this.pushLog('username input', {
                    value: event.target.value,
                    trimmed: event.target.value.trim(),
                    normalized: event.target.value.trim().toLowerCase(),
                    length: event.target.value.length,
                });
            },
            onPasswordInput(event) {
                this.pushLog('password input', {
                    length: event.target.value.length,
                });
            },
            onRememberChange(event) {
                this.pushLog('remember change', {
                    checked: event.target.checked,
                });
            },
            pushLog(label, payload) {
                const entry = {
                    label,
                    payload,
                    timestamp: new Date().toISOString(),
                };

                console.log(`[login] ${label}`, payload);

                const history = this.readHistory();
                history.push(entry);
                sessionStorage.setItem(this.storageKey, JSON.stringify(history.slice(-40)));
            },
            readHistory() {
                try {
                    return JSON.parse(sessionStorage.getItem(this.storageKey) ?? '[]');
                } catch (error) {
                    return [];
                }
            },
            flushHistory() {
                const history = this.readHistory();

                if (history.length === 0) {
                    return;
                }

                console.group('[login] historial restaurado');
                history.forEach((entry) => {
                    console.log(`[login] ${entry.label}`, entry.payload, entry.timestamp);
                });
                console.groupEnd();
            },
            observeErrors() {
                const root = this.$root;

                const emitErrors = () => {
                    const messages = Array.from(root.querySelectorAll('[class*="text-red"], [class*="text-rose"]'))
                        .map((node) => node.textContent?.trim())
                        .filter(Boolean);

                    if (messages.length > 0) {
                        this.pushLog('mensajes visibles', messages);
                    }
                };

                emitErrors();

                const observer = new MutationObserver(() => emitErrors());
                observer.observe(root, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                });
            },
        };
    }
</script>
