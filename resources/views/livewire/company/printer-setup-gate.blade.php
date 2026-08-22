<div>
    @if ($pending->isNotEmpty())
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Configuracion inicial</p>
                <h3 class="mt-1 text-lg font-black text-gray-900">Tipo de impresora</h3>

                @if ($canManage)
                    <p class="mt-2 text-sm text-gray-500">
                        Antes de continuar, elige que impresora usa cada caja. Puedes cambiar esto despues.
                    </p>

                    <form wire:submit="save" class="mt-5 space-y-4">
                        @foreach ($pending as $cashRegister)
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {{ $cashRegister->name }} <span class="text-rose-600">*</span>
                                </label>
                                <select wire:model="printerTypes.{{ $cashRegister->id }}"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                    @foreach (\App\Models\CashRegister::PRINTER_TYPES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Guardar
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-500">
                        Falta configurar el tipo de impresora de tu caja antes de continuar. Pide a un administrador que complete esta configuracion en Admin &rarr; Estructura.
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
