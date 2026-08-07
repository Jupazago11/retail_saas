<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-stone-900">Parámetros</h1>
        </div>
        <button wire:click="save"
            class="rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
            Guardar cambios
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Datos bancarios --}}
        <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Transferencia</p>
            <h2 class="mt-1 text-lg font-black text-stone-900">Datos bancarios</h2>
            <p class="mt-1 text-xs text-stone-400">Se muestran en la página de espera de pago de los clientes.</p>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Banco</label>
                    <input wire:model="bankName" type="text" placeholder="Bancolombia"
                        class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    @error('bankName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Tipo de cuenta</label>
                    <select wire:model="bankType"
                        class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="Cuenta de ahorros">Cuenta de ahorros</option>
                        <option value="Cuenta corriente">Cuenta corriente</option>
                        <option value="Nequi">Nequi</option>
                        <option value="Daviplata">Daviplata</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Número de cuenta</label>
                    <input wire:model="bankAccount" type="text" placeholder="123-456789-12"
                        class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    @error('bankAccount') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Titular</label>
                    <input wire:model="bankHolder" type="text" placeholder="Mi Empresa SAS"
                        class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    @error('bankHolder') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">NIT titular</label>
                    <input wire:model="bankNit" type="text" placeholder="900.123.456-7"
                        class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    @error('bankNit') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Contacto y precios --}}
        <div class="space-y-5">
            <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Comercial</p>
                <h2 class="mt-1 text-lg font-black text-stone-900">Precio y contacto</h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Precio del plan (texto)</label>
                        <input wire:model="planPrice" type="text" placeholder="$120.000 COP/mes"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <p class="mt-1 text-xs text-stone-400">Texto libre — se muestra en la página de pago.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Email de soporte</label>
                        <input wire:model="contactEmail" type="email" placeholder="soporte@miempresa.com"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('contactEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Teléfono / WhatsApp</label>
                        <input wire:model="contactPhone" type="text" placeholder="+57 310 000 0000"
                            class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @error('contactPhone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 ring-1 ring-stone-200">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">General</p>
                <h2 class="mt-1 text-lg font-black text-stone-900">Aplicación</h2>

                <div class="mt-5">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-stone-500">Nombre de la plataforma</label>
                    <input wire:model="appName" type="text" placeholder="Retail SaaS"
                        class="mt-1 w-full rounded-2xl border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    @error('appName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

    </div>

</div>
