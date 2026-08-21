<div>
    <div class="space-y-6">

        {{-- Buscador --}}
        <div x-data="responsivePageSize({ rowHeight: 60, reserved: 300 })" class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Usuarios</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Buscar por nombre, email o usuario..."
                        class="w-72 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <span class="text-xs text-gray-400">{{ $users->total() }} usuarios</span>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="py-2 pl-0 pr-4">Usuario</th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2 w-px whitespace-nowrap text-center">Empresas</th>
                            <th class="px-4 py-2 w-px whitespace-nowrap">Registro</th>
                            <th class="px-4 py-2 w-px whitespace-nowrap">Rol plataforma</th>
                            <th class="px-4 py-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pl-4 pr-0 py-2 w-px whitespace-nowrap text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="even:bg-gray-50">
                                <td class="py-3 pl-0 pr-4 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-400">{{ '@'.$user->username }}</p>
                                </td>
                                <td class="px-4 py-3 align-middle text-xs text-gray-600">{{ $user->email ?? '—' }}</td>
                                <td class="px-4 py-3 align-middle text-center text-xs text-gray-600 w-px whitespace-nowrap">
                                    {{ $user->companies_count }}
                                </td>
                                <td class="px-4 py-3 align-middle text-xs text-gray-400 w-px whitespace-nowrap">
                                    {{ $user->created_at->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 align-middle w-px whitespace-nowrap">
                                    @if ($user->is_platform_admin)
                                        <x-status-badge color="amber">superadmin</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">usuario</x-status-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle w-px whitespace-nowrap">
                                    <button type="button" wire:click="toggleStatus({{ $user->id }})"
                                        title="Clic para {{ $user->status === 'active' ? 'desactivar' : 'activar' }}"
                                        class="transition hover:opacity-75">
                                        @if ($user->status === 'active')
                                            <x-status-badge color="emerald">Activo</x-status-badge>
                                        @else
                                            <x-status-badge color="rose">Inactivo</x-status-badge>
                                        @endif
                                    </button>
                                </td>
                                <td class="pl-4 pr-0 py-3 align-middle w-px whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="startEditUser({{ $user->id }})" title="Editar usuario"
                                            class="inline-flex items-center justify-center rounded-full border border-gray-300 p-1.5 text-gray-500 transition hover:border-blue-400 hover:text-blue-700">
                                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                                        </button>
                                        <button wire:click="startResetPassword({{ $user->id }})"
                                            class="whitespace-nowrap rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                            Restablecer contraseña
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-xs text-gray-400">Sin usuarios.</td>
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
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200" wire:click.stop>

                @if (! $passwordSaved)
                    <h3 class="text-lg font-black text-gray-900">Restablecer contraseña</h3>
                    <p class="mt-1 text-xs text-gray-400">
                        Nueva contraseña para <span class="font-semibold text-gray-600">{{ $resettingUserName }}</span>.
                        Se reemplaza la anterior de inmediato; no hay forma de recuperarla despues.
                    </p>

                    <div class="mt-5">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Contraseña nueva <span class="text-rose-600">*</span></label>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="relative flex-1">
                                <input wire:model="newPassword" type="{{ $showPasswordText ? 'text' : 'password' }}"
                                    class="w-full rounded-lg border-gray-300 pr-10 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <button type="button" wire:click="togglePasswordVisibility"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
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
                                class="rounded-full border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                Generar otra
                            </button>
                        </div>
                        @error('newPassword') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button wire:click="closeResetModal"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button wire:click="confirmResetPassword"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Restablecer
                        </button>
                    </div>
                @else
                    <h3 class="text-lg font-black text-gray-900">Contraseña actualizada</h3>
                    <p class="mt-1 text-xs text-gray-400">
                        Copiala y enviasela a <span class="font-semibold text-gray-600">{{ $resettingUserName }}</span> ahora.
                        No se va a volver a mostrar.
                    </p>

                    <div class="mt-5 flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 ring-1 ring-gray-200" x-data>
                        <code class="text-sm font-semibold tracking-wide text-gray-900" x-ref="passwordText">{{ $newPassword }}</code>
                        <button type="button"
                            x-on:click="navigator.clipboard.writeText($refs.passwordText.textContent.trim())"
                            class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                            Copiar
                        </button>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button wire:click="closeResetModal"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                            Cerrar
                        </button>
                    </div>
                @endif

            </div>
        </div>
    @endif

    {{-- Modal editar usuario --}}
    @if ($showEditModal)
        <div class="fixed inset-0 z-50 bg-black/40"></div>

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:click="closeEditModal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200" wire:click.stop>
                <h3 class="text-lg font-black text-gray-900">Editar usuario</h3>
                <p class="mt-1 text-xs text-gray-400">Actualiza nombre, usuario o correo.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="editName" type="text"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('editName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Usuario <span class="text-rose-600">*</span></label>
                        <input wire:model="editUsername" type="text"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('editUsername') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Correo <span class="text-rose-600">*</span></label>
                        <input wire:model="editEmail" type="email"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('editEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeEditModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="saveEditUser"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
