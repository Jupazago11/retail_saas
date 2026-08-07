<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Cupon</p>
                        <h3 class="mt-2 text-2xl font-black text-stone-900">
                            {{ $editingCouponId ? 'Editar cupon' : 'Nuevo cupon' }}
                        </h3>
                    </div>

                    @if ($editingCouponId)
                        <button type="button" wire:click="resetCouponForm" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                            Cancelar
                        </button>
                    @endif
                </div>

                <form wire:submit="saveCoupon" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-stone-700">Codigo</label>
                        <input wire:model="code" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-stone-700">Nombre</label>
                        <input wire:model="name" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-stone-700">Tipo</label>
                            <select wire:model="discountType" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="percentage">Porcentaje</option>
                                <option value="fixed_amount">Monto fijo</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-stone-700">Valor</label>
                            <input wire:model="discountValue" type="number" step="0.01" min="0.01" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-stone-700">Inicio</label>
                            <input wire:model="startsAt" type="datetime-local" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-stone-700">Fin</label>
                            <input wire:model="expiresAt" type="datetime-local" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-stone-700">Limite total</label>
                            <input wire:model="totalUsesLimit" type="number" min="1" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-stone-700">Limite por usuario</label>
                            <input wire:model="perUserLimit" type="number" min="1" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-stone-700">Limite por empresa</label>
                            <input wire:model="perCompanyLimit" type="number" min="1" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-stone-700">Estado</label>
                        <select wire:model="status" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-stone-200 p-4">
                        <p class="text-sm font-semibold text-stone-900">Planes aplicables</p>
                        <div class="space-y-2">
                            @foreach ($availablePlans as $plan)
                                <label class="flex items-center gap-3 text-sm text-stone-700">
                                    <input wire:model="selectedPlanIds" type="checkbox" value="{{ $plan->id }}" class="rounded border-stone-300 text-amber-600 focus:ring-amber-500">
                                    <span>{{ $plan->name }} ({{ $plan->code }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-3 rounded-2xl border border-stone-200 p-4">
                        <p class="text-sm font-semibold text-stone-900">Bundles aplicables</p>
                        <div class="space-y-2">
                            @foreach ($availableBundles as $bundle)
                                <label class="flex items-center gap-3 text-sm text-stone-700">
                                    <input wire:model="selectedBundleIds" type="checkbox" value="{{ $bundle->id }}" class="rounded border-stone-300 text-amber-600 focus:ring-amber-500">
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

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                        {{ $editingCouponId ? 'Actualizar cupon' : 'Guardar cupon' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-3 lg:col-start-2">
            @forelse ($coupons as $coupon)
                <article class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Cupon</p>
                            <h3 class="mt-2 text-2xl font-black text-stone-900">{{ $coupon->name }}</h3>
                            <p class="mt-1 text-sm text-stone-500">{{ $coupon->code }}</p>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="rounded-2xl bg-stone-50 px-4 py-3 text-right text-sm">
                                <div class="font-semibold text-stone-900">{{ $this->statusLabel($coupon->status) }}</div>
                                <div class="text-stone-500">{{ $this->discountTypeLabel($coupon->discount_type) }} - {{ $this->formatMoney($coupon->discount_value) }}</div>
                            </div>
                            <button type="button" wire:click="startEditingCoupon({{ $coupon->id }})" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700">
                                Editar
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Vigencia inicio</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ optional($coupon->starts_at)?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Vigencia fin</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ optional($coupon->expires_at)?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Limite total</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ $coupon->total_uses_limit ?? 'Sin limite' }}</p>
                        </div>
                        <div class="rounded-2xl border border-stone-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Redenciones visibles</p>
                            <p class="mt-2 text-lg font-bold text-stone-900">{{ $coupon->redemptions->count() }}</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 xl:grid-cols-2">
                        <div class="rounded-2xl border border-stone-200 p-5">
                            <p class="text-sm font-semibold text-stone-900">Planes aplicables</p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ $coupon->plans->isNotEmpty() ? $coupon->plans->pluck('name')->implode(', ') : 'Sin planes asociados' }}
                            </p>
                            <p class="mt-4 text-sm font-semibold text-stone-900">Bundles aplicables</p>
                            <p class="mt-2 text-sm text-stone-600">
                                {{ $coupon->bundles->isNotEmpty() ? $coupon->bundles->pluck('name')->implode(', ') : 'Sin bundles asociados' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-stone-200 p-5">
                            <p class="text-sm font-semibold text-stone-900">Limites por actor</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-stone-500">Por usuario</dt>
                                    <dd class="font-semibold text-stone-900">{{ $coupon->per_user_limit ?? 'Sin limite' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-stone-500">Por empresa</dt>
                                    <dd class="font-semibold text-stone-900">{{ $coupon->per_company_limit ?? 'Sin limite' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead>
                                <tr class="text-left text-stone-500">
                                    <th class="pb-3 font-medium">Empresa</th>
                                    <th class="pb-3 font-medium">Usuario</th>
                                    <th class="pb-3 font-medium">Plan suscripcion</th>
                                    <th class="pb-3 font-medium">Monto aplicado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @forelse ($coupon->redemptions as $redemption)
                                    <tr>
                                        <td class="py-4 font-medium text-stone-900">{{ $redemption->company?->legal_name ?? 'Global / N/A' }}</td>
                                        <td class="py-4 text-stone-600">{{ $redemption->user?->name ?? '-' }}</td>
                                        <td class="py-4 text-stone-600">{{ $redemption->subscription?->plan?->name ?? '-' }}</td>
                                        <td class="py-4 text-stone-600">{{ $this->formatMoney($redemption->applied_amount) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-stone-500">Sin redenciones visibles para la empresa actual.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl bg-white p-10 text-center shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Cupones</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Sin cupones registrados</h3>
                    <p class="mt-3 text-sm text-stone-500">
                        Aun no existen cupones en el catalogo comercial de la plataforma.
                    </p>
                </div>
            @endforelse
        </div>
        </div>
    </div>
</div>
