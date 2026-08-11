<div class="py-12">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 sm:px-6 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">

        {{-- Lista de empresas --}}
        <section class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-100">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Workspace</p>
                    <h3 class="mt-1 text-xl font-black text-gray-900">Empresas vinculadas</h3>
                    <p class="mt-1 text-sm text-gray-500">La operacion del sistema siempre se ejecuta dentro de una empresa activa.</p>
                </div>
                <x-status-badge color="amber" class="uppercase tracking-[0.18em]">
                    {{ $companies->count() }} registradas
                </x-status-badge>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($companies as $company)
                    <article class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 transition hover:border-gray-300 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h4 class="text-base font-semibold text-gray-900">{{ $company->display_name }}</h4>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $company->legal_name }}</p>
                            @if ($company->tax_id)
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-gray-400">NIT {{ $company->tax_id }}</p>
                            @endif
                        </div>

                        <button
                            wire:click="switchCompany({{ $company->id }})"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                        >
                            Usar empresa
                        </button>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center">
                        <p class="text-sm text-gray-400">No tienes empresas vinculadas.</p>
                        <p class="mt-1 text-sm text-gray-400">Crea la primera para acceder al menú principal.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Formulario crear empresa --}}
        <section class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-700">Nuevo</p>
            <h3 class="mt-1 text-xl font-black text-gray-900">Crear empresa</h3>
            <p class="mt-1 text-sm text-gray-500">Provisiona automaticamente la sucursal, bodega y caja principal.</p>

            <form wire:submit="createCompany" class="mt-6 space-y-4">
                <div>
                    <x-input-label for="legal_name" value="Razon social" />
                    <x-text-input wire:model="legalName" id="legal_name" name="legal_name" type="text"
                        class="mt-1 block w-full" required />
                    <x-input-error class="mt-2" :messages="$errors->get('legalName')" />
                </div>

                <div>
                    <x-input-label for="display_name" value="Nombre comercial" />
                    <x-text-input wire:model="displayName" id="display_name" name="display_name" type="text"
                        class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('displayName')" />
                </div>

                <div>
                    <x-input-label for="tax_id" value="NIT" />
                    <x-text-input wire:model="taxId" id="tax_id" name="tax_id" type="text"
                        class="mt-1 block w-full" />
                    <x-input-error class="mt-2" :messages="$errors->get('taxId')" />
                </div>

                <button type="submit"
                    class="w-full justify-center rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                    Crear y continuar
                </button>
            </form>
        </section>

    </div>
</div>
