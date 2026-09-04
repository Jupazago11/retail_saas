<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.roles" />

        {{-- Roles --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Roles</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">
                        Catalogo interno
                        <span class="ml-2 text-sm font-normal text-gray-400">{{ $companyRoles->count() }} registrados</span>
                    </h3>
                </div>
                <button wire:click="openModal('role')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white transition hover:bg-blue-700"
                    title="Nuevo rol">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="pb-2">Nombre</th>
                            <th class="pb-2">Permisos</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($companyRoles as $role)
                            <tr wire:key="role-{{ $role->id }}" class="even:bg-gray-50">
                                <td class="py-3 font-medium text-gray-900">{{ $role->display_name }}</td>
                                <td class="py-3 text-xs text-gray-500">{{ $role->permissions->count() }} permisos</td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    <x-status-toggle :active="$role->status === 'active'" action="toggleRoleStatus({{ $role->id }})" />
                                </td>
                                <td class="py-3 w-px whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <button wire:click="editRole({{ $role->id }})"
                                            class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                            Editar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-xs text-gray-400">Aun no hay roles personalizados para esta empresa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Usuarios --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Usuarios</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">
                        Asignaciones operativas
                        <span class="ml-2 text-sm font-normal text-gray-400">
                            {{ $activeUsersCount }}{{ $maxUsers !== null ? ' de '.$maxUsers : '' }} activos
                        </span>
                    </h3>
                    @if ($remainingUserSlots !== null)
                        <p class="mt-1 text-xs {{ $remainingUserSlots > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            @if ($remainingUserSlots > 0)
                                Puedes crear {{ $remainingUserSlots }} {{ \Illuminate\Support\Str::plural('usuario', $remainingUserSlots) }} mas
                            @else
                                Alcanzaste el limite de usuarios de tu plan
                            @endif
                        </p>
                    @endif
                </div>
                <button wire:click="openModal('user')"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white transition hover:bg-blue-700"
                    title="Agregar usuario">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>

            @if ($maxUsers !== null && $activeUsersCount > $maxUsers)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Tienes {{ $activeUsersCount }} usuarios activos pero tu plan actual solo permite {{ $maxUsers }}.
                    Desactiva a los usuarios que no necesites operando ahora mismo; no podras activar a nadie mas hasta quedar dentro del limite.
                </div>
            @endif

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="pb-2">Usuario</th>
                            <th class="pb-2">Rol</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($users as $user)
                            <tr wire:key="role-user-{{ $user->id }}" class="even:bg-gray-50">
                                <td class="py-3">
                                    <p class="font-medium text-gray-900">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ '@'.$user->username }}{{ $user->email ? ' · '.$user->email : ' · usuario interno (sin correo)' }}</p>
                                </td>
                                <td class="py-3">
                                    @if (($user->pivot->company_role ?? null) === 'owner')
                                        <span class="text-xs text-gray-500">Propietario (asignacion base, no se modifica aqui)</span>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <select wire:model="memberships.{{ $user->id }}.company_role_id"
                                                class="block w-48 rounded-lg border-gray-300 text-xs shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                                <option value="">Selecciona un rol...</option>
                                                @foreach ($companyRoles as $role)
                                                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                                @endforeach
                                            </select>
                                            <button wire:click="saveMembership({{ $user->id }})"
                                                class="rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                                                Guardar
                                            </button>
                                        </div>
                                        @error("memberships.{$user->id}.company_role_id")
                                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </td>
                                <td class="py-3 w-px whitespace-nowrap text-right">
                                    @if (($user->pivot->company_role ?? null) === 'owner')
                                        <x-status-badge color="emerald">Activo</x-status-badge>
                                    @else
                                        <x-status-toggle :active="$user->pivot->status === 'active'" action="toggleUserStatus({{ $user->id }})" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-xs text-gray-400">Aun no hay usuarios vinculados a esta empresa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal nuevo/editar rol --}}
    @if ($activeModal === 'role')
        <div wire:click.self="closeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="flex w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl" style="max-height: 90vh;">
                <div class="flex flex-shrink-0 items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">
                            {{ $editingRoleId ? 'Editar' : 'Nuevo' }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Rol</h3>
                    </div>
                    <button wire:click="closeModal" class="px-1 text-xl leading-none text-gray-400 hover:text-gray-700">&times;</button>
                </div>

                <form wire:submit="saveRole" class="flex min-h-0 flex-1 flex-col">
                    <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                        <div>
                            <label for="role-display-name" class="text-sm font-medium text-gray-700">Nombre del rol <span class="text-rose-600">*</span></label>
                            <input wire:model="displayName" id="role-display-name" type="text" autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-gray-700">Permisos <span class="text-rose-600">*</span></label>
                                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">{{ count($selectedPermissionCodes) }} seleccionados</p>
                            </div>

                            @php
                                $moduleLabels = [
                                    'cash'          => 'Caja',
                                    'credit'        => 'Crédito',
                                    'inventory'     => 'Inventario',
                                    'masters'       => 'Maestras',
                                    'products'      => 'Productos',
                                    'sales'         => 'Ventas',
                                    'purchases'     => 'Compras',
                                    'suppliers'     => 'Proveedores',
                                    'payables'      => 'Cuentas por pagar',
                                    'customers'     => 'Clientes',
                                    'reports'       => 'Reportes',
                                    'settings'      => 'Configuración',
                                    'users'         => 'Usuarios',
                                    'roles'         => 'Roles',
                                    'subscriptions' => 'Suscripciones',
                                    'loyalty'       => 'Fidelización',
                                    'promotions'    => 'Promociones',
                                    'imports'       => 'Importaciones',
                                ];
                            @endphp
                            <div class="mt-3 space-y-4">
                                @foreach ($permissionGroups as $moduleCode => $permissions)
                                    @php
                                        $moduleCodes = $permissions->pluck('code')->all();
                                        $allModuleSelected = count($moduleCodes) > 0 && count(array_intersect($moduleCodes, $selectedPermissionCodes)) === count($moduleCodes);
                                    @endphp
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-600">{{ $moduleLabels[$moduleCode] ?? ucfirst($moduleCode) }}</p>
                                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-700">
                                                <input type="checkbox" wire:click="toggleModulePermissions('{{ $moduleCode }}')" @checked($allModuleSelected)
                                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                                Seleccionar todo
                                            </label>
                                        </div>
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            @foreach ($permissions as $permission)
                                                <label class="inline-flex items-start gap-2 text-sm text-gray-700">
                                                    <input wire:model="selectedPermissionCodes" type="checkbox" value="{{ $permission->code }}" class="mt-1 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-600">
                                                    <span>{{ $permission->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-shrink-0 gap-2 border-t border-stone-100 px-5 py-3">
                        <button type="button" wire:click="closeModal" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            {{ $editingRoleId ? 'Actualizar rol' : 'Guardar rol' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal agregar usuario --}}
    @if ($activeModal === 'user')
        <div wire:click.self="closeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="flex w-full max-w-xl flex-col rounded-xl bg-white shadow-xl" style="max-height: 90vh;">
                <div class="flex flex-shrink-0 items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Nuevo</p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Usuario</h3>
                    </div>
                    <button wire:click="closeModal" class="px-1 text-xl leading-none text-gray-400 hover:text-gray-700">&times;</button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <p class="text-sm text-gray-500">Crea un usuario interno de operacion (cajero, vendedor, etc.). No requiere correo, solo un nombre de usuario y una contrasena inicial.</p>

                    <form wire:submit="createInternalUser" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                            <input wire:model="newInternalName" type="text" autofocus class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="Ej: Laura Gomez">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Username <span class="text-rose-600">*</span></label>
                            <input wire:model="newInternalUsername" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="vendedor01">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Contrasena inicial <span class="text-rose-600">*</span></label>
                            <input wire:model="newInternalPassword" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="Minimo 8 caracteres">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Rol <span class="text-rose-600">*</span></label>
                            <select wire:model="newInternalCompanyRoleId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">Selecciona un rol...</option>
                                @foreach ($companyRoles as $role)
                                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" wire:click="closeModal" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" class="rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                                Crear usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
