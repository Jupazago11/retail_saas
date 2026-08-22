<div class="py-10">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('inventory.index') }}" wire:navigate class="text-sm font-medium text-gray-500 hover:text-gray-700">
            ← Volver a Inventario
        </a>
        <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Lote operativo</p>
                <h3 class="mt-2 text-2xl font-black text-gray-900">Contexto del ajuste</h3>

                <form wire:submit="import" class="mt-6 space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="inventory-branch" class="text-sm font-medium text-gray-700">Sucursal</label>
                            <select wire:model.live="branchId" id="inventory-branch" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">Selecciona</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="inventory-warehouse" class="text-sm font-medium text-gray-700">Bodega</label>
                            <select wire:model="warehouseId" id="inventory-warehouse" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">Selecciona</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="inventory-adjustment-type" class="text-sm font-medium text-gray-700">Tipo</label>
                            <select wire:model="adjustmentType" id="inventory-adjustment-type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="increase">Ingreso</option>
                                <option value="decrease">Salida</option>
                            </select>
                        </div>

                        <div>
                            <label for="inventory-adjusted-at" class="text-sm font-medium text-gray-700">Fecha operativa</label>
                            <input wire:model="adjustedAt" id="inventory-adjusted-at" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>

                    <div>
                        <label for="inventory-reason" class="text-sm font-medium text-gray-700">Motivo</label>
                        <input wire:model="reason" id="inventory-reason" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div>
                        <label for="inventory-notes" class="text-sm font-medium text-gray-700">Notas</label>
                        <textarea wire:model="notes" id="inventory-notes" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                    </div>

                    <div>
                        <label for="inventory-import-file" class="text-sm font-medium text-gray-700">Archivo CSV</label>
                        <input wire:model="importFile" id="inventory-import-file" type="file" accept=".csv,text/csv,.txt" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white">
                        Procesar lote
                    </button>
                </form>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Plantilla</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">CSV esperado</h3>
                    <p class="mt-3 text-sm leading-6 text-gray-600">
                        Columnas minimas: <span class="font-semibold text-gray-800">product_sku, quantity</span>.
                        Opcionales: <span class="font-semibold text-gray-800">variant_sku, unit_cost</span>.
                        En ajustes de salida, `unit_cost` puede omitirse.
                    </p>
                    <pre class="mt-4 overflow-x-auto rounded-lg bg-stone-950 p-4 text-xs leading-6 text-stone-100">product_sku,variant_sku,quantity,unit_cost
ARR-500,,12,1850
CAR-001,CAR-ROJ,4,920</pre>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Resultado</p>
                    <h3 class="mt-2 text-2xl font-black text-gray-900">Ultimo lote</h3>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Lineas validas</p>
                            <p class="mt-2 text-3xl font-black text-emerald-900">{{ $summary['created_count'] }}</p>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Errores</p>
                            <p class="mt-2 text-3xl font-black text-amber-900">{{ $summary['error_count'] }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-gray-600">
                        <p>Archivo: <span class="font-semibold text-gray-900">{{ $summary['file_name'] ?? 'Sin procesamiento reciente' }}</span></p>
                        <p>Ajuste creado: <span class="font-semibold text-gray-900">{{ $summary['adjustment_id'] ?? 'No creado' }}</span></p>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-sm font-semibold text-gray-900">Detalle de errores</h4>

                        @if ($summary['errors'] !== [])
                            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                                @foreach ($summary['errors'] as $error)
                                    <li class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">{{ $error }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-gray-500">Aun no hay errores registrados para el ultimo lote procesado.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
