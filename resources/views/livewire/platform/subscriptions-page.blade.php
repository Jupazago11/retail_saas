<div>
    {{-- Livewire needs a single root element. "space-y-*" only wraps the normal
         content below so it never adds margin-top to the modals (siblings of
         this div, not children of it), which used to push a "fixed inset-0"
         overlay down from the real top of the viewport. --}}
    <div class="space-y-6">

    {{-- Header --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Plataforma</p>
        <h1 class="mt-1 text-2xl font-black text-stone-900">Suscripciones</h1>
    </div>

    {{-- Filtros --}}
    <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Buscar empresa..."
                    class="w-56 rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                <select wire:model.live="filter"
                    class="rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <option value="all">Todas</option>
                    <option value="pending">Pendientes</option>
                    <option value="active">Activas</option>
                    <option value="trialing">Trial</option>
                    <option value="vencida">Vencidas</option>
                </select>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                        <th class="pb-2">Empresa</th>
                        <th class="pb-2">Propietario</th>
                        <th class="pb-2">Plan</th>
                        <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                        <th class="pb-2 w-px whitespace-nowrap">Inicio</th>
                        <th class="pb-2 w-px whitespace-nowrap">Vence</th>
                        <th class="pb-2 w-px whitespace-nowrap text-center">Auto-renovación</th>
                        <th class="pb-2 w-px whitespace-nowrap text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($subscriptions as $sub)
                        @php($isPastDue = $sub->isPastDue())
                        <tr wire:key="sub-{{ $sub->id }}">
                            <td class="py-3 align-middle">
                                <p class="font-semibold text-stone-900">{{ $sub->company?->display_name ?? '—' }}</p>
                                <p class="text-xs text-stone-400">{{ $sub->company?->legal_name ?? '' }}</p>
                            </td>
                            <td class="py-3 align-middle text-xs text-stone-500">{{ $sub->company?->owner?->name ?? '—' }}</td>
                            <td class="py-3 align-middle text-xs text-stone-600">{{ $sub->plan?->name ?? '—' }}</td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                @if ($isPastDue)
                                    <span class="inline-flex justify-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">vencida</span>
                                @elseif ($sub->status === 'active')
                                    <span class="inline-flex justify-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">activa</span>
                                @elseif ($sub->status === 'pending')
                                    <span class="inline-flex justify-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">pendiente</span>
                                @elseif ($sub->status === 'trialing')
                                    <span class="inline-flex justify-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">trial</span>
                                @else
                                    <span class="inline-flex justify-center rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-500">{{ $sub->status }}</span>
                                @endif
                            </td>
                            <td class="py-3 align-middle text-xs text-stone-400 w-px whitespace-nowrap">
                                {{ $sub->starts_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 align-middle text-xs w-px whitespace-nowrap
                                {{ $sub->ends_at && $sub->ends_at->isPast() ? 'text-rose-500 font-semibold' : 'text-stone-400' }}">
                                {{ $sub->ends_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap text-center">
                                @if ($sub->company)
                                    <button wire:click="toggleAutoRenew({{ $sub->company_id }})"
                                        title="{{ $sub->company->auto_renew ? 'Desactivar renovación automática' : 'Activar renovación automática' }}"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $sub->company->auto_renew ? 'bg-emerald-500' : 'bg-stone-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $sub->company->auto_renew ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap text-right space-x-2">
                                <button wire:click="startEdit({{ $sub->id }})"
                                    class="rounded-full border border-stone-300 px-3 py-1 text-xs font-semibold text-stone-600 transition hover:border-amber-400 hover:text-amber-700">
                                    Editar
                                </button>
                                @if (in_array($sub->id, $latestSubscriptionIds, true) && ($isPastDue || $sub->status === 'ended'))
                                    <button wire:click="startActivate({{ $sub->company_id }})"
                                        class="rounded-full bg-stone-900 px-3 py-1 text-xs font-semibold text-white transition hover:bg-stone-700">
                                        Activar nuevo plan
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-10 text-center text-xs text-stone-400">Sin suscripciones.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($subscriptions->hasPages())
            <div class="mt-4">{{ $subscriptions->links() }}</div>
        @endif
    </div>
    </div>

    {{-- Modal editar --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl ring-1 ring-stone-200">
                <h3 class="text-lg font-black text-stone-900">Editar suscripción</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Plan</label>
                        <select wire:model="editPlanId"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Seleccionar plan...</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        @error('editPlanId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Fecha de vencimiento</label>
                        <input wire:model="editEndsAt" type="date"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <p class="mt-1 text-xs text-stone-400">Dejar vacío para sin vencimiento.</p>
                        @error('editEndsAt') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal"
                        class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                        Cancelar
                    </button>
                    <button wire:click="saveEdit"
                        class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal activar nuevo plan --}}
    @if ($showActivateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl ring-1 ring-stone-200">
                <h3 class="text-lg font-black text-stone-900">Activar nuevo plan</h3>
                <p class="mt-1 text-xs text-stone-400">Crea una suscripción directa nueva para esta empresa. Si existe una suscripción vigente, se cierra automáticamente.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Plan</label>
                        <select wire:model="activatePlanId"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Seleccionar plan...</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        @error('activatePlanId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Fecha de inicio</label>
                        <input wire:model="activateStartsAt" type="date"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('activateStartsAt') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Fecha de vencimiento</label>
                        <input wire:model="activateEndsAt" type="date"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <p class="mt-1 text-xs text-stone-400">Dejar vacío para sin vencimiento.</p>
                        @error('activateEndsAt') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeActivateModal"
                        class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-600 transition hover:bg-stone-50">
                        Cancelar
                    </button>
                    <button wire:click="saveActivate"
                        class="rounded-full bg-stone-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-700">
                        Activar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
