<div>
    {{-- Livewire needs a single root element. "space-y-*" only wraps the normal
         content below so it never adds margin-top to the modal (a sibling of
         this div, not a child of it), which used to push the "fixed inset-0"
         overlay down from the real top of the viewport. --}}
    <div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-gray-900">Impresoras</h1>
        </div>
        <button wire:click="openCreate"
            class="rounded-full bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
            + Nueva guia
        </button>
    </div>

    {{-- Tabla --}}
    <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="pb-2">Nombre / modelo</th>
                        <th class="pb-2">Archivo</th>
                        <th class="pb-2 w-px whitespace-nowrap">Estado</th>
                        <th class="pb-2 w-px whitespace-nowrap text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($guides as $guide)
                        <tr wire:key="printer-guide-{{ $guide->id }}" class="even:bg-gray-50">
                            <td class="py-3 align-middle text-gray-700">{{ $guide->title }}</td>
                            <td class="py-3 align-middle text-xs">
                                @if ($guide->path)
                                    @php($guideUrl = $guide->temporaryUrl())
                                    <a href="{{ $guideUrl }}" target="_blank" rel="noopener" class="font-semibold text-blue-600 hover:text-blue-700">
                                        Descargar archivo
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                @if ($guide->status->value === 'active')
                                    <x-status-badge color="emerald">activo</x-status-badge>
                                @else
                                    <x-status-badge color="stone">inactivo</x-status-badge>
                                @endif
                            </td>
                            <td class="py-3 align-middle w-px whitespace-nowrap">
                                <div class="flex justify-end gap-2">
                                    <button wire:click="startEdit({{ $guide->id }})"
                                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-blue-400 hover:text-blue-700">
                                        Editar
                                    </button>
                                    <button wire:click="toggleStatus({{ $guide->id }})"
                                        class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold text-gray-600 transition hover:border-stone-400">
                                        {{ $guide->status->value === 'active' ? 'Inactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-xs text-gray-400">Sin guias creadas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200">
                <h3 class="text-lg font-black text-gray-900">{{ $editingId ? 'Editar guia' : 'Nueva guia' }}</h3>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre / modelo <span class="text-rose-600">*</span></label>
                        <input wire:model="title" type="text" placeholder="Xprinter XP-58"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Instrucciones <span class="text-rose-600">*</span></label>
                        <textarea wire:model="instructions" rows="6" placeholder="Pasos para resolver el problema, en orden..."
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Archivo (opcional)</label>
                        <input wire:model="file" type="file"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm file:mr-3 file:rounded-full file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
                        <p class="mt-1 text-xs text-gray-400">Cualquier tipo de archivo (instalador, driver, PDF, etc.). Maximo 20 MB.</p>
                        @if ($editingId && ! $file)
                            @php($existingGuide = \App\Models\PrinterGuide::find($editingId))
                            @if ($existingGuide?->path)
                                <p class="mt-1 text-xs text-gray-500">Ya tiene un archivo cargado ({{ $existingGuide->original_filename }}). Sube uno nuevo para reemplazarlo.</p>
                            @endif
                        @endif
                        <div wire:loading wire:target="file" class="mt-1 text-xs text-blue-600">Subiendo archivo...</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Estado <span class="text-rose-600">*</span></label>
                        <select wire:model="status"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal"
                        class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button wire:click="save" wire:loading.attr="disabled" wire:target="save,file"
                        class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60">
                        {{-- El input de archivo sube el temp file en una peticion aparte
                             (wire:target="file") antes de que exista un $this->file real;
                             sin deshabilitar aqui tambien por ese target, se podia dar
                             clic en "Guardar" mientras seguia subiendo y la guia se
                             guardaba sin el archivo (que llegaba null en save()). --}}
                        <span wire:loading.remove wire:target="save,file">{{ $editingId ? 'Actualizar' : 'Crear guia' }}</span>
                        <span wire:loading wire:target="save">Guardando...</span>
                        <span wire:loading wire:target="file">Subiendo archivo...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
