<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.roles" />

        <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Rol</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingRoleId ? 'Editar rol' : 'Nuevo rol' }}
                        </h3>
                    </div>

                    <button wire:click="resetRoleForm" class="text-sm font-medium text-gray-500">
                        {{ $editingRoleId ? 'Cancelar' : 'Limpiar' }}
                    </button>
                </div>

                <form wire:submit="saveRole" class="mt-6 space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="role-code" class="text-sm font-medium text-gray-700">Codigo</label>
                            <input wire:model="roleCode" id="role-code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            @error('roleCode') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="role-status" class="text-sm font-medium text-gray-700">Estado</label>
                            <select wire:model="roleStatus" id="role-status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                            @error('roleStatus') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="role-display-name" class="text-sm font-medium text-gray-700">Nombre visible</label>
                        <input wire:model="displayName" id="role-display-name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('displayName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Permisos</label>
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
                        @error('selectedPermissionCodes') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingRoleId ? 'Actualizar rol' : 'Guardar rol' }}
                    </button>
                </form>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Roles creados</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Catalogo interno</h3>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($companyRoles as $role)
                        <div class="rounded-lg bg-gray-50 px-4 py-4 ring-1 ring-gray-200">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $role->display_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $role->code }} · {{ $role->permissions->count() }} permisos · {{ $role->status }}</p>
                                </div>
                                <button wire:click="editRole({{ $role->id }})" class="rounded-full border border-gray-300 px-3 py-1 text-sm font-medium text-gray-700">
                                    Editar
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Aun no hay roles personalizados para esta empresa.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Usuarios</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Asignaciones operativas</h3>
                </div>
                <div class="mt-2 text-right">
                    <p class="text-sm text-gray-500">
                        {{ $activeUsersCount }}{{ $maxUsers !== null ? ' de '.$maxUsers : '' }} usuarios activos
                    </p>
                    @if ($remainingUserSlots !== null)
                        <p class="text-xs {{ $remainingUserSlots > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            @if ($remainingUserSlots > 0)
                                Puedes crear {{ $remainingUserSlots }} {{ \Illuminate\Support\Str::plural('usuario', $remainingUserSlots) }} mas
                            @else
                                Alcanzaste el limite de usuarios de tu plan
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            @if ($maxUsers !== null && $activeUsersCount > $maxUsers)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Tienes {{ $activeUsersCount }} usuarios activos pero tu plan actual solo permite {{ $maxUsers }}.
                    Desactiva a los usuarios que no necesites operando ahora mismo; no podras activar a nadie mas hasta quedar dentro del limite.
                </div>
            @endif

            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5">
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 text-sm">
                    <button type="button" wire:click="setNewUserMode('new')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $newUserMode === 'new' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Crear usuario nuevo
                    </button>
                    <button type="button" wire:click="setNewUserMode('existing')"
                        class="rounded-md px-3 py-1.5 font-semibold transition {{ $newUserMode === 'existing' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        Vincular usuario existente
                    </button>
                </div>

                @if ($newUserMode === 'new')
                    <p class="mt-3 text-sm text-gray-500">Crea un usuario interno de operacion (cajero, vendedor, etc.). No requiere correo, solo un nombre de usuario y una contrasena inicial.</p>

                    <form wire:submit="createInternalUser" class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Nombre</label>
                            <input wire:model="newInternalName" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="Ej: Laura Gomez">
                            @error('newInternalName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Username</label>
                            <input wire:model="newInternalUsername" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="vendedor01">
                            @error('newInternalUsername') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Contrasena inicial</label>
                            <input wire:model="newInternalPassword" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="Minimo 8 caracteres">
                            @error('newInternalPassword') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Rol</label>
                            <select wire:model="newInternalCompanyRoleId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">Selecciona un rol...</option>
                                @foreach ($companyRoles as $role)
                                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                            @error('newInternalCompanyRoleId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                                Crear usuario
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 text-sm text-gray-500">Busca por correo o `username` y asigna un rol de esta empresa al entrar.</p>

                    <form wire:submit="addUserToCompany" class="mt-4 grid gap-4 xl:grid-cols-[1.4fr_1fr_auto]">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Correo o username</label>
                            <input wire:model="newUserIdentifier" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600" placeholder="usuario@empresa.com o vendedor01">
                            @error('newUserIdentifier') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Rol</label>
                            <select wire:model="newUserCompanyRoleId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">Selecciona un rol...</option>
                                @foreach ($companyRoles as $role)
                                    <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                @endforeach
                            </select>
                            @error('newUserCompanyRoleId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                                Vincular
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="mt-6 space-y-4">
                @foreach ($users as $user)
                    <div wire:key="role-user-{{ $user->id }}" class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-lg font-black text-gray-900">{{ $user->name }}</h4>
                                    @if ($user->pivot->status === 'active')
                                        <x-status-badge color="emerald">Activo</x-status-badge>
                                    @else
                                        <x-status-badge color="stone">Inactivo</x-status-badge>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-600">{{ '@'.$user->username }}{{ $user->email ? ' · '.$user->email : ' · usuario interno (sin correo)' }}</p>
                                <p class="text-sm text-gray-500">Actual: <span class="font-medium text-gray-900">{{ $this->currentAssignmentLabel($user) }}</span></p>
                                @if (($user->pivot->company_role ?? null) !== 'owner')
                                    <button wire:click="toggleUserStatus({{ $user->id }})"
                                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                        {{ $user->pivot->status === 'active' ? 'Desactivar usuario' : 'Activar usuario' }}
                                    </button>
                                @endif
                            </div>

                            <div class="min-w-[320px] space-y-3">
                                @if (($user->pivot->company_role ?? null) === 'owner')
                                    <div class="rounded-lg bg-white p-4 ring-1 ring-gray-200 text-sm text-gray-600">
                                        El propietario conserva su asignacion base y no se modifica desde esta pantalla.
                                    </div>
                                @else
                                    <div>
                                        <label class="text-sm font-medium text-gray-700">Rol</label>
                                        <select wire:model="memberships.{{ $user->id }}.company_role_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                            <option value="">Selecciona un rol...</option>
                                            @foreach ($companyRoles as $role)
                                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @error('memberships.'.$user->id.'.company_role_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                                    <button wire:click="saveMembership({{ $user->id }})" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                                        Guardar asignacion
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        </div>
    </div>
</div>
