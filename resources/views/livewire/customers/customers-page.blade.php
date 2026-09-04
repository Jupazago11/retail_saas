<div class="pb-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

        <div x-data="responsivePageSize({ rowHeight: 72, reserved: 320 })" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Maestro</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Clientes registrados</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por nombre, documento o contacto"
                        class="w-64 rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
                        <button type="button" wire:click="setStatusFilter('')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === '' ? 'bg-gradient-to-br from-blue-600 to-purple-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Todos
                        </button>
                        <button type="button" wire:click="setStatusFilter('active')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Activos
                        </button>
                        <button type="button" wire:click="setStatusFilter('inactive')"
                            class="rounded-md px-3 py-1.5 font-semibold transition {{ $statusFilter === 'inactive' ? 'bg-stone-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                            Inactivos
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="pb-2">Cliente</th>
                            <th class="pb-2">Contacto</th>
                            <th class="pb-2">Credito</th>
                            <th class="pb-2">Fidelizacion</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($customers as $customer)
                            @php
                                $fullName = trim(implode(' ', array_filter([
                                    $customer->person?->first_name,
                                    $customer->person?->last_name,
                                ])));
                            @endphp
                            <tr wire:key="customer-{{ $customer->id }}" class="even:bg-gray-50">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $fullName !== '' ? $fullName : 'Sin nombre' }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $customer->person?->document_type ?: 'Sin tipo' }}
                                        @if ($customer->person?->document_number)
                                            · {{ $customer->person->document_number }}
                                        @endif
                                    </p>
                                </td>
                                <td class="py-3 align-middle text-gray-600 text-xs">
                                    <p>{{ $customer->person?->phone ?: 'Sin telefono' }}</p>
                                    <p>{{ $customer->person?->email ?: 'Sin email' }}</p>
                                </td>
                                <td class="py-3 align-middle">
                                    @if ($customer->credit_enabled && $customer->creditAccount)
                                        <x-status-badge color="emerald">
                                            ${{ \App\Support\Money::format((float) $customer->creditAccount->available_credit) }} disp.
                                        </x-status-badge>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-3 align-middle">
                                    @if ($customer->loyalty_enabled && $customer->loyaltyAccount)
                                        <x-status-badge color="violet">
                                            {{ number_format((float) $customer->loyaltyAccount->points_balance, 0, ',', '.') }} pts
                                        </x-status-badge>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    <x-status-toggle :active="$customer->status === 'active'" action="toggleCustomerStatus({{ $customer->id }})" />
                                </td>
                                <td class="py-3 align-middle">
                                    <div class="flex justify-end gap-1"
                                        x-data="{tip:false,p:{},show(e){const r=e.getBoundingClientRect();this.p={t:r.top-38,l:r.left+r.width/2};this.tip=true;}}">
                                        <button wire:click="editCustomer({{ $customer->id }})"
                                            @mouseenter="show($el)" @mouseleave="tip=false"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                            </svg>
                                        </button>
                                        <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                            class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                            Editar cliente
                                            <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">
                                    Aun no hay clientes registrados para esta empresa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($customers->hasPages())
                <div class="mt-4 border-t border-stone-100 pt-4">
                    {{ $customers->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Boton flotante + (oculto mientras hay un modal abierto para que no quede flotando sobre el overlay) --}}
    @if (! $showModal)
    <button wire:click="openModal" title="Nuevo cliente"
        style="position:fixed;bottom:2rem;right:2rem;z-index:9999;width:3.5rem;height:3.5rem;border-radius:9999px;background:#2563eb;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,0.25);"
        onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#1c1917'">
        <svg xmlns="http://www.w3.org/2000/svg" style="width:1.75rem;height:1.75rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
    </button>
    @endif

    {{-- Modal crear / editar cliente --}}
    @if ($showModal)
        <div wire:click.self="closeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-xl rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">
                            {{ $editingCustomerId ? 'Editar' : 'Nuevo' }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Cliente</h3>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                </div>

                {{-- Form --}}
                <form wire:submit="saveCustomer" class="flex flex-col flex-1 min-h-0">
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="customer-document-type" class="text-xs font-medium text-gray-700">Tipo documento</label>
                                <input wire:model="documentType" id="customer-document-type" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label for="customer-document-number" class="text-xs font-medium text-gray-700">Numero documento</label>
                                <input wire:model="documentNumber" id="customer-document-number" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="customer-first-name" class="text-xs font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                                <input wire:model="firstName" id="customer-first-name" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label for="customer-last-name" class="text-xs font-medium text-gray-700">Apellido</label>
                                <input wire:model="lastName" id="customer-last-name" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="customer-phone" class="text-xs font-medium text-gray-700">Telefono</label>
                                <input wire:model="phone" id="customer-phone" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label for="customer-email" class="text-xs font-medium text-gray-700">Email</label>
                                <input wire:model="email" id="customer-email" type="email"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>

                        {{-- Sin campos de credito/fidelizacion aqui: ese cupo y esos puntos
                             se administran desde los modulos de Credito y Fidelizacion,
                             para no duplicar el mismo flujo en dos pantallas. --}}
                        @if ($editingCustomerId)
                            <p class="text-xs text-gray-400">
                                El cupo de credito y los puntos de fidelizacion se administran desde sus respectivos modulos.
                            </p>
                        @endif

                    </div>{{-- /scrollable body --}}

                    {{-- Footer --}}
                    <div class="flex-shrink-0 flex gap-2 border-t border-stone-100 px-5 py-3">
                        <button type="button" wire:click="closeModal"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            {{ $editingCustomerId ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif
</div>
