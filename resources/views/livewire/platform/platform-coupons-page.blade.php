<div>
    {{-- Livewire needs a single root element. "space-y-*" only wraps the normal
         content below so it never adds margin-top to the modal (a sibling of
         this div, not a child of it), which used to push the "fixed inset-0"
         overlay down from the real top of the viewport. --}}
    <div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-stone-900">Cupones</h1>
        </div>
        <button wire:click="openCreate"
            class="rounded-full bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
            + Nuevo cupón
        </button>
    </div>

    {{-- Tabla --}}
    <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                        <th class="pb-2">Código</th>
                        <th class="pb-2">Nombre</th>
                        <th class="pb-2">Descuento</th>
                        <th class="pb-2">Planes</th>
                        <th class="pb-2 w-px whitespace-nowrap">Vence</th>
                        <th class="pb-2 w-px whitespace-nowrap">Usos</th>
                        <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                        <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($coupons as $coupon)
                        <tr wire:key="coupon-{{ $coupon->id }}">
                            <td class="py-3 align-middle font-mono text-xs font-bold text-stone-900">
                                {{ $coupon->code }}
                            </td>
                            <td class="py-3 align-middle text-stone-700">{{ $coupon->name }}</td>
                            <td class="py-3 align-middle text-xs text-stone-600">
                                @if ($coupon->discount_type === 'percentage')
                                    {{ $coupon->discount_value }}%
                                @else
                                    ${{ number_format($coupon->discount_value, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="py-3 align-middle">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($coupon->plans as $plan)
                                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">{{ $plan->name }}</span>
                                    @endforeach
                                    @if ($coupon->plans->isEmpty())
                                        <span class="text-xs text-stone-400">Todos</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 align-middle text-xs text-stone-400 w-px whitespace-nowrap">
                                {{ $coupon->expires_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 align-middle text-xs text-stone-600 w-px whitespace-nowrap">
                                {{ $coupon->redemptions_count ?? $coupon->redemptions->count() }}
                                @if ($coupon->total_uses_limit)
                                    / {{ $coupon->total_uses_limit }}
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                @if ($coupon->status === 'active')
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">activo</span>
                                @else
                                    <span class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-semibold text-stone-500">inactivo</span>
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="startEdit({{ $coupon->id }})"
                                        class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 transition hover:border-amber-400 hover:text-amber-700">
                                        Editar
                                    </button>
                                    <button wire:click="toggleStatus({{ $coupon->id }})"
                                        class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 transition hover:border-stone-400">
                                        {{ $coupon->status === 'active' ? 'Inactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-xs text-stone-400">Sin cupones creados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-xl ring-1 ring-stone-200">
                <h3 class="text-lg font-black text-stone-900">{{ $editingId ? 'Editar cupón' : 'Nuevo cupón' }}</h3>

                <div class="mt-5 grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Código</label>
                        <input wire:model="code" type="text" placeholder="DESC20"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 uppercase">
                        @error('code') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Nombre</label>
                        <input wire:model="name" type="text"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Tipo de descuento</label>
                        <select wire:model="discountType"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="percentage">Porcentaje (%)</option>
                            <option value="fixed_amount">Monto fijo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Valor</label>
                        <input wire:model="discountValue" type="number" min="0" step="0.01"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('discountValue') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Válido desde</label>
                        <input wire:model="startsAt" type="date"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Expira</label>
                        <input wire:model="expiresAt" type="date"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Usos totales</label>
                        <input wire:model="totalUsesLimit" type="number" min="1" placeholder="Sin límite"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Usos por empresa</label>
                        <input wire:model="perCompanyLimit" type="number" min="1" placeholder="Sin límite"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>

                    <div class="col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Aplica a planes (vacío = todos)</label>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($plans as $plan)
                                <label class="flex items-center gap-2 text-sm text-stone-700">
                                    <input type="checkbox" wire:model="selectedPlanIds" value="{{ $plan->id }}"
                                        class="rounded border-stone-300 text-amber-600 focus:ring-amber-500">
                                    {{ $plan->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Estado</label>
                        <select wire:model="status"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal"
                        class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                        Cancelar
                    </button>
                    <button wire:click="save"
                        class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700">
                        {{ $editingId ? 'Actualizar' : 'Crear cupón' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
