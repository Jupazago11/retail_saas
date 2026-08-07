<div class="py-10">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-purchases-nav />

        <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Documento base</p>
                <h3 class="mt-2 text-2xl font-black text-stone-900">Contexto de compra</h3>

                <form wire:submit="import" class="mt-6 space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="purchase-import-branch" class="text-sm font-medium text-stone-700">Sucursal</label>
                            <select wire:model.live="branchId" id="purchase-import-branch" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Selecciona</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branchId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-warehouse" class="text-sm font-medium text-stone-700">Bodega</label>
                            <select wire:model="warehouseId" id="purchase-import-warehouse" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Selecciona</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            @error('warehouseId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-supplier" class="text-sm font-medium text-stone-700">Proveedor formal</label>
                            <select wire:model="supplierId" id="purchase-import-supplier" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="">Sin proveedor formal</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">
                                        {{ trim(($supplier->person?->first_name ?? '').' '.($supplier->person?->last_name ?? '')) ?: 'Proveedor #'.$supplier->id }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplierId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-supplier-name" class="text-sm font-medium text-stone-700">Nombre proveedor libre</label>
                            <input wire:model="supplierName" id="purchase-import-supplier-name" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('supplierName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-invoice-number" class="text-sm font-medium text-stone-700">Factura</label>
                            <input wire:model="invoiceNumber" id="purchase-import-invoice-number" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('invoiceNumber') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-type" class="text-sm font-medium text-stone-700">Tipo</label>
                            <input wire:model="purchaseType" id="purchase-import-type" type="text" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('purchaseType') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-status" class="text-sm font-medium text-stone-700">Estado</label>
                            <select wire:model="purchaseStatus" id="purchase-import-status" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="draft">Borrador</option>
                                <option value="confirmed">Confirmada</option>
                                <option value="partially_paid">Parcialmente pagada</option>
                                <option value="paid">Pagada</option>
                            </select>
                            @error('purchaseStatus') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-paid-amount" class="text-sm font-medium text-stone-700">Pago inicial</label>
                            <input wire:model="initialPaidAmount" id="purchase-import-paid-amount" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('initialPaidAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-purchased-at" class="text-sm font-medium text-stone-700">Fecha compra</label>
                            <input wire:model="purchasedAt" id="purchase-import-purchased-at" type="datetime-local" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('purchasedAt') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase-import-due-at" class="text-sm font-medium text-stone-700">Vencimiento</label>
                            <input wire:model="dueAt" id="purchase-import-due-at" type="datetime-local" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @error('dueAt') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="purchase-import-notes" class="text-sm font-medium text-stone-700">Notas</label>
                        <textarea wire:model="notes" id="purchase-import-notes" rows="3" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="purchase-import-file" class="text-sm font-medium text-stone-700">Archivo CSV</label>
                        <input wire:model="importFile" id="purchase-import-file" type="file" accept=".csv,text/csv,.txt" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('importFile') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                        Procesar lote
                    </button>
                </form>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Plantilla</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">CSV esperado</h3>
                    <p class="mt-3 text-sm leading-6 text-stone-600">
                        Columnas minimas: <span class="font-semibold text-stone-800">product_sku, quantity, unit_cost</span>.
                        Opcionales: <span class="font-semibold text-stone-800">presentation_name, variant_sku, tax_rate</span>.
                    </p>
                    <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-950 p-4 text-xs leading-6 text-stone-100">product_sku,presentation_name,variant_sku,quantity,unit_cost,tax_rate
ARR-500,,,12,1800,19
JUG-001,Pack x 6,CON-PULPA,3,8200,19</pre>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Resultado</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Ultimo lote</h3>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Lineas validas</p>
                            <p class="mt-2 text-3xl font-black text-emerald-900">{{ $summary['created_count'] }}</p>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Errores</p>
                            <p class="mt-2 text-3xl font-black text-amber-900">{{ $summary['error_count'] }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-stone-600">
                        <p>Archivo: <span class="font-semibold text-stone-900">{{ $summary['file_name'] ?? 'Sin procesamiento reciente' }}</span></p>
                        <p>Compra creada: <span class="font-semibold text-stone-900">{{ $summary['purchase_id'] ?? 'No creada' }}</span></p>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-stone-900">Detalle de errores</h4>

                        @if ($summary['errors'] !== [])
                            <ul class="mt-3 space-y-2 text-sm text-stone-700">
                                @foreach ($summary['errors'] as $error)
                                    <li class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">{{ $error }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-stone-500">Aun no hay errores registrados para el ultimo lote procesado.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
