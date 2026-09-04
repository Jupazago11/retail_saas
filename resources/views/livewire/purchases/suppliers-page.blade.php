<div class="pb-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

        <x-purchases-nav active="purchases.suppliers" />

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Maestro</p>
                    <h3 class="mt-1 text-2xl font-black text-gray-900">Proveedores registrados</h3>
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
                            <th class="pb-2">Proveedor</th>
                            <th class="pb-2">Contacto</th>
                            <th class="pb-2">Plazo</th>
                            <th class="pb-2">Saldo a favor</th>
                            <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                            <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($suppliers as $supplier)
                            @php
                                $fullName = trim(implode(' ', array_filter([
                                    $supplier->person?->first_name,
                                    $supplier->person?->last_name,
                                ])));
                            @endphp
                            <tr wire:key="supplier-{{ $supplier->id }}" class="even:bg-gray-50">
                                <td class="py-3 align-middle">
                                    <p class="font-semibold text-gray-900">{{ $fullName !== '' ? $fullName : 'Sin nombre' }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ $supplier->person?->document_type ?: 'Sin tipo' }}
                                        @if ($supplier->person?->document_number)
                                            · {{ $supplier->person->document_number }}
                                        @endif
                                    </p>
                                </td>
                                <td class="py-3 align-middle text-gray-600 text-xs">
                                    <p>{{ $supplier->person?->phone ?: 'Sin telefono' }}</p>
                                    <p>{{ $supplier->person?->email ?: 'Sin email' }}</p>
                                </td>
                                <td class="py-3 align-middle text-gray-600 text-sm">
                                    {{ $supplier->payment_term_days !== null ? $supplier->payment_term_days.' dias' : '—' }}
                                </td>
                                <td class="py-3 align-middle">
                                    <x-status-badge color="emerald">
                                        ${{ \App\Support\Money::format((float) $supplier->credit_balance) }}
                                    </x-status-badge>
                                </td>
                                <td class="py-3 align-middle w-px whitespace-nowrap">
                                    <x-status-toggle :active="$supplier->status === 'active'" action="toggleSupplierStatus({{ $supplier->id }})" />
                                </td>
                                <td class="py-3 align-middle">
                                    <div class="flex justify-end gap-1"
                                        x-data="{tip:false,p:{},show(e){const r=e.getBoundingClientRect();this.p={t:r.top-38,l:r.left+r.width/2};this.tip=true;}}">
                                        <button wire:click="editSupplier({{ $supplier->id }})"
                                            @mouseenter="show($el)" @mouseleave="tip=false"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H9v-2.414a2 2 0 01.586-1.415z"/>
                                            </svg>
                                        </button>
                                        <div x-show="tip" :style="`position:fixed;top:${p.t}px;left:${p.l}px;transform:translateX(-50%);z-index:9999`"
                                            class="pointer-events-none whitespace-nowrap rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-lg">
                                            Editar proveedor
                                            <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-stone-900"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-400">
                                    Aun no hay proveedores registrados para esta empresa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Boton flotante + (oculto mientras hay un modal abierto para que no quede flotando sobre el overlay) --}}
    @if (! $showModal)
    <button wire:click="openModal" title="Nuevo proveedor"
        style="position:fixed;bottom:2rem;right:2rem;z-index:9999;width:3.5rem;height:3.5rem;border-radius:9999px;background:#2563eb;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,0.25);"
        onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#1c1917'">
        <svg xmlns="http://www.w3.org/2000/svg" style="width:1.75rem;height:1.75rem" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
    </button>
    @endif

    {{-- Modal crear / editar proveedor --}}
    @if ($showModal)
        <div wire:click.self="closeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-xl rounded-xl bg-white shadow-xl flex flex-col" style="max-height: 90vh;">

                {{-- Header --}}
                <div class="flex-shrink-0 flex items-center justify-between border-b border-stone-100 px-5 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">
                            {{ $editingSupplierId ? 'Editar' : 'Nuevo' }}
                        </p>
                        <h3 class="mt-0.5 text-lg font-black text-gray-900">Proveedor</h3>
                    </div>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-700 text-xl leading-none px-1">&times;</button>
                </div>

                {{-- Form --}}
                <form wire:submit="saveSupplier" class="flex flex-col flex-1 min-h-0">
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="supplier-document-type" class="text-xs font-medium text-gray-700">Tipo documento</label>
                                <input wire:model="documentType" id="supplier-document-type" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label for="supplier-document-number" class="text-xs font-medium text-gray-700">Numero documento</label>
                                <input wire:model="documentNumber" id="supplier-document-number" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="supplier-first-name" class="text-xs font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                                <input wire:model="firstName" id="supplier-first-name" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label for="supplier-last-name" class="text-xs font-medium text-gray-700">Apellido</label>
                                <input wire:model="lastName" id="supplier-last-name" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="supplier-phone" class="text-xs font-medium text-gray-700">Telefono</label>
                                <input wire:model="phone" id="supplier-phone" type="text"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                            <div>
                                <label for="supplier-email" class="text-xs font-medium text-gray-700">Email</label>
                                <input wire:model="email" id="supplier-email" type="email"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                            </div>
                        </div>

                        <div>
                            {{-- Sin campo "Estado": todo proveedor se crea activo, y ya se
                                 puede activar/desactivar sin abrir este modal con el pill de
                                 la columna "Estado" en la tabla. --}}
                            <label for="supplier-payment-term-days" class="text-xs font-medium text-gray-700">Plazo de pago (dias)</label>
                            <input wire:model="paymentTermDays" id="supplier-payment-term-days" type="number" min="0"
                                class="mt-1 block w-full max-w-[200px] rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm">
                        </div>

                        <div>
                            <label for="supplier-notes" class="text-xs font-medium text-gray-700">Notas</label>
                            <textarea wire:model="notes" id="supplier-notes" rows="3"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600 text-sm"></textarea>
                        </div>

                    </div>{{-- /scrollable body --}}

                    {{-- Footer --}}
                    <div class="flex-shrink-0 flex gap-2 border-t border-stone-100 px-5 py-3">
                        <button type="button" wire:click="closeModal"
                            class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            {{ $editingSupplierId ? 'Actualizar' : 'Guardar' }}
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif
</div>
