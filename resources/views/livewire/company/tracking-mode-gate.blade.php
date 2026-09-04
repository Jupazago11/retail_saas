<div>
    @if ($pending)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Configuracion inicial</p>
                <h3 class="mt-1 text-lg font-black text-gray-900">Modo de venta de tus platos</h3>

                @if ($canManage)
                    <p class="mt-2 text-sm text-gray-500">
                        Antes de continuar, elige como quieres manejar tus platos. Puedes cambiar esto despues en Configuracion.
                    </p>

                    <form wire:submit="save" class="mt-5 space-y-3">
                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                            <input type="radio" wire:model="trackingMode" value="simple" class="mt-1 border-gray-300 text-blue-600 focus:ring-blue-600">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">Producto simple</span>
                                <span class="block text-xs text-gray-500">Cada plato se vende y descuenta su propio stock, sin desglosar insumos.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                            <input type="radio" wire:model="trackingMode" value="recipe" class="mt-1 border-gray-300 text-blue-600 focus:ring-blue-600">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">Con receta (insumos)</span>
                                <span class="block text-xs text-gray-500">Cada plato descuenta los insumos de su receta al venderse, no su propio stock.</span>
                            </span>
                        </label>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Guardar
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-500">
                        Falta elegir el modo de venta de tus platos antes de continuar. Pide a un administrador que complete esta configuracion.
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
