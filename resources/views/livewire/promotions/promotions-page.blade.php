<div class="py-10">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Reglas visibles</p>
                <p class="mt-2 text-3xl font-black text-gray-900">{{ $statusCards['total'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Activas</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">{{ $statusCards['active'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Descuentos</p>
                <p class="mt-2 text-3xl font-black text-sky-700">{{ $statusCards['product_discounts'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-500">Vigentes / Programadas</p>
                <p class="mt-2 text-3xl font-black text-blue-700">{{ $statusCards['running'] }} / {{ $statusCards['upcoming'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.95fr_1.15fr]">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Alta</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">{{ $editingPromotionId ? 'Editar promocion' : 'Nueva promocion' }}</h3>
                    </div>

                    <button wire:click="resetPromotionForm" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                        {{ $editingPromotionId ? 'Cancelar edicion' : 'Limpiar' }}
                    </button>
                </div>

                <div class="mt-6 space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Nombre <span class="text-rose-600">*</span></label>
                            <input wire:model="name" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Codigo</label>
                            <input wire:model="code" type="text" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Estado <span class="text-rose-600">*</span></label>
                            <select wire:model.live="status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                                <option value="archived">Archivada</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Tipo <span class="text-rose-600">*</span></label>
                            <select wire:model.live="promotionType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="product_discount">Descuento por producto</option>
                                <option value="combo_price">Combo a precio fijo</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Descuento <span class="text-rose-600">*</span></label>
                            <select wire:model.live="discountType" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                @if ($promotionType === 'combo_price')
                                    <option value="fixed_price">Precio fijo</option>
                                @else
                                    <option value="percentage">Porcentaje</option>
                                    <option value="fixed_amount">Monto fijo</option>
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Valor <span class="text-rose-600">*</span></label>
                            <input wire:model="discountValue" type="number" min="0.0001" step="0.0001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Prioridad <span class="text-rose-600">*</span></label>
                            <input wire:model="priority" type="number" min="0" step="1" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Inicia</label>
                            <input wire:model="startsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Termina</label>
                            <input wire:model="endsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                    </div>

                    @if ($promotionType === 'product_discount')
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-500">Objetivos</p>
                                    <h4 class="mt-1 text-lg font-black text-gray-900">Alcance de la promocion</h4>
                                </div>

                                <button wire:click="addTargetLine" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                                    Agregar objetivo
                                </button>
                            </div>


                            <div class="mt-4 space-y-4">
                                @foreach ($targets as $index => $target)
                                    <div wire:key="promotion-target-line-{{ $target['_key'] }}" class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                                        <div class="grid gap-4 md:grid-cols-[170px_minmax(0,1fr)_150px_auto]">
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Tipo</label>
                                                <select wire:model.live="targets.{{ $index }}.target_type" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                                    <option value="product">Producto</option>
                                                    <option value="category">Categoria</option>
                                                    <option value="variant">Variante</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Objetivo</label>
                                                <select wire:model="targets.{{ $index }}.target_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                                    <option value="">Selecciona</option>
                                                    @foreach ($this->targetOptionsForType($target['target_type']) as $option)
                                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Minimo</label>
                                                <input wire:model="targets.{{ $index }}.min_quantity" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                            </div>

                                            <div class="flex items-end">
                                                <button wire:click="removeTargetLine({{ $target['_key'] }})" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                                                    Quitar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($promotionType === 'combo_price')
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-500">Componentes</p>
                                    <h4 class="mt-1 text-lg font-black text-gray-900">Arma el combo</h4>
                                </div>

                                <button wire:click="addComboItemLine" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                                    Agregar item
                                </button>
                            </div>


                            <div class="mt-4 space-y-4">
                                @foreach ($comboItems as $index => $comboItem)
                                    @php
                                        $variantsForProduct = $this->variants()->where('product_id', (int) ($comboItem['product_id'] ?: 0))->values();
                                    @endphp
                                    <div wire:key="promotion-combo-line-{{ $comboItem['_key'] }}" class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                                        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px_140px_auto]">
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Producto</label>
                                                <select wire:model.live="comboItems.{{ $index }}.product_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                                    <option value="">Selecciona</option>
                                                    @foreach ($this->products() as $product)
                                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Variante</label>
                                                <select wire:model="comboItems.{{ $index }}.product_variant_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                                    <option value="">Sin variante</option>
                                                    @foreach ($variantsForProduct as $variant)
                                                        <option value="{{ $variant->id }}">{{ $variant->sku ?: 'Variante '.$variant->id }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Cantidad</label>
                                                <input wire:model="comboItems.{{ $index }}.required_quantity" type="number" min="0.000001" step="0.000001" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                            </div>

                                            <div class="flex items-end">
                                                <button wire:click="removeComboItemLine({{ $comboItem['_key'] }})" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                                                    Quitar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex gap-2">
                        <button wire:click="savePromotion" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                            {{ $editingPromotionId ? 'Actualizar promocion' : 'Guardar promocion' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-700">Catalogo comercial</p>
                        <h3 class="mt-2 text-2xl font-black text-gray-900">Promociones registradas</h3>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_160px_190px_180px]">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por nombre o codigo" class="rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <x-searchable-select
                            id="promotions-filter-status"
                            model="statusFilter"
                            placeholder="Todos los estados"
                            live
                            :options="[
                                ['id' => 'active', 'label' => 'Activas'],
                                ['id' => 'inactive', 'label' => 'Inactivas'],
                                ['id' => 'archived', 'label' => 'Archivadas'],
                            ]"
                        />
                        <x-searchable-select
                            id="promotions-filter-type"
                            model="typeFilter"
                            placeholder="Todos los tipos"
                            live
                            :options="[
                                ['id' => 'product_discount', 'label' => 'Descuento por producto'],
                                ['id' => 'combo_price', 'label' => 'Combo a precio fijo'],
                            ]"
                        />
                        <x-searchable-select
                            id="promotions-filter-effective-state"
                            model="effectiveStateFilter"
                            placeholder="Todas las vigencias"
                            live
                            :options="[
                                ['id' => 'running', 'label' => 'Vigentes'],
                                ['id' => 'upcoming', 'label' => 'Programadas'],
                                ['id' => 'expired', 'label' => 'Vencidas'],
                                ['id' => 'inactive', 'label' => 'Inactivas'],
                                ['id' => 'archived', 'label' => 'Archivadas'],
                            ]"
                        />
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($promotions as $promotion)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-lg font-black text-gray-900">{{ $promotion->name }}</h4>
                                        <x-status-badge :color="$promotion->status === 'active' ? 'emerald' : ($promotion->status === 'inactive' ? 'amber' : 'stone')">
                                            {{ $this->statusLabel($promotion->status) }}
                                        </x-status-badge>
                                        <x-status-badge color="sky">
                                            {{ $this->promotionTypeLabel($promotion->promotion_type) }}
                                        </x-status-badge>
                                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                                            {{ $this->effectiveStateLabel($promotion) }}
                                        </span>
                                    </div>

                                    <div class="grid gap-2 text-sm text-gray-600 md:grid-cols-2">
                                        <p>Codigo: <span class="font-medium text-gray-900">{{ $promotion->code ?: 'Sin codigo' }}</span></p>
                                        <p>Descuento: <span class="font-medium text-gray-900">{{ $this->discountTypeLabel($promotion->discount_type) }} · {{ number_format((float) $promotion->discount_value, 4, '.', ',') }}</span></p>
                                        <p>Prioridad: <span class="font-medium text-gray-900">{{ $promotion->priority }}</span></p>
                                        <p>Ventana: <span class="font-medium text-gray-900">{{ optional($promotion->starts_at)->format('Y-m-d H:i') ?: 'Inmediata' }} a {{ optional($promotion->ends_at)->format('Y-m-d H:i') ?: 'Sin cierre' }}</span></p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button wire:click="startEditingPromotion({{ $promotion->id }})" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
                                        Editar
                                    </button>

                                    <button wire:click="duplicatePromotion({{ $promotion->id }})" class="rounded-full border border-sky-300 px-4 py-2 text-sm font-semibold text-sky-700">
                                        Duplicar
                                    </button>

                                    @if ($promotion->status !== 'archived')
                                        <button wire:click="archivePromotion({{ $promotion->id }})" class="rounded-full border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700">
                                            Archivar
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if ($promotion->promotion_type === 'product_discount')
                                <div class="mt-4 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Objetivos</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($promotion->targets as $target)
                                            <span class="rounded-full bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700">
                                                {{ $this->targetTypeLabel($target->target_type) }} · {{ $this->promotionTargetLabel($target) }} · min {{ number_format((float) $target->min_quantity, 2, '.', ',') }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($promotion->promotion_type === 'combo_price')
                                <div class="mt-4 rounded-lg bg-white p-4 ring-1 ring-gray-200">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Componentes</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($promotion->comboItems as $comboItem)
                                            <span class="rounded-full bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700">
                                                {{ $this->comboItemLabel($comboItem) }} · x{{ number_format((float) $comboItem->required_quantity, 2, '.', ',') }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                            Aun no hay promociones con el filtro actual.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
