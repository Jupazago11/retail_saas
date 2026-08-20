<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Plataforma</p>
            <h1 class="mt-1 text-2xl font-black text-gray-900">Parámetros</h1>
        </div>
        <button wire:click="save"
            class="rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
            Guardar cambios
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Datos bancarios --}}
        <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Transferencia</p>
            <h2 class="mt-1 text-lg font-black text-gray-900">Datos bancarios</h2>
            <p class="mt-1 text-xs text-gray-400">Se muestran en la página de espera de pago de los clientes.</p>

            <div class="mt-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Banco</label>
                    <input wire:model="bankName" type="text" placeholder="Bancolombia"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @error('bankName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo de cuenta</label>
                    <select wire:model="bankType"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <option value="Cuenta de ahorros">Cuenta de ahorros</option>
                        <option value="Cuenta corriente">Cuenta corriente</option>
                        <option value="Nequi">Nequi</option>
                        <option value="Daviplata">Daviplata</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Número de cuenta</label>
                    <input wire:model="bankAccount" type="text" placeholder="123-456789-12"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @error('bankAccount') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Titular</label>
                    <input wire:model="bankHolder" type="text" placeholder="Mi Empresa SAS"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @error('bankHolder') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">NIT titular</label>
                    <input wire:model="bankNit" type="text" placeholder="900.123.456-7"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @error('bankNit') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Contacto y precios --}}
        <div class="space-y-5">
            <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Comercial</p>
                <h2 class="mt-1 text-lg font-black text-gray-900">Precio y contacto</h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Precio del plan (texto)</label>
                        <input wire:model="planPrice" type="text" placeholder="$120.000 COP/mes"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        <p class="mt-1 text-xs text-gray-400">Texto libre — se muestra en la página de pago.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Email de soporte</label>
                        <input wire:model="contactEmail" type="email" placeholder="soporte@miempresa.com"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('contactEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Teléfono / WhatsApp</label>
                        <input wire:model="contactPhone" type="text" placeholder="+57 310 000 0000"
                            class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @error('contactPhone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 ring-1 ring-gray-200">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">General</p>
                <h2 class="mt-1 text-lg font-black text-gray-900">Aplicación</h2>

                <div class="mt-5">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Logo</label>

                    @unless ($this->isLogoStorageConfigured())
                        <div class="mt-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-700 ring-1 ring-amber-200">
                            El bucket de Cloudflare R2 todavia no esta configurado (variables <code>R2_*</code> en el <code>.env</code>). En cuanto llenes esas variables, esta seccion empieza a funcionar sin tocar nada mas.
                        </div>
                    @endunless

                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-200">
                            @if ($newLogo)
                                <img src="{{ $newLogo->temporaryUrl() }}" alt="Vista previa" class="h-full w-full object-contain">
                            @else
                                <x-application-logo class="h-10 w-10 object-contain" />
                            @endif
                        </div>

                        <div class="flex-1">
                            <input type="file" wire:model="newLogo" accept="image/*"
                                {{ $this->isLogoStorageConfigured() ? '' : 'disabled' }}
                                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-blue-700 hover:file:bg-blue-100 disabled:opacity-50">
                            <p class="mt-1 text-xs text-gray-400">
                                Imagen cuadrada (ej. 512x512px). Si no subes nada, o si eliminas el que tenias, se usa el logo por defecto.
                            </p>
                            @error('newLogo') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                            <div class="mt-2 flex gap-2">
                                @if ($newLogo)
                                    <button type="button" wire:click="uploadLogo" wire:loading.attr="disabled" wire:target="uploadLogo"
                                        class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="uploadLogo">Guardar logo</span>
                                        <span wire:loading wire:target="uploadLogo">Subiendo...</span>
                                    </button>
                                @endif
                                @if ($currentLogoUrl)
                                    <button type="button" wire:click="removeLogo" wire:confirm="¿Quitar el logo actual? Se volvera a usar el logo por defecto."
                                        class="rounded-full border border-gray-300 px-4 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-rose-300 hover:text-rose-600">
                                        Quitar logo
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre de la plataforma</label>
                    <input wire:model="appName" type="text" placeholder="{{ \App\Models\PlatformSetting::appName() }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    @error('appName') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Correo para notificaciones de nuevas cuentas</label>
                    <input wire:model="ownerNotificationEmail" type="email" placeholder="jupazago11@gmail.com"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                    <p class="mt-1 text-xs text-gray-400">Recibe un aviso cada vez que alguien crea una cuenta nueva.</p>
                    @error('ownerNotificationEmail') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

    </div>

</div>
