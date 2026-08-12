<div>
    {{-- Livewire needs a single root element. "space-y-*" only wraps the normal
         content below so it never adds margin-top to the modals (siblings of
         this div, not children of it), which used to push a "fixed inset-0"
         overlay down from the real top of the viewport. --}}
    <div class="space-y-6">

    {{-- Header --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
        <h1 class="mt-1 text-2xl font-black text-gray-900">Suscripciones</h1>
    </div>

    {{-- Filtros --}}
    <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="text"
                    placeholder="Buscar empresa..."
                    class="w-56 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                <select wire:model.live="filter"
                    class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
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
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="pb-2">Empresa</th>
                        <th class="pb-2">Propietario</th>
                        <th class="pb-2">Plan</th>
                        <th class="pb-2 w-px whitespace-nowrap">Equipos</th>
                        <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                        <th class="pb-2 w-px whitespace-nowrap">Inicio</th>
                        <th class="pb-2 w-px whitespace-nowrap">Vence</th>
                        <th class="pb-2 w-px whitespace-nowrap">Pago</th>
                        <th class="pb-2 w-px whitespace-nowrap text-center">Auto-renovación</th>
                        <th class="pb-2 w-px whitespace-nowrap text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($subscriptions as $sub)
                        @php $isPastDue = $sub->isPastDue(); @endphp
                        <tr wire:key="sub-{{ $sub->id }}" class="even:bg-gray-50">
                            <td class="py-3 align-middle">
                                <p class="font-semibold text-gray-900">{{ $sub->company?->display_name ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ $sub->company?->legal_name ?? '' }}</p>
                            </td>
                            <td class="py-3 align-middle text-xs text-gray-500">{{ $sub->company?->owner?->name ?? '—' }}</td>
                            <td class="py-3 align-middle text-xs text-gray-600">{{ $sub->plan?->name ?? '—' }}</td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                @if (($equipmentDisplaySubscriptionIds[$sub->company_id] ?? null) === $sub->id)
                                    @php $companyEquipment = $equipmentByCompany[$sub->company_id] ?? []; @endphp
                                    <button type="button" wire:click="openEquipmentModal({{ $sub->company_id }})"
                                        title="Gestionar equipos"
                                        class="flex items-center gap-2 rounded-full border border-transparent p-1 transition hover:border-gray-200">
                                        @foreach ($equipmentTypes as $type)
                                            @php
                                                $counts = $companyEquipment[$type->value] ?? collect();
                                                $activeCount = $counts['active'] ?? 0;
                                                $requestedCount = $counts['requested'] ?? 0;
                                                $pendingCount = $counts['pending_return'] ?? 0;
                                                $badgeColor = match(true) {
                                                    $activeCount > 0 => 'bg-emerald-50 text-emerald-600',
                                                    $pendingCount > 0 => 'bg-amber-50 text-amber-600',
                                                    $requestedCount > 0 => 'bg-blue-50 text-blue-600',
                                                    default => 'bg-gray-50 text-gray-300',
                                                };
                                            @endphp
                                            <span title="{{ $type->label() }}: {{ $activeCount }} en uso, {{ $requestedCount }} solicitada(s), {{ $pendingCount }} pendiente(s) de devolucion"
                                                class="relative inline-flex h-6 w-6 items-center justify-center rounded-full {{ $badgeColor }}">
                                                @if ($type === \App\Enums\EquipmentType::ThermalPrinter)
                                                    <x-heroicon-o-printer class="h-3.5 w-3.5" />
                                                @else
                                                    <x-heroicon-o-qr-code class="h-3.5 w-3.5" />
                                                @endif
                                                @if ($activeCount > 1)
                                                    <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-emerald-600 text-[9px] font-bold text-white">{{ $activeCount }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </button>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                @if ($isPastDue)
                                    <x-status-badge color="rose">vencida</x-status-badge>
                                @elseif ($sub->status === 'active')
                                    <x-status-badge color="emerald">activa</x-status-badge>
                                @elseif ($sub->status === 'pending')
                                    <x-status-badge color="amber">pendiente</x-status-badge>
                                @elseif ($sub->status === 'trialing')
                                    <x-status-badge color="blue">trial</x-status-badge>
                                @else
                                    <x-status-badge color="stone">{{ $sub->status }}</x-status-badge>
                                @endif
                            </td>
                            <td class="py-3 align-middle text-xs text-gray-400 w-px whitespace-nowrap">
                                {{ $sub->starts_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 align-middle text-xs w-px whitespace-nowrap
                                {{ $sub->ends_at && $sub->ends_at->isPast() ? 'text-rose-500 font-semibold' : 'text-gray-400' }}">
                                {{ $sub->ends_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    @if ($sub->isPaid())
                                        <span title="{{ $sub->payment_reference ?? 'Sin referencia' }}">
                                            <x-status-badge color="emerald">{{ $sub->paid_at->format('d/m/Y') }}</x-status-badge>
                                        </span>
                                    @else
                                        <button type="button" wire:click="markPaid({{ $sub->id }})"
                                            title="Marcar esta suscripcion como pagada"
                                            class="whitespace-nowrap rounded-full border border-amber-300 px-3 py-1 text-xs font-semibold text-amber-700 transition hover:bg-amber-50">
                                            Sin confirmar
                                        </button>
                                    @endif
                                    <button type="button" wire:click="openAttachmentsModal({{ $sub->id }})"
                                        title="Comprobantes de pago"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                                        <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap text-center">
                                @if ($sub->company)
                                    <button wire:click="toggleAutoRenew({{ $sub->company_id }})"
                                        title="{{ $sub->company->auto_renew ? 'Apagar renovación automática' : 'Encender renovación automática' }}"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $sub->company->auto_renew ? 'bg-emerald-500' : 'bg-stone-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $sub->company->auto_renew ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap text-right space-x-2">
                                <button wire:click="startEdit({{ $sub->id }})"
                                    class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                    Editar
                                </button>
                                @if (in_array($sub->id, $latestSubscriptionIds, true) && ($isPastDue || $sub->status === 'ended'))
                                    <button wire:click="startActivate({{ $sub->company_id }})"
                                        class="rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700">
                                        Activar nuevo plan
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-10 text-center text-xs text-gray-400">Sin suscripciones.</td>
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
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">Editar suscripción</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Plan</label>
                        <select wire:model="editPlanId"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Seleccionar plan...</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        @error('editPlanId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de vencimiento</label>
                        <input wire:model="editEndsAt" type="date"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <p class="mt-1 text-xs text-gray-400">Dejar vacío para sin vencimiento.</p>
                        @error('editEndsAt') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="saveEdit"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal activar nuevo plan --}}
    @if ($showActivateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">Activar nuevo plan</h3>
                <p class="mt-1 text-xs text-gray-400">Crea una suscripción directa nueva para esta empresa. Si existe una suscripción vigente, se cierra automáticamente.</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Plan</label>
                        <select wire:model="activatePlanId"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">Seleccionar plan...</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                        @error('activatePlanId') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de inicio</label>
                        <input wire:model="activateStartsAt" type="date"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('activateStartsAt') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha de vencimiento</label>
                        <input wire:model="activateEndsAt" type="date"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <p class="mt-1 text-xs text-gray-400">Se sugiere 31 dias desde hoy. Dejar vacío a proposito para que no venza nunca.</p>
                        @error('activateEndsAt') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Referencia de pago</label>
                        <input wire:model="activatePaymentReference" type="text" placeholder="Ej: Transferencia Bancolombia, comprobante por WhatsApp"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <p class="mt-1 text-xs text-gray-400">Al activar queda marcado como pagado hoy. Opcional, para dejar una nota de que comprobante lo confirma.</p>
                        @error('activatePaymentReference') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeActivateModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="saveActivate"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                        Activar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal equipos --}}
    @if ($showEquipmentModal)
        @php
            $company = $this->equipmentCompany();
            $rentals = $this->equipmentRentalsForModal();
        @endphp
        <div class="fixed inset-0 z-50 bg-black/40"></div>

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:click="closeEquipmentModal">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200" wire:click.stop>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Hardware</p>
                        <h3 class="mt-1 text-lg font-black text-gray-900">Equipos en alquiler</h3>
                        <p class="text-xs text-gray-400">{{ $company?->display_name }}</p>
                    </div>
                    <button type="button" wire:click="closeEquipmentModal" class="text-gray-400 transition hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    @foreach ($equipmentTypes as $type)
                        @php
                            $typeRentals = $rentals->where('equipment_type', $type);
                            $activeCount = $typeRentals->where('status', \App\Enums\EquipmentRentalStatus::Active)->count();
                        @endphp
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $type->label() }}</p>
                                    <p class="text-xs text-gray-500">${{ $this->formatMoney(\App\Support\EquipmentRentalCatalog::monthlyPrice($type)) }}/mes por unidad — {{ $activeCount }} activa(s)</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" wire:click="requestEquipment('{{ $type->value }}')"
                                        class="whitespace-nowrap rounded-full border border-blue-300 px-3 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-50">
                                        Solicitar
                                    </button>
                                    <button type="button" wire:click="addEquipment('{{ $type->value }}')"
                                        class="whitespace-nowrap rounded-full bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700">
                                        + Agregar unidad
                                    </button>
                                </div>
                            </div>

                            @if ($typeRentals->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach ($typeRentals as $rental)
                                        @php
                                            $rentalBadgeColor = match ($rental->status->value) {
                                                'active' => 'emerald',
                                                'requested' => 'blue',
                                                'pending_return' => 'amber',
                                                default => 'stone',
                                            };
                                        @endphp
                                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-xs">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-gray-900">#{{ $rental->company_sequence }}</span>
                                                <x-status-badge color="{{ $rentalBadgeColor }}">{{ $rental->status->label() }}</x-status-badge>
                                                @if ($rental->replaces_rental_id)
                                                    <span class="text-gray-400">(reemplaza #{{ $rental->replaces?->company_sequence ?? '?' }})</span>
                                                @endif
                                                <span class="text-gray-400">
                                                    {{ $rental->status->value === 'requested' ? 'pedido '.$rental->requested_at?->format('d/m/Y') : 'desde '.$rental->started_at?->format('d/m/Y') }}
                                                </span>
                                            </div>
                                            <div class="flex gap-1.5">
                                                @if ($rental->status->value === 'requested')
                                                    <button type="button" wire:click="fulfillEquipment({{ $rental->id }})" class="rounded-full border border-emerald-300 px-2 py-0.5 font-semibold text-emerald-700 transition hover:bg-emerald-50">Entregar</button>
                                                @elseif ($rental->status->value === 'active')
                                                    <button type="button" wire:click="replaceEquipment({{ $rental->id }})" class="rounded-full border border-gray-300 px-2 py-0.5 font-semibold text-gray-600 transition hover:bg-gray-100">Reemplazar</button>
                                                    <button type="button" wire:click="requestEquipmentReturn({{ $rental->id }})" class="rounded-full border border-rose-300 px-2 py-0.5 font-semibold text-rose-700 transition hover:bg-rose-50">Cancelar</button>
                                                @elseif ($rental->status->value === 'pending_return')
                                                    <button type="button" wire:click="markEquipmentReturned({{ $rental->id }})" class="rounded-full border border-amber-300 px-2 py-0.5 font-semibold text-amber-700 transition hover:bg-amber-50">Marcar devuelto</button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nota (opcional, aplica a la proxima accion)</label>
                        <input wire:model="equipmentNotes" type="text" placeholder="Ej: se dano la impresora, se envia reemplazo"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Historial</p>
                    <div class="mt-2 max-h-48 space-y-2 overflow-y-auto">
                        @forelse ($this->equipmentAuditForModal() as $log)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-700">{{ $this->equipmentAuditLabel($log) }} — {{ $log->actor?->name ?? 'Sistema' }}</span>
                                <span class="text-gray-400">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">Sin movimientos todavia.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" wire:click="closeEquipmentModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal comprobantes de pago --}}
    @if ($showAttachmentsModal)
        @php
            $paymentSubscription = $this->attachmentsSubscription();
            $attachments = $this->attachmentsForModal();
            $r2Ready = $this->isR2Configured();
        @endphp
        <div class="fixed inset-0 z-50 bg-black/40"></div>

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:click="closeAttachmentsModal">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200" wire:click.stop>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Pago</p>
                        <h3 class="mt-1 text-lg font-black text-gray-900">Comprobantes de pago</h3>
                        <p class="text-xs text-gray-400">{{ $paymentSubscription?->company?->display_name }} — {{ $paymentSubscription?->plan?->name ?? 'Sin plan' }}</p>
                    </div>
                    <button type="button" wire:click="closeAttachmentsModal" class="text-gray-400 transition hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                @unless ($r2Ready)
                    <div class="mt-4 rounded-lg bg-amber-50 p-4 text-xs text-amber-700 ring-1 ring-amber-200">
                        El bucket de Cloudflare R2 todavia no esta configurado (variables <code>R2_*</code> en el <code>.env</code>). Puedes seguir usando el resto de la plataforma; en cuanto llenes esas variables, este boton empieza a funcionar sin tocar nada mas.
                    </div>
                @endunless

                <div class="mt-5" x-data="{ uploadFailed: false }"
                    x-on:livewire-upload-start.window="uploadFailed = false"
                    x-on:livewire-upload-error.window="uploadFailed = true">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Adjuntar foto(s)</label>
                    <input type="file" wire:model="newAttachments" multiple accept="image/*"
                        {{ $r2Ready ? '' : 'disabled' }}
                        class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-50">
                    <p class="mt-1 text-xs text-gray-400">Desde el celular deja elegir camara o galeria (maximo 20MB por foto). Se pueden agregar mas fotos despues, en cualquier momento.</p>
                    <p class="mt-1 text-xs text-rose-500" x-show="uploadFailed" x-cloak>
                        No se pudo subir la foto. Lo mas probable es que pese mas de 20MB — comprimela o recortala e intenta de nuevo.
                    </p>
                    @error('newAttachments') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    @error('newAttachments.*') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                    @if (! empty($newAttachments))
                        <div class="mt-3 flex justify-end">
                            <button type="button" wire:click="uploadAttachments" wire:loading.attr="disabled"
                                class="rounded-full bg-blue-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="uploadAttachments">Subir {{ count($newAttachments) }} foto(s)</span>
                                <span wire:loading wire:target="uploadAttachments">Subiendo...</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Adjuntos ({{ $attachments->count() }})</p>
                    @if ($attachments->isEmpty())
                        <p class="mt-2 text-xs text-gray-400">Sin comprobantes adjuntos todavia.</p>
                    @else
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            @foreach ($attachments as $attachment)
                                @php($attachmentUrl = $attachment->temporaryUrl())
                                <div class="group relative overflow-hidden rounded-lg border border-gray-200">
                                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                                        <img src="{{ $attachmentUrl }}" alt="{{ $attachment->original_filename }}" class="h-24 w-full object-cover">
                                    </a>
                                    <button type="button" wire:click="archiveAttachment({{ $attachment->id }})"
                                        title="Archivar (no se borra, solo deja de mostrarse aqui)"
                                        class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100 hover:bg-rose-600">
                                        <x-heroicon-o-archive-box class="h-3.5 w-3.5" />
                                    </button>
                                    <p class="truncate bg-gray-50 px-2 py-1 text-[10px] text-gray-500">{{ $attachment->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" wire:click="closeAttachmentsModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
