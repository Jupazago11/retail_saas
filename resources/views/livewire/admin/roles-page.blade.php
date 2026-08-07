<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        <div class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Rol</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">
                            {{ $editingRoleId ? 'Editar rol' : 'Nuevo rol' }}
                        </h3>
                    </div>

                    <button wire:click="resetRoleForm" class="text-sm font-medium text-stone-500">
                        {{ $editingRoleId ? 'Cancelar' : 'Limpiar' }}
                    </button>
                </div>

                <form wire:submit="saveRole" class="mt-6 space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="role-code" class="text-sm font-medium text-stone-700">Codigo</label>
                            <input wire:model="roleCode" id="role-code" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('roleCode') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="role-status" class="text-sm font-medium text-stone-700">Estado</label>
                            <select wire:model="roleStatus" id="role-status" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                            @error('roleStatus') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="role-display-name" class="text-sm font-medium text-stone-700">Nombre visible</label>
                        <input wire:model="displayName" id="role-display-name" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('displayName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-stone-700">Permisos</label>
                            <p class="text-xs uppercase tracking-[0.18em] text-stone-500">{{ count($selectedPermissionCodes) }} seleccionados</p>
                        </div>

                        @php
                            $moduleLabels = [
                                'cash'       => 'Caja',
                                'credit'     => 'Crédito',
                                'inventory'  => 'Inventario',
                                'products'   => 'Productos',
                                'sales'      => 'Ventas',
                                'purchases'  => 'Compras',
                                'reports'    => 'Reportes',
                                'settings'   => 'Configuración',
                                'roles'      => 'Roles',
                                'loyalty'    => 'Fidelización',
                                'promotions' => 'Promociones',
                                'imports'    => 'Importaciones',
                            ];
                        @endphp
                        <div class="mt-3 space-y-4">
                            @foreach ($permissionGroups as $moduleCode => $permissions)
                                <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-600">{{ $moduleLabels[$moduleCode] ?? ucfirst($moduleCode) }}</p>
                                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                                        @foreach ($permissions as $permission)
                                            <label class="inline-flex items-start gap-2 text-sm text-stone-700">
                                                <input wire:model="selectedPermissionCodes" type="checkbox" value="{{ $permission->code }}" class="mt-1 rounded border-stone-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                                <span>{{ $permission->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('selectedPermissionCodes') <p class="mt-2 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingRoleId ? 'Actualizar rol' : 'Guardar rol' }}
                    </button>
                </form>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Roles creados</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Catalogo interno</h3>
                </div>

                <div class="mt-6 space-y-3">
                    @forelse ($companyRoles as $role)
                        <div class="rounded-2xl bg-stone-50 px-4 py-4 ring-1 ring-stone-200">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-stone-900">{{ $role->display_name }}</p>
                                    <p class="text-xs text-stone-500">{{ $role->code }} · {{ $role->permissions->count() }} permisos · {{ $role->status }}</p>
                                </div>
                                <button wire:click="editRole({{ $role->id }})" class="rounded-full border border-stone-300 px-3 py-1 text-sm font-medium text-stone-700">
                                    Editar
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">Aun no hay roles personalizados para esta empresa.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Usuarios</p>
                <h3 class="mt-2 text-2xl font-black text-stone-900">Asignaciones operativas</h3>
            </div>

            <div class="mt-6 rounded-3xl border border-stone-200 bg-stone-50 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-600">Vincular usuario existente</p>
                        <p class="mt-2 text-sm text-stone-500">Busca por correo o `username` y asigna una plantilla o rol personalizado al entrar.</p>
                    </div>
                </div>

                <form wire:submit="addUserToCompany" class="mt-5 grid gap-4 xl:grid-cols-[1.4fr_1fr_1fr_auto]">
                    <div>
                        <label class="text-sm font-medium text-stone-700">Correo o username</label>
                        <input wire:model="newUserIdentifier" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" placeholder="usuario@empresa.com o vendedor01">
                        @error('newUserIdentifier') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-stone-700">Plantilla</label>
                        <select wire:model="newUserRoleTemplateId" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Sin plantilla</option>
                            @foreach ($roleTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-stone-700">Rol personalizado</label>
                        <select wire:model="newUserCompanyRoleId" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Sin rol personalizado</option>
                            @foreach ($companyRoles as $role)
                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                        @error('newUserRoleTemplateId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        @error('newUserCompanyRoleId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                            Vincular
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 space-y-4">
                @foreach ($users as $user)
                    <div wire:key="role-user-{{ $user->id }}" class="rounded-3xl border border-stone-200 bg-stone-50 p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="space-y-2">
                                <h4 class="text-lg font-black text-stone-900">{{ $user->name }}</h4>
                                <p class="text-sm text-stone-600">{{ '@'.$user->username }} · {{ $user->email }}</p>
                                <p class="text-sm text-stone-500">Actual: <span class="font-medium text-stone-900">{{ $this->currentAssignmentLabel($user) }}</span></p>
                            </div>

                            <div class="min-w-[320px] space-y-3">
                                @if (($user->pivot->company_role ?? null) === 'owner')
                                    <div class="rounded-2xl bg-white p-4 ring-1 ring-stone-200 text-sm text-stone-600">
                                        El propietario conserva su asignacion base y no se modifica desde esta pantalla.
                                    </div>
                                @else
                                    <div>
                                        <label class="text-sm font-medium text-stone-700">Plantilla</label>
                                        <select wire:model="memberships.{{ $user->id }}.role_template_id" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                            <option value="">Sin plantilla</option>
                                            @foreach ($roleTemplates as $template)
                                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-stone-700">Rol personalizado</label>
                                        <select wire:model="memberships.{{ $user->id }}.company_role_id" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                            <option value="">Sin rol personalizado</option>
                                            @foreach ($companyRoles as $role)
                                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    @error('memberships.'.$user->id.'.role_template_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                                    @error('memberships.'.$user->id.'.company_role_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                                    <button wire:click="saveMembership({{ $user->id }})" class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white">
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
