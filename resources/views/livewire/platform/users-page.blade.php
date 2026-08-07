<div>
    <div class="space-y-6">

        {{-- Header --}}
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-stone-900">Usuarios</h1>
        </div>

        {{-- Buscador --}}
        <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200">
            <div class="flex items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Buscar por nombre, email o usuario..."
                    class="w-72 rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                <span class="text-xs text-stone-400">{{ $users->total() }} usuarios</span>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                            <th class="pb-2">Usuario</th>
                            <th class="pb-2">Email</th>
                            <th class="pb-2 w-px whitespace-nowrap">Empresas</th>
                            <th class="pb-2 w-px whitespace-nowrap">Registro</th>
                            <th class="pb-2 w-px whitespace-nowrap">Rol plataforma</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-stone-900">{{ $user->name }}</p>
                                    <p class="text-xs text-stone-400">@{{ $user->username }}</p>
                                </td>
                                <td class="py-3 align-middle text-xs text-stone-600">{{ $user->email ?? '—' }}</td>
                                <td class="py-3 align-middle text-center text-xs text-stone-600 w-px whitespace-nowrap">
                                    {{ $user->companies_count }}
                                </td>
                                <td class="py-3 align-middle text-xs text-stone-400 w-px whitespace-nowrap">
                                    {{ $user->created_at->format('d/m/Y') }}
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    @if ($user->is_platform_admin)
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">superadmin</span>
                                    @else
                                        <span class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-semibold text-stone-400">usuario</span>
                                    @endif
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap text-right">
                                    <button wire:click="startResetPassword({{ $user->id }})"
                                        class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 transition hover:border-amber-400 hover:text-amber-700">
                                        Restablecer contraseña
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-xs text-stone-400">Sin usuarios.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="mt-4">{{ $users->links() }}</div>
            @endif
        </div>

    </div>

    {{-- Modal restablecer contraseña --}}
    @if ($showResetModal)
        <div class="fixed inset-0 z-50 bg-black/40"></div>

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:click="closeResetModal">
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl ring-1 ring-stone-200" wire:click.stop>

                @if (! $passwordSaved)
                    <h3 class="text-lg font-black text-stone-900">Restablecer contraseña</h3>
                    <p class="mt-1 text-xs text-stone-400">
                        Nueva contraseña para <span class="font-semibold text-stone-600">{{ $resettingUserName }}</span>.
                        Se reemplaza la anterior de inmediato; no hay forma de recuperarla despues.
                    </p>

                    <div class="mt-5">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Contraseña nueva</label>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="relative flex-1">
                                <input wire:model="newPassword" type="{{ $showPasswordText ? 'text' : 'password' }}"
                                    class="w-full rounded-2xl border-stone-300 pr-10 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <button type="button" wire:click="togglePasswordVisibility"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-stone-400 hover:text-stone-600"
                                    title="{{ $showPasswordText ? 'Ocultar' : 'Mostrar' }}">
                                    @if ($showPasswordText)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    @endif
                                </button>
                            </div>
                            <button type="button" wire:click="regeneratePassword"
                                class="rounded-full border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-600 transition hover:border-amber-400 hover:text-amber-700">
                                Generar otra
                            </button>
                        </div>
                        @error('newPassword') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button wire:click="closeResetModal"
                            class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                            Cancelar
                        </button>
                        <button wire:click="confirmResetPassword"
                            class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700">
                            Restablecer
                        </button>
                    </div>
                @else
                    <h3 class="text-lg font-black text-stone-900">Contraseña actualizada</h3>
                    <p class="mt-1 text-xs text-stone-400">
                        Copiala y enviasela a <span class="font-semibold text-stone-600">{{ $resettingUserName }}</span> ahora.
                        No se va a volver a mostrar.
                    </p>

                    <div class="mt-5 flex items-center justify-between rounded-2xl bg-stone-50 px-4 py-3 ring-1 ring-stone-200" x-data>
                        <code class="text-sm font-semibold tracking-wide text-stone-900" x-ref="passwordText">{{ $newPassword }}</code>
                        <button type="button"
                            x-on:click="navigator.clipboard.writeText($refs.passwordText.textContent.trim())"
                            class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 transition hover:border-amber-400 hover:text-amber-700">
                            Copiar
                        </button>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button wire:click="closeResetModal"
                            class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700">
                            Cerrar
                        </button>
                    </div>
                @endif

            </div>
        </div>
    @endif
</div>
