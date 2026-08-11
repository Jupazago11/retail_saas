<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Plan efectivo</p>
                <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $snapshot['plan']?->name ?? 'Sin plan' }}</h3>
                <p class="mt-2 text-sm text-gray-500">{{ $this->sourceLabel($snapshot['source']) }}</p>

                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Codigo</dt>
                        <dd class="font-semibold text-gray-900">{{ $snapshot['plan']?->code ?? '-' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Precio base</dt>
                        <dd class="font-semibold text-gray-900">${{ $snapshot['plan'] ? $this->formatMoney($snapshot['plan']->base_price) : '0.00' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Modulos</dt>
                        <dd class="font-semibold text-gray-900">{{ count($snapshot['modules']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Features</dt>
                        <dd class="font-semibold text-gray-900">{{ count($snapshot['features']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Limites</dt>
                        <dd class="font-semibold text-gray-900">{{ count($snapshot['limits']) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Cambio manual</p>
                <h3 class="mt-2 text-2xl font-black text-gray-900">Actualizar suscripcion directa</h3>

                <form wire:submit="saveSubscription" class="mt-6 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Plan</label>
                        <select wire:model="selectedPlanId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Selecciona un plan</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} ({{ $plan->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Estado</label>
                        <select wire:model.live="subscriptionStatus" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="active">Activa</option>
                            <option value="trialing">Trial</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Inicio efectivo</label>
                        <input wire:model="startsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                    @if ($subscriptionStatus === 'trialing')
                        <div>
                            <label class="text-sm font-medium text-gray-700">Dias de trial</label>
                            <input wire:model="trialDays" type="number" min="1" max="365" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    @endif
                    @error('selectedPlanId') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('subscriptionStatus') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('startsAt') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    @error('trialDays') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        Guardar suscripcion
                    </button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2 lg:col-start-2">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Historial directo</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Suscripciones registradas</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $directSubscriptions->count() }} registros</p>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-3 font-medium">Plan</th>
                                <th class="pb-3 font-medium">Estado</th>
                                <th class="pb-3 font-medium">Inicio</th>
                                <th class="pb-3 font-medium">Fin</th>
                                <th class="pb-3 font-medium">Trial hasta</th>
                                <th class="pb-3 font-medium">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($directSubscriptions as $subscription)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900">{{ $subscription->plan?->name ?? 'Sin plan' }}</td>
                                    <td class="py-4 text-gray-600">{{ $this->statusLabel($subscription->status) }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($subscription->starts_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($subscription->ends_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ optional($subscription->trial_ends_at)?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="py-4">
                                        <div class="flex flex-wrap gap-2">
                                            @if ($subscription->status !== 'ended')
                                                <button type="button" wire:click="endSubscription({{ $subscription->id }})" class="rounded-full border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700">
                                                    Finalizar
                                                </button>
                                            @else
                                                <button type="button" wire:click="renewSubscription({{ $subscription->id }})" class="rounded-full border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                    Renovar
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-gray-500">No hay suscripciones directas registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Bundles</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Asignaciones por bundle</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $bundleMemberships->count() }} registros</p>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-3 font-medium">Bundle</th>
                                <th class="pb-3 font-medium">Plan asignado</th>
                                <th class="pb-3 font-medium">Owner</th>
                                <th class="pb-3 font-medium">Estado bundle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($bundleMemberships as $membership)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900">{{ $membership->bundle?->name ?? 'Sin bundle' }}</td>
                                    <td class="py-4 text-gray-600">{{ $membership->plan?->name ?? $membership->bundle?->subscriptions->first()?->plan?->name ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ $membership->bundle?->owner?->name ?? '-' }}</td>
                                    <td class="py-4 text-gray-600">{{ $membership->bundle?->status ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-gray-500">No hay bundles asociados a esta empresa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Catalogo</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Planes disponibles</h3>
                    </div>
                    <p class="text-sm text-gray-500">{{ $plans->count() }} planes</p>
                </div>

                <div class="mt-6 grid gap-4 xl:grid-cols-3">
                    @foreach ($plans as $plan)
                        <article class="rounded-xl border border-gray-200 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-lg font-black text-gray-900">{{ $plan->name }}</h4>
                                    <p class="text-sm text-gray-500">{{ $plan->code }} - {{ $plan->billing_period }}</p>
                                </div>
                                <x-status-badge color="amber">
                                    ${{ $this->formatMoney($plan->base_price) }}
                                </x-status-badge>
                            </div>

                            <div class="mt-4 space-y-3 text-sm">
                                <div>
                                    <p class="font-semibold text-gray-900">Modulos</p>
                                    <p class="mt-1 text-gray-600">{{ $plan->modules->pluck('name')->implode(', ') }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Features</p>
                                    <p class="mt-1 text-gray-600">{{ $plan->features->pluck('name')->implode(', ') }}</p>
                                </div>
                                @php
                                    $limitLabels = [
                                        'max_users' => 'Usuarios',
                                        'max_companies' => 'Empresas',
                                        'max_branches' => 'Sucursales',
                                        'max_warehouses' => 'Bodegas',
                                        'max_cash_registers' => 'Cajas',
                                        'max_products' => 'Productos',
                                        'max_monthly_sales' => 'Ventas/mes',
                                        'max_electronic_documents' => 'Docs. electrónicos',
                                    ];
                                @endphp
                                <div>
                                    <p class="font-semibold text-gray-900">Limites</p>
                                    <ul class="mt-1 space-y-1 text-gray-600">
                                        @foreach ($plan->limits as $limit)
                                            <li>{{ $limitLabels[$limit->limit_key] ?? $limit->limit_key }}: {{ number_format((int) $limit->limit_value) }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
