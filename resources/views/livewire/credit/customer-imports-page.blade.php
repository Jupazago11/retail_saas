<div class="py-10">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-6 lg:grid-cols-[1.02fr_0.98fr]">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Carga CSV</p>
                <h3 class="mt-2 text-2xl font-black text-stone-900">Importador de clientes</h3>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    Encabezado minimo: <span class="font-semibold text-stone-800">first_name</span>.
                    Columnas opcionales: document_type, document_number, last_name, phone, email, status, credit_enabled, credit_limit y loyalty_enabled.
                </p>

                <form wire:submit="import" class="mt-6 space-y-4">
                    <div>
                        <label for="customer-import-file" class="text-sm font-medium text-stone-700">Archivo CSV</label>
                        <input wire:model="importFile" id="customer-import-file" type="file" accept=".csv,text/csv,.txt" class="mt-1 block w-full rounded-2xl border-stone-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('importFile') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="inline-flex rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white">
                        Procesar importacion
                    </button>
                </form>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Plantilla</p>
                <h3 class="mt-2 text-2xl font-black text-stone-900">Ejemplo rapido</h3>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-950 p-4 text-xs leading-6 text-stone-100">first_name,last_name,document_type,document_number,phone,email,status,credit_enabled,credit_limit,loyalty_enabled
Maria,Lopez,CC,123456789,3001234567,maria@demo.com,active,true,150000,false
Carlos,Perez,NIT,900333444,,carlos@demo.com,active,false,0,true</pre>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-amber-700">Resultado</p>
                    <h3 class="mt-2 text-2xl font-black text-stone-900">Ultimo lote procesado</h3>
                </div>

                @if ($summary['file_name'])
                    <span class="rounded-full border border-stone-200 px-3 py-1 text-xs font-semibold text-stone-600">
                        {{ $summary['file_name'] }}
                    </span>
                @endif
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Creados</p>
                    <p class="mt-2 text-3xl font-black text-emerald-900">{{ $summary['created_count'] }}</p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Errores</p>
                    <p class="mt-2 text-3xl font-black text-amber-900">{{ $summary['error_count'] }}</p>
                </div>
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
