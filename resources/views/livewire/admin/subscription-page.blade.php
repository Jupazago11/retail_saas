<div class="pb-10">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-admin-nav />

        {{-- Plan actual --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Tu plan actual</p>
                    <h3 class="mt-1 text-3xl font-black text-gray-900">{{ $snapshot['plan']?->name ?? 'Sin plan activo' }}</h3>
                    @if ($snapshot['plan'])
                        <p class="mt-1 text-xl font-bold text-gray-700">
                            ${{ $this->formatMoney($snapshot['plan']->base_price) }}<span class="text-sm font-normal text-gray-400">/mes</span>
                        </p>
                    @endif
                </div>

                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Renovacion</p>
                    @if ($snapshot['subscription']?->ends_at)
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ $snapshot['subscription']->ends_at->format('d/m/Y') }}</p>
                    @elseif ($snapshot['subscription']?->trial_ends_at)
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ $snapshot['subscription']->trial_ends_at->format('d/m/Y') }}</p>
                        <p class="text-xs text-amber-600">Fin del periodo de prueba</p>
                    @else
                        <p class="mt-1 text-sm text-gray-400">Sin vencimiento definido</p>
                    @endif
                </div>
            </div>

            @if ($this->whatsappUrl() !== '')
                <a href="{{ $this->whatsappUrl() }}" target="_blank" rel="noopener"
                    class="mt-5 inline-flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    <x-heroicon-o-chat-bubble-left-right class="h-4 w-4" />
                    Enviar comprobante de pago por WhatsApp
                </a>
                <p class="mt-2 text-xs text-gray-400">Tambien es el numero de soporte de {{ \App\Models\PlatformSetting::appName() }}.</p>
            @endif
        </div>

        {{-- Otros planes --}}
        @if ($otherPlans->isNotEmpty())
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Otros planes disponibles</p>
                <div class="mt-3 -mx-1 flex snap-x snap-mandatory gap-4 overflow-x-auto px-1 pb-2">
                    @foreach ($otherPlans as $plan)
                        <article class="w-64 shrink-0 snap-start rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">{{ $plan->code }}</p>
                            <h4 class="mt-1 text-lg font-black text-gray-900">{{ $plan->name }}</h4>
                            <p class="mt-1 text-2xl font-black text-gray-900">
                                ${{ $this->formatMoney($plan->base_price) }}<span class="text-xs font-normal text-gray-400">/mes</span>
                            </p>

                            @if ($plan->modules->isNotEmpty())
                                <ul class="mt-4 space-y-1.5 text-xs text-gray-600">
                                    @foreach ($plan->modules as $module)
                                        <li class="flex items-center gap-1.5">
                                            <x-heroicon-o-check class="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                            {{ $module->name }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Equipos en alquiler (solo lectura) --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Hardware</p>
            <h3 class="mt-2 text-2xl font-black text-gray-900">Equipos en alquiler</h3>
            <p class="mt-1 text-sm text-gray-500">Impresora termica y lector de codigo de barras alquilados a esta empresa. La entrega y el alquiler los gestiona el equipo de {{ \App\Models\PlatformSetting::appName() }}, no se activan desde aqui.</p>

            <div class="mt-5 space-y-4">
                @foreach ($equipmentTypes as $type)
                    @php
                        $rentals = $this->activeEquipmentRentals($type);
                        $requestedCount = $this->requestedEquipmentCount($type);
                    @endphp
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $type->label() }}</p>
                                <p class="text-xs text-gray-500">${{ $this->formatMoney(\App\Support\EquipmentRentalCatalog::monthlyPrice($type)) }}/mes por unidad</p>
                            </div>

                            @if ($rentals->count() > 0)
                                <x-status-badge color="emerald">{{ $rentals->count() }} {{ \Illuminate\Support\Str::plural('unidad', $rentals->count()) }}</x-status-badge>
                            @elseif ($requestedCount > 0)
                                <x-status-badge color="amber">{{ $requestedCount }} solicitada(s)</x-status-badge>
                            @else
                                <x-status-badge color="stone">Sin alquiler</x-status-badge>
                            @endif
                        </div>

                        @if ($rentals->count() > 0)
                            <div class="mt-3 space-y-2">
                                @foreach ($rentals as $rental)
                                    @php
                                        $months = $rental->monthsElapsed();
                                        $recovered = $rental->amountRecovered();
                                        $pct = min(100, (int) round(($recovered / (float) $rental->unit_cost) * 100));
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span>Unidad #{{ $rental->company_sequence }} — desde {{ $rental->started_at->format('d/m/Y') }}</span>
                                            <span>{{ $months }} {{ \Illuminate\Support\Str::plural('mes', $months) }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 rounded-full bg-gray-100">
                                            <div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>
