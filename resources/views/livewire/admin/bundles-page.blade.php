<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.bundles" />

        <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Paquete</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingMembershipId ? 'Editar paquete' : 'Nuevo paquete' }}
                        </h3>
                    </div>

                    @if ($editingMembershipId)
                        <button type="button" wire:click="resetBundleForm" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveBundle" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Plan <span class="text-rose-600">*</span></label>
                        <select wire:model="planId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Selecciona un plan</option>
                            @foreach ($availablePlans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Estado <span class="text-rose-600">*</span></label>
                            <select wire:model="status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Maximo de empresas</label>
                            <input wire:model="maxCompanies" type="number" min="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Tipo descuento</label>
                            <select wire:model="discountType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">Sin descuento</option>
                                <option value="percentage">Porcentaje</option>
                                <option value="fixed_amount">Monto fijo</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Valor descuento</label>
                            <input wire:model="discountValue" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>


                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingMembershipId ? 'Actualizar paquete' : 'Guardar paquete' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-3 lg:col-start-2">
            @forelse ($memberships as $membership)
                <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Paquete</p>
                            <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $membership->bundle?->name ?? 'Sin paquete' }}</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Propietario: {{ $membership->bundle?->owner?->name ?? '-' }}
                            </p>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-gray-50 px-4 py-3 text-right text-sm">
                                <div class="font-semibold text-gray-900">{{ $this->assignedPlanLabel($membership) }}</div>
                                <div class="text-gray-500">Plan asignado</div>
                            </div>
                            <button type="button" wire:click="startEditingMembership({{ $membership->id }})" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                                Editar
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Estado</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ $membership->bundle?->status ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Maximo de empresas</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ $membership->bundle?->max_companies ?? 0 }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Descuento</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">
                                {{ $membership->bundle?->discount_type ?? '-' }} {{ $membership->bundle ? $this->formatMoney($membership->bundle->discount_value) : '0.00' }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Empresas vinculadas</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ $membership->bundle?->companies?->count() ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="pb-3 font-medium">Empresa</th>
                                    <th class="pb-3 font-medium">Plan</th>
                                    <th class="pb-3 font-medium">Rol en el paquete</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($membership->bundle?->companies ?? [] as $bundleCompany)
                                    <tr>
                                        <td class="py-4 font-medium text-gray-900">{{ $bundleCompany->company?->legal_name ?? 'Sin empresa' }}</td>
                                        <td class="py-4 text-gray-600">{{ $bundleCompany->plan?->name ?? $this->assignedPlanLabel($bundleCompany) }}</td>
                                        <td class="py-4 text-gray-600">
                                            {{ (int) $bundleCompany->company_id === (int) $membership->company_id ? 'Empresa actual' : 'Empresa asociada' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Paquetes</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Sin membresias registradas</h3>
                    <p class="mt-3 text-sm text-gray-500">
                        La empresa activa no tiene paquetes asociados en este momento.
                    </p>
                </div>
            @endforelse
        </div>
        </div>
    </div>
</div>
