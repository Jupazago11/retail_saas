<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav active="admin.coupons" />

        <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Cupon</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">
                            {{ $editingCouponId ? 'Editar cupon' : 'Nuevo cupon' }}
                        </h3>
                    </div>

                    @if ($editingCouponId)
                        <button type="button" wire:click="resetCouponForm" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveCoupon" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Codigo <span class="text-rose-600">*</span></label>
                        <input wire:model="code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                        <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Tipo <span class="text-rose-600">*</span></label>
                            <select wire:model="discountType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="percentage">Porcentaje</option>
                                <option value="fixed_amount">Monto fijo</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Valor <span class="text-rose-600">*</span></label>
                            <input wire:model="discountValue" type="number" step="0.01" min="0.01" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Inicio</label>
                            <input wire:model="startsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Fin</label>
                            <input wire:model="expiresAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Limite total</label>
                            <input wire:model="totalUsesLimit" type="number" min="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Limite por usuario</label>
                            <input wire:model="perUserLimit" type="number" min="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700">Limite por empresa</label>
                            <input wire:model="perCompanyLimit" type="number" min="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Estado <span class="text-rose-600">*</span></label>
                        <select wire:model="status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>

                    <div class="space-y-3 rounded-lg border border-gray-200 p-4">
                        <p class="text-sm font-semibold text-gray-900">Planes aplicables</p>
                        <div class="space-y-2">
                            @foreach ($availablePlans as $plan)
                                <label class="flex items-center gap-3 text-sm text-gray-700">
                                    <input wire:model="selectedPlanIds" type="checkbox" value="{{ $plan->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                                    <span>{{ $plan->name }} ({{ $plan->code }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-3 rounded-lg border border-gray-200 p-4">
                        <p class="text-sm font-semibold text-gray-900">Paquetes aplicables</p>
                        <div class="space-y-2">
                            @foreach ($availableBundles as $bundle)
                                <label class="flex items-center gap-3 text-sm text-gray-700">
                                    <input wire:model="selectedBundleIds" type="checkbox" value="{{ $bundle->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                                    <span>{{ $bundle->name }} - {{ $bundle->owner?->name ?? 'Sin owner' }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @error('code') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('name') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('discountType') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('discountValue') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('startsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('expiresAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('totalUsesLimit') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('perUserLimit') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('perCompanyLimit') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingCouponId ? 'Actualizar cupon' : 'Guardar cupon' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-3 lg:col-start-2">
            @forelse ($coupons as $coupon)
                <article class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Cupon</p>
                            <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $coupon->name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ $coupon->code }}</p>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-gray-50 px-4 py-3 text-right text-sm">
                                <div class="font-semibold text-gray-900">{{ $this->statusLabel($coupon->status) }}</div>
                                <div class="text-gray-500">{{ $this->discountTypeLabel($coupon->discount_type) }} - {{ $this->formatMoney($coupon->discount_value) }}</div>
                            </div>
                            <button type="button" wire:click="startEditingCoupon({{ $coupon->id }})" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                                Editar
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Vigencia inicio</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ optional($coupon->starts_at)?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Vigencia fin</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ optional($coupon->expires_at)?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Limite total</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ $coupon->total_uses_limit ?? 'Sin limite' }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Redenciones visibles</p>
                            <p class="mt-2 text-lg font-bold text-gray-900">{{ $coupon->redemptions->count() }}</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 xl:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 p-5">
                            <p class="text-sm font-semibold text-gray-900">Planes aplicables</p>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ $coupon->plans->isNotEmpty() ? $coupon->plans->pluck('name')->implode(', ') : 'Sin planes asociados' }}
                            </p>
                            <p class="mt-4 text-sm font-semibold text-gray-900">Paquetes aplicables</p>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ $coupon->bundles->isNotEmpty() ? $coupon->bundles->pluck('name')->implode(', ') : 'Sin bundles asociados' }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-5">
                            <p class="text-sm font-semibold text-gray-900">Limites por actor</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">Por usuario</dt>
                                    <dd class="font-semibold text-gray-900">{{ $coupon->per_user_limit ?? 'Sin limite' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-gray-500">Por empresa</dt>
                                    <dd class="font-semibold text-gray-900">{{ $coupon->per_company_limit ?? 'Sin limite' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="pb-3 font-medium">Empresa</th>
                                    <th class="pb-3 font-medium">Usuario</th>
                                    <th class="pb-3 font-medium">Plan suscripcion</th>
                                    <th class="pb-3 font-medium">Monto aplicado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @forelse ($coupon->redemptions as $redemption)
                                    <tr>
                                        <td class="py-4 font-medium text-gray-900">{{ $redemption->company?->legal_name ?? 'Global / N/A' }}</td>
                                        <td class="py-4 text-gray-600">{{ $redemption->user?->name ?? '-' }}</td>
                                        <td class="py-4 text-gray-600">{{ $redemption->subscription?->plan?->name ?? '-' }}</td>
                                        <td class="py-4 text-gray-600">{{ $this->formatMoney($redemption->applied_amount) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-gray-500">Sin redenciones visibles para la empresa actual.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Cupones</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Sin cupones registrados</h3>
                    <p class="mt-3 text-sm text-gray-500">
                        Aun no existen cupones en el catalogo comercial de la plataforma.
                    </p>
                </div>
            @endforelse
        </div>
        </div>
    </div>
</div>
