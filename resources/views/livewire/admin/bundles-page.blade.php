<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Bundle</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">
                            {{ $editingMembershipId ? 'Editar bundle' : 'Nuevo bundle' }}
                        </h3>
                    </div>

                    @if ($editingMembershipId)
                        <button type="button" wire:click="resetBundleForm" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveBundle" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-stone-700">Nombre</label>
                        <input wire:model="name" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-stone-700">Plan</label>
                        <select wire:model="planId" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Selecciona un plan</option>
                            @foreach ($availablePlans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-stone-700">Estado</label>
                            <select wire:model="status" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-stone-700">Max empresas</label>
                            <input wire:model="maxCompanies" type="number" min="1" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-stone-700">Tipo descuento</label>
                            <select wire:model="discountType" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Sin descuento</option>
                                <option value="percentage">Porcentaje</option>
                                <option value="fixed_amount">Monto fijo</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-stone-700">Valor descuento</label>
                            <input wire:model="discountValue" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>

                    @error('name') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('planId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('status') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('maxCompanies') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('discountType') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('discountValue') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingMembershipId ? 'Actualizar bundle' : 'Guardar bundle' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-3 lg:col-start-2">
            @forelse ($memberships as $membership)
                <article class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Bundle</p>
                            <h3 class="mt-2 text-2xl font-black text-stone-900">{{ $membership->bundle?->name ?? 'Sin bundle' }}</h3>
                            <p class="mt-1 text-sm text-stone-500">
                                Owner: {{ $membership->bundle?->owner?->name ?? '-' }}
                            </p>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-2xl bg-stone-50 px-4 py-3 text-right text-sm">
                                <div class="font-semibold text-stone-900">{{ $this->assignedPlanLabel($membership) }}</div>
                                <div class="text-stone-500">Plan asignado</div>
                            </div>
                            <button type="button" wire:click="startEditingMembership({{ $membership->id }})" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                                Editar
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Estado</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ $membership->bundle?->status ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Max empresas</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ $membership->bundle?->max_companies ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Descuento</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">
                                {{ $membership->bundle?->discount_type ?? '-' }} {{ $membership->bundle ? $this->formatMoney($membership->bundle->discount_value) : '0.00' }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Empresas vinculadas</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ $membership->bundle?->companies?->count() ?? 0 }}</p>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead>
                                <tr class="text-left text-stone-500">
                                    <th class="pb-3 font-medium">Empresa</th>
                                    <th class="pb-3 font-medium">Plan</th>
                                    <th class="pb-3 font-medium">Rol en bundle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($membership->bundle?->companies ?? [] as $bundleCompany)
                                    <tr>
                                        <td class="py-4 font-medium text-stone-900">{{ $bundleCompany->company?->legal_name ?? 'Sin empresa' }}</td>
                                        <td class="py-4 text-stone-600">{{ $bundleCompany->plan?->name ?? $this->assignedPlanLabel($bundleCompany) }}</td>
                                        <td class="py-4 text-stone-600">
                                            {{ (int) $bundleCompany->company_id === (int) $membership->company_id ? 'Empresa actual' : 'Empresa asociada' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl bg-white p-10 text-center shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Bundles</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Sin memberships registrados</h3>
                    <p class="mt-3 text-sm text-stone-500">
                        La empresa activa no tiene bundles asociados en este momento.
                    </p>
                </div>
            @endforelse
        </div>
        </div>
    </div>
</div>
