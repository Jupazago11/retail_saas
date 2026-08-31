@php
    use App\Enums\SaleStatus;
    use App\Support\Money;

    $totals = $preview['totals'] ?? [];
    $promotions = $preview['promotions'] ?? [];
    $selectedCustomer = $this->selectedCustomer();
    $grandTotal = Money::format((float) ($totals['grand_total'] ?? 0));
    $pointsEquivalent = Money::format((float) $this->loyaltyCashEquivalentPreview());
    // Restante/Cambio ya no se calculan aqui: se mueven a JS (posPaymentSummary
    // en el <script> de abajo) para que se actualicen en vivo mientras se
    // escribe, no solo al perder el foco. Ver el modal de Cobro.
    $productLookupOptions = $products
        ->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
        ])
        ->values();
    $customerLookupOptions = $customers
        ->map(fn ($c) => [
            'id'       => $c->id,
            'name'     => $c->person?->full_name ?? 'Cliente #'.$c->id,
            'document' => $c->person?->document_number ?? '',
        ])
        ->values()
        ->all();
    $promotionNames = collect($promotions)
        ->map(fn ($promotion) => data_get($promotion, 'promotion_name') ?? data_get($promotion, 'name'))
        ->filter()
        ->implode(' Â· ');
    $showRegisterSelector = $cashRegisters->count() > 1;
@endphp

<div>
<style>
    [x-cloak] {
        display: none !important;
    }
</style>

<script>
    function posProductSearch() {
        return {
            search: '',
            open: false,
            highlighted: -1,
            catalog: @json($productLookupOptions),
            get results() {
                const q = this.search.trim().toLowerCase();
                if (q.length < 1) return [];
                return this.catalog.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.barcode && p.barcode.toLowerCase().includes(q)) ||
                    (p.sku && p.sku.toLowerCase().includes(q))
                ).slice(0, 8);
            },
            _dispatchTerm(term) {
                const input = document.getElementById('pos-product-lookup');
                console.log('[pos:alpine] _dispatchTerm → term:', term, '| input encontrado:', !!input);
                if (!input) return;
                input.value = term;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
            addProduct(p) {
                const term = p.barcode || p.sku || p.name;
                console.log('[pos:alpine] addProduct → term:', term, '| delegando a app.js via change event');
                this.search = '';
                this.open = false;
                this.highlighted = -1;
                this._dispatchTerm(term);
            },
            onEnter() {
                if (this.highlighted >= 0 && this.results[this.highlighted]) {
                    this.addProduct(this.results[this.highlighted]);
                    return;
                }
                const q = this.search.trim();
                if (!q) return;
                const qLow = q.toLowerCase();
                const exact = this.catalog.find(p =>
                    (p.barcode && p.barcode.toLowerCase() === qLow) ||
                    (p.sku && p.sku.toLowerCase() === qLow) ||
                    p.name.toLowerCase() === qLow
                );
                if (exact) { this.addProduct(exact); return; }
                if (this.results.length > 0) { this.addProduct(this.results[0]); return; }
                // Sin coincidencia: limpia estado; el keydown de app.js maneja el call a Livewire
                console.log('[pos:alpine] onEnter → sin match, app.js keydown tomará control');
                this.search = '';
                this.open = false;
            },
            onDown() { this.open = true; this.highlighted = Math.min(this.highlighted + 1, this.results.length - 1); },
            onUp() { this.highlighted = Math.max(this.highlighted - 1, -1); },
            onInput() { this.highlighted = -1; this.open = this.search.length > 0; },
            init() {
                console.log('[pos:alpine] init → catalog.length:', this.catalog.length);
                window.addEventListener('pos-product-selected', (e) => {
                    console.log('[pos:alpine] pos-product-selected recibido:', e.detail);
                    document.getElementById('pos-product-lookup')?.focus();
                });
            }
        };
    }

    function posCustomerSearch() {
        return {
            search: '',
            open: false,
            highlighted: -1,
            catalog: @json($customerLookupOptions),
            get results() {
                var q = this.search.trim().toLowerCase();
                if (q.length < 1) return [];
                return this.catalog.filter(function(c) {
                    return c.name.toLowerCase().includes(q) || c.document.toLowerCase().includes(q);
                }).slice(0, 8);
            },
            onInput: function() {
                this.highlighted = -1;
                this.open = this.search.length > 0;
            },
            onDown: function() {
                var total = this.results.length + (this.search.length > 0 && this.results.length === 0 ? 1 : 0);
                this.highlighted = Math.min(this.highlighted + 1, Math.max(total - 1, 0));
                this.open = true;
            },
            onUp: function() { this.highlighted = Math.max(this.highlighted - 1, -1); },
            select: function(c) {
                this.search = '';
                this.open = false;
                this.highlighted = -1;
                this.$wire.call('selectPaymentCustomer', c.id);
            },
            createNew: function() {
                var doc = this.search.trim();
                if (!doc) return;
                this.search = '';
                this.open = false;
                this.$wire.call('setNewPaymentCustomerDoc', doc);
            }
        };
    }

    function paymentAmount(initialValue, paymentIndex) {
        return {
            idx: paymentIndex,
            raw: initialValue ? String(initialValue) : '',
            fmt: function(n) {
                var num = parseFloat(String(n).replace(/[^\d.]/g, ''));
                if (!n || isNaN(num)) return '';
                return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(num);
            },
            init: function() {
                this.$refs.input.value = this.raw ? this.fmt(this.raw) : '';
            },
            onFocus: function() {
                var self = this;
                this.$nextTick(function() { self.$refs.input.select(); });
            },
            onInput: function() {
                var input = this.$refs.input;
                var digitsBeforeCursor = input.value.slice(0, input.selectionStart).replace(/[^\d]/g, '').length;

                var raw = input.value.replace(/[^\d]/g, '');
                input.value = raw ? this.fmt(raw) : '';

                var pos = 0, digitsSeen = 0;
                while (pos < input.value.length && digitsSeen < digitsBeforeCursor) {
                    if (/\d/.test(input.value[pos])) digitsSeen++;
                    pos++;
                }
                input.setSelectionRange(pos, pos);

                // live:false (diferido): actualiza el valor reactivo que lee
                // posPaymentSummary().remaining() en cada tecla, sin pegarle
                // al servidor por cada digito — el envio real ocurre en la
                // proxima accion de Livewire (cambiar otra linea, Confirmar...).
                this.raw = raw;
                this.$wire.set('payments.' + this.idx + '.amount', raw || '', false);
            },
            onBlur: function() {
                var raw = this.$refs.input.value.replace(/[^\d]/g, '');
                this.raw = raw;
                this.$refs.input.value = raw ? this.fmt(raw) : '';
                this.$wire.set('payments.' + this.idx + '.amount', raw || '', false);
            }
        };
    }

    // Restante/Cambio del modal de Cobro, en vivo mientras se escribe — lee
    // $wire.payments (actualizado por paymentAmount().onInput arriba) en vez
    // de esperar un commit al servidor para recalcular.
    function posPaymentSummary(total) {
        return {
            total: total,
            fmt: function(n) {
                return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(Math.round(n));
            },
            remaining: function() {
                // Guard: al confirmar la venta, el modal se cierra y
                // resetSaleForm() reemplaza $wire.payments en el mismo ciclo
                // — Alpine puede llegar a evaluar esta expresion una ultima
                // vez durante esa transicion, con $wire.payments todavia no
                // disponible como array.
                var payments = Array.isArray(this.$wire.payments) ? this.$wire.payments : [];
                var entered = payments.reduce(function(sum, p) {
                    var raw = String(p.amount == null ? '' : p.amount).replace(/[^\d.-]/g, '');
                    var n = parseFloat(raw);
                    return sum + (isNaN(n) ? 0 : n);
                }, 0);

                return this.total - entered;
            }
        };
    }
</script>

<script>
    (function () {
        // Este <script> se vuelve a ejecutar cada vez que se navega aqui con
        // wire:navigate (Livewire clona los <script> del body en cada visita
        // SPA) — pero 'livewire:initialized' solo se dispara UNA vez en toda
        // la sesion del navegador (cuando Livewire arranca). Si el usuario
        // llega a /sales/pos por SPA (no con un F5), ese evento ya paso hace
        // rato y el listener de abajo nunca se ejecutaba: el ticket dejaba de
        // abrirse solo despues de la primera carga dura de la pagina.
        if (window.__posPageScriptInitialized) return;
        window.__posPageScriptInitialized = true;

        function init() {
        var POS = {
            log: function() { var a = ['[POS]'].concat(Array.from(arguments)); console.log.apply(console, a); },
            err: function() { var a = ['[POS ERR]'].concat(Array.from(arguments)); console.error.apply(console, a); }
        };

        Livewire.hook('request', function(ctx) {
            var methods = (ctx.payload && ctx.payload.calls) ? ctx.payload.calls.map(function(c){ return c.method; }) : [];
            POS.log('request →', methods);
            var isSaveSale = methods.indexOf('saveSale') !== -1;

            // Si saveSale no confirma la venta (ej. "selecciona un cliente
            // para credito"), nunca se dispara open-sale-ticket y la pestaña
            // en blanco abierta al hacer click (ver x-on:click del boton
            // "Confirmar y cobrar") se queda huerfana. El timeout deja que
            // open-sale-ticket corra primero y anule __posTicketTab si la
            // venta si se confirmo.
            function closeOrphanTicketTab() {
                setTimeout(function () {
                    if (window.__posTicketTab && !window.__posTicketTab.closed) {
                        window.__posTicketTab.close();
                        window.__posTicketTab = null;
                    }
                }, 150);
            }

            ctx.succeed(function(resp) {
                POS.log('response OK', resp.status);
                if (isSaveSale) closeOrphanTicketTab();
            });
            ctx.fail(function(resp) {
                POS.err('response FAIL', resp.status, resp.content && resp.content.substring(0,200));
                if (isSaveSale) closeOrphanTicketTab();
            });
        });

        Livewire.on('pos-debug', function(data) { POS.log('pos-debug:', JSON.stringify(data)); });

        document.addEventListener('click', function(e) {
            if (e.target.closest('[wire\\:click="openPaymentModal"]')) {
                POS.log('Cobrar click → openPaymentModal');
            }
            if (e.target.closest('[wire\\:click="saveSale"]')) {
                POS.log('saveSale click');
            }
        });

        var obs = new MutationObserver(function(muts) {
            muts.forEach(function(m) {
                m.addedNodes.forEach(function(n) {
                    if (n.nodeType === 1 && n.querySelector) {
                        try { if (n.querySelector('[wire\\:click="closePaymentModal"]')) POS.log('Modal ENTRO al DOM'); } catch(ex) {}
                    }
                });
                m.removedNodes.forEach(function(n) {
                    if (n.nodeType === 1 && n.querySelector) {
                        try { if (n.querySelector('[wire\\:click="closePaymentModal"]')) POS.log('Modal SALIO del DOM'); } catch(ex) {}
                    }
                });
            });
        });
        obs.observe(document.body, { childList: true, subtree: true });

        // Al confirmar una venta (Cobrar), el servidor manda la URL del
        // ticket y aqui se abre sola en una pestaña nueva. El boton
        // "Confirmar y cobrar" ya abrio una pestaña en blanco AL HACER CLICK
        // (window.__posTicketTab, ver el x-on:click del boton) — hay que
        // reusarla en vez de llamar window.open() aqui: para entonces ya
        // paso la respuesta async de Livewire, y la mayoria de navegadores
        // bloquean un window.open() que no ocurre dentro del gesto de click
        // original.
        Livewire.on('open-sale-ticket', function (data) {
            if (!data || !data.url) return;

            if (window.__posTicketTab && !window.__posTicketTab.closed) {
                window.__posTicketTab.location.href = data.url;
            } else {
                window.open(data.url, '_blank', 'noopener');
            }

            window.__posTicketTab = null;
        });

        POS.log('Debug hooks OK');
        }

        if (window.Livewire) {
            init();
        } else {
            document.addEventListener('livewire:initialized', init);
        }
    })();
</script>


<div class="pb-10">
    <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
        {{-- Sin <x-sales-nav>: las pestañas Ventas/POS las dibuja el
        componente contenedor (sales-workspace-page.blade.php) una sola vez. --}}

        @if (! $canCreateSales)
            <section class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $salesRequirementsMessage }}
            </section>
        @endif

        <div class="flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200" data-pos-shell>

            {{-- â•â• TOP: referencia de operacion + caja + toolbar â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50/70 px-3 py-2.5 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="w-full sm:w-56">
                        <label for="pos-audit" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Operacion</label>
                        <input
                            id="pos-audit"
                            type="text"
                            value="{{ $auditReference }}"
                            readonly
                            class="h-9 w-full rounded-lg border-gray-200 bg-gray-100 px-3 font-mono text-xs font-semibold tracking-wide text-gray-600 shadow-sm"
                        >
                    </div>

                    @if ($showRegisterSelector)
                        <div class="w-full sm:w-44">
                            <label for="pos-cash-register" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Caja</label>
                            <select
                                id="pos-cash-register"
                                wire:model.live="cashRegisterId"
                                class="h-9 w-full rounded-lg border-gray-300 bg-white px-2 text-sm font-medium text-gray-800 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                            >
                                @foreach ($cashRegisters as $register)
                                    <option value="{{ $register->id }}">{{ $register->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                {{-- Toolbar --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    {{-- Cobrar --}}
                    <button type="button" wire:click="openPaymentModal" title="Cobrar"
                        class="relative inline-flex h-10 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386a1.5 1.5 0 0 1 1.455 1.136L5.4 5.25m0 0h13.95l-1.5 7.5H6.75m-1.35-7.5L6.75 12.75m0 0-1.125 5.625A1.5 1.5 0 0 0 7.095 20.25H18M9 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm8.25 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                        Cobrar
                        <span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-white"></span>
                    </button>

                    {{-- Ticket ultima venta --}}
                    @if ($latestSale)
                        <a href="{{ route('sales.ticket', $latestSale) }}" target="_blank" rel="noopener noreferrer" title="Ticket ultima venta"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25h-10.5A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18v-2.25m-6-3h7.5m0 0-3-3m3 3-3 3"/></svg>
                            <span class="sr-only">Ticket ultima venta</span>
                        </a>
                    @else
                        <div class="inline-flex h-10 w-10 cursor-default items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-gray-300" title="Sin ventas aun">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25h-10.5A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18v-2.25m-6-3h7.5m0 0-3-3m3 3-3 3"/></svg>
                        </div>
                    @endif

                    {{-- Congelar venta actual (no aplica modificando una venta ya
                    confirmada: ahi solo hay confirmar el cambio o cancelar) --}}
                    @if ($this->canViewFrozenSales() && ! $modifyingSaleId)
                        <button type="button" wire:click="freezeCurrentSale" title="Congelar venta actual"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
                            {{-- cubo de hielo: cube-transparent Heroicon --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                            <span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white"></span>
                        </button>
                    @endif

                    {{-- Congeladas (modal) --}}
                    @if ($this->canViewFrozenSales())
                        <button type="button" wire:click="openFrozenSalesModal" title="Ventas congeladas"
                            class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
                            {{-- cubo de hielo pequeño + flecha hacia abajo --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 8.5l-6-3.5-6 3.5m12 0-6 3.25m6-3.25v5.9l-6 3.5M6 8.5l6 3.25M6 8.5v5.9l6 3.5m0-5.9v5.9"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v2.5m0 0-1.5-1.5m1.5 1.5 1.5-1.5"/>
                            </svg>
                            <span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white"></span>
                        </button>
                    @endif

                    {{-- Historial: cambia de pestaña en el mismo contenedor, sin
                    navegar (POS sigue montado con el carrito intacto). --}}
                    <button type="button" x-on:click="$dispatch('switch-sales-tab', { tab: 'history' })" title="Historial de ventas"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-blue-300 hover:text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M3 9.75h18M3 15h18M3 20.25h18"/></svg>
                        <span class="sr-only">Historial de ventas</span>
                    </button>
                </div>
            </div>

            {{-- â•â• TABLE: area principal de lineas â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
            <div class="max-h-[42vh] min-h-[220px] overflow-y-auto border-b border-gray-200">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50">
                        <tr class="text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                            <th class="w-px whitespace-nowrap px-2 py-2">Codigo</th>
                            <th class="px-2 py-2">Producto</th>
                            <th class="w-px whitespace-nowrap px-2 py-2 text-right">Cant.</th>
                            <th class="w-px whitespace-nowrap px-2 py-2 text-right">Precio</th>
                            <th class="w-px min-w-[104px] whitespace-nowrap px-2 py-2 text-center">Lista</th>
                            <th class="w-px whitespace-nowrap px-2 py-2 text-right">Total</th>
                            <th class="w-px whitespace-nowrap px-2 py-2 text-center">Acc.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php $hasProducts = collect($items)->contains(fn($i) => !blank($i['product_id'] ?? null)); @endphp
                        @if (! $hasProducts)
                            <tr>
                                <td colspan="7" class="py-24 text-center text-sm text-gray-400">
                                    Escanea o busca un producto para iniciar la venta.
                                </td>
                            </tr>
                        @else
                            @foreach ($items as $index => $item)
                                @continue(blank($item['product_id'] ?? null))
                                @php
                                    $product = $products->firstWhere('id', (int) ($item['product_id'] ?? 0));
                                    $lineKey = (int) ($item['_key'] ?? $index);
                                    $priceTierOptions = $this->linePriceTierOptions($item);
                                    $lineTotal = Money::format((float) bcmul((string) ($item['quantity'] ?? '0'), (string) ($item['unit_price'] ?? '0'), 2));
                                    $barcode = $product?->barcode ?: $product?->sku ?: '---';
                                @endphp
                                <tr wire:key="pos-line-{{ $lineKey }}" class="even:bg-gray-50">
                                    <td class="px-2 py-2 align-middle text-xs text-gray-500">{{ $barcode }}</td>
                                    <td class="px-2 py-2 align-middle">
                                        <div class="font-semibold text-gray-900">{{ $product?->name ?? '—' }}</div>
                                        @if ($product?->flexible_price)
                                            <span class="mt-1 inline-block rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">Precio libre</span>
                                        @elseif ($product?->presentations?->isNotEmpty())
                                            <select wire:model.live="items.{{ $index }}.product_presentation_id" class="mt-1 h-7 w-full rounded-md border-gray-200 bg-white px-2 text-[11px] text-gray-700 focus:border-blue-600 focus:ring-blue-600">
                                                <option value="">Presentacion base</option>
                                                @foreach ($product->presentations as $presentation)
                                                    <option value="{{ $presentation->id }}">{{ $presentation->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </td>
                                    <td class="px-1.5 py-2 align-middle">
                                        @if ($product?->flexible_price)
                                            <div class="text-center text-sm text-gray-400">—</div>
                                        @else
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" wire:click="decreaseQuantity({{ $lineKey }})" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-gray-300 text-base font-bold text-gray-500 transition hover:border-blue-400 hover:text-blue-600">−</button>
                                                <input type="text" wire:model.live="items.{{ $index }}.quantity" class="h-8 w-14 min-w-0 rounded-md border-gray-300 bg-white px-1 text-center text-sm text-gray-900 focus:border-blue-600 focus:ring-blue-600">
                                                <button type="button" wire:click="increaseQuantity({{ $lineKey }})" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-gray-300 text-base font-bold text-gray-500 transition hover:border-blue-400 hover:text-blue-600">+</button>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 align-middle text-right text-sm font-semibold text-gray-900">
                                        @if ($product?->flexible_price)
                                            {{-- Con precio flexible no hay cantidad x precio unitario: el
                                                 cajero escribe directamente el total de esta venta. --}}
                                            <input type="text" inputmode="numeric" wire:model.live="items.{{ $index }}.unit_price"
                                                placeholder="Total"
                                                class="h-8 w-24 rounded-md border-amber-300 bg-amber-50 px-2 text-right text-sm font-semibold text-gray-900 focus:border-amber-500 focus:ring-amber-500">
                                        @else
                                            {{ Money::format((float) ($item['unit_price'] ?? 0)) }}
                                        @endif
                                    </td>
                                    <td class="px-1 py-2 align-middle">
                                        @if ($product?->flexible_price)
                                            <span class="block text-center text-[11px] text-gray-400">Libre</span>
                                        @elseif (count($priceTierOptions) > 1)
                                            <div class="grid grid-cols-3 gap-1">
                                                @foreach ($priceTierOptions as $tier => $option)
                                                    <button
                                                        type="button"
                                                        wire:click="switchPriceTier({{ $lineKey }}, '{{ $tier }}')"
                                                        class="h-7 rounded-md border text-[11px] font-bold transition {{ ($item['price_tier'] ?? 'price_1') === $tier ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}"
                                                    >
                                                        {{ $option['label'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <button type="button" wire:click="cyclePriceTier({{ $lineKey }})" class="h-7 w-full rounded-md border border-gray-200 bg-gray-50 text-[11px] font-bold text-gray-600 transition hover:bg-gray-100">
                                                {{ strtoupper(str_replace('price_', 'V', (string) ($item['price_tier'] ?? 'price_1'))) }}
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 align-middle text-right text-sm font-bold text-gray-900">{{ $lineTotal }}</td>
                                    <td class="px-1 py-2 align-middle text-center">
                                        <button type="button" wire:click="removeItemLine({{ $lineKey }})" class="text-gray-400 transition hover:text-rose-600" title="Eliminar linea">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7.5h12m-9.75 0V6a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 .75.75v1.5m-8.25 0v10.125A1.875 1.875 0 0 0 9.375 19.5h5.25a1.875 1.875 0 0 0 1.875-1.875V7.5"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- FIDELIZACION (solo si aplica) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
            @if ($this->canRedeemLoyalty() || $selectedCustomer || $customerId || $loyaltyPointsToRedeem !== '')
                <div class="border-b border-gray-200 bg-gray-50/70 px-3 py-2">
                    <div class="grid gap-2 sm:grid-cols-[1fr_140px_140px]">
                        <div class="rounded-lg bg-white p-2 ring-1 ring-gray-200">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Cliente</span>
                            <span class="mt-0.5 block truncate text-sm font-semibold text-gray-900">{{ $selectedCustomer?->person?->full_name ?? 'Cliente fidelizado' }}</span>
                        </div>
                        <div class="rounded-lg bg-white p-2 ring-1 ring-gray-200">
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Redencion</label>
                            <input type="text" wire:model.live="loyaltyPointsToRedeem" class="mt-0.5 h-7 w-full rounded-md border-gray-200 bg-white px-2 text-right text-sm text-gray-900 focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        <div class="rounded-lg bg-white p-2 ring-1 ring-gray-200">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Equivalente</span>
                            <span class="mt-0.5 block text-right text-sm font-semibold text-gray-900">{{ $pointsEquivalent }}</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- â•â• PROMOCIONES â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
            @if ($promotions !== [])
                <div class="border-b border-gray-200 bg-blue-50 px-3 py-1.5 text-xs text-blue-800">
                    <span class="font-semibold">Promociones:</span> {{ $promotionNames }}
                </div>
            @endif

            {{-- â•â• BOTTOM STRIP: captura + indicadores + totales + info â•â•â•â•â•â•â•â• --}}
            <div class="bg-gray-50/70 px-3 py-3">
                <div class="grid gap-2 lg:grid-cols-[minmax(0,1fr)_220px_220px]">

                    {{-- Seccion 1: Busqueda de producto --}}
                    <div x-data="posProductSearch()" class="relative">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Producto</label>
                        <input
                            id="pos-product-lookup"
                            type="text"
                            x-model="search"
                            @input="onInput()"
                            @keydown.enter.prevent="onEnter()"
                            @keydown.arrow-down.prevent="onDown()"
                            @keydown.arrow-up.prevent="onUp()"
                            @blur="setTimeout(() => { open = false; highlighted = -1; }, 180)"
                            @focus="open = search.length > 0 && results.length > 0"
                            data-pos-product-input
                            name="product_lookup"
                            autocomplete="off"
                            class="h-10 w-full rounded-lg border-gray-300 bg-white px-3 text-sm font-medium text-gray-900 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                            placeholder="Codigo de barras o nombre"
                        >
                        <ul
                            x-show="open && results.length > 0"
                            x-cloak
                            class="absolute bottom-full left-0 right-0 z-20 mb-1 max-h-56 overflow-y-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-200"
                        >
                            <template x-for="(p, i) in results" :key="p.id">
                                <li
                                    @mousedown.prevent="addProduct(p)"
                                    :class="{ 'bg-blue-50': i === highlighted }"
                                    class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm transition hover:bg-blue-50"
                                >
                                    <span class="truncate font-medium text-gray-900" x-text="p.name"></span>
                                    <span class="shrink-0 font-mono text-xs text-gray-400" x-text="p.barcode || p.sku || ''"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Total a cobrar --}}
                    <div class="flex flex-col justify-between rounded-lg bg-blue-50 p-2.5 ring-1 ring-blue-100">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-blue-700">Total a cobrar</span>
                        <span class="text-right text-2xl font-black text-blue-700">{{ $grandTotal }}</span>
                    </div>

                    {{-- Estado + Info --}}
                    <div class="space-y-1.5">
                        <button
                            type="button"
                            wire:click="$set('saleStatus', '{{ $saleStatus === SaleStatus::Confirmed->value ? SaleStatus::Draft->value : SaleStatus::Confirmed->value }}')"
                            class="flex w-full items-center justify-between rounded-lg bg-white px-2.5 py-1.5 text-left ring-1 ring-gray-200 transition hover:bg-gray-50"
                            title="Cambiar estado"
                        >
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Estado</span>
                            <span class="text-xs font-bold {{ $saleStatus === SaleStatus::Confirmed->value ? 'text-emerald-700' : 'text-blue-700' }}">
                                {{ $saleStatus === SaleStatus::Confirmed->value ? 'CONTADO' : 'BORRADOR' }}
                            </span>
                        </button>
                        <div class="space-y-0.5 rounded-lg bg-white px-2.5 py-1.5 text-[10px] text-gray-500 ring-1 ring-gray-200">
                            <p class="font-semibold text-gray-700">{{ $this->cashierName() }}</p>
                            <p>{{ $this->terminalLabel() }}</p>
                            <p>{{ $this->workDateLabel() }}</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
{{-- /root closes at end, after modals --}}

{{-- Modal: cobro de venta --}}
@if ($showPaymentModal)
    {{-- Sin wire:click.self="closePaymentModal": un clic accidental fuera
    (zona gris) NO debe cerrar este modal y perder lo que se llevaba escrito
    — solo se cierra con la X o "Cancelar". --}}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);">
        <div class="flex w-full max-w-lg flex-col rounded-xl bg-white shadow-xl" style="max-height: 90vh;">
            {{-- Cabecera --}}
            <div class="flex flex-shrink-0 items-center justify-between border-b border-stone-100 px-5 py-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Cobro</p>
                    <h3 class="mt-0.5 text-lg font-black text-gray-900">Cobrar venta</h3>
                </div>
                <button wire:click="closePaymentModal" class="px-1 text-xl leading-none text-gray-400 hover:text-gray-700" title="Cerrar">&times;</button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                {{-- Cliente --}}
                <div>
                    <p class="mb-1.5 text-xs font-semibold text-gray-700">Cliente</p>

                    @if ($resolvedCustomerId || $paymentCustomerDocument !== '')
                        <div class="flex items-center gap-2">
                            <div class="flex flex-1 items-center gap-2 rounded-lg px-3 py-1.5 ring-1 ring-inset {{ $resolvedCustomerId ? 'bg-emerald-50 ring-emerald-200' : 'bg-blue-50 ring-blue-200' }}">
                                @if ($resolvedCustomerId)
                                    @php $rc = $customers->firstWhere('id', $resolvedCustomerId); @endphp
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span class="text-sm font-semibold text-emerald-800">{{ $resolvedCustomerName }}</span>
                                    @if ($rc?->person?->document_number)
                                        <span class="font-mono text-xs text-emerald-600 opacity-75">{{ $rc->person->document_number }}</span>
                                    @endif
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="text-sm font-semibold text-blue-700">Nuevo cliente</span>
                                    <span class="font-mono text-xs text-blue-600">{{ $paymentCustomerDocument }}</span>
                                @endif
                            </div>
                            <button type="button" wire:click="clearPaymentCustomer"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-lg leading-none text-gray-400 transition hover:border-rose-300 hover:text-rose-600">
                                &times;
                            </button>
                        </div>
                    @else
                        <div x-data="posCustomerSearch()" class="relative">
                            <input
                                type="text"
                                x-model="search"
                                x-on:input="onInput"
                                x-on:keydown.down.prevent="onDown"
                                x-on:keydown.up.prevent="onUp"
                                x-on:keydown.escape="open = false"
                                placeholder="Nombre o documento del cliente"
                                class="h-9 w-full rounded-lg border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm placeholder-gray-400 focus:border-blue-600 focus:ring-blue-600"
                                autocomplete="off"
                            >
                            <ul x-show="open" x-cloak
                                class="absolute left-0 right-0 top-full z-50 mt-1 max-h-48 overflow-y-auto rounded-lg bg-white shadow-lg ring-1 ring-gray-200">
                                <template x-for="(c, i) in results" :key="c.id">
                                    <li @mousedown.prevent="select(c)"
                                        :class="{ 'bg-blue-50': i === highlighted }"
                                        class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm transition hover:bg-blue-50">
                                        <span class="font-semibold text-gray-800" x-text="c.name"></span>
                                        <span class="font-mono text-xs text-gray-400" x-text="c.document"></span>
                                    </li>
                                </template>
                                <li x-show="search.length > 0 && results.length === 0"
                                    @mousedown.prevent="createNew"
                                    class="cursor-pointer px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                                    + Nuevo: <span class="font-mono" x-text="search"></span>
                                </li>
                            </ul>
                            <p class="mt-1 text-[11px] italic text-gray-400">Sin seleccion: se registrara como anonimo</p>
                        </div>
                    @endif
                </div>

                {{-- Fecha de venta: por defecto queda con el momento actual;
                el checkbox solo se marca para registrar una venta olvidada
                de un dia anterior. Solo visible para el dueño de la empresa
                o quien tenga el permiso sales.change_date. --}}
                @if ($this->canBackdateSale())
                    <div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                            <input type="checkbox" wire:model.live="backdateSale"
                                class="rounded border-gray-300 text-blue-600 focus:border-blue-600 focus:ring-blue-600">
                            Es de un dia anterior
                        </label>
                        @if ($backdateSale)
                            <input type="date" wire:model="soldAt" max="{{ now()->format('Y-m-d') }}"
                                class="mt-1.5 h-9 w-full rounded-lg border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm focus:border-blue-600 focus:ring-blue-600">
                        @else
                            <p class="mt-1 text-[11px] italic text-gray-400">Se registra con la fecha y hora actuales.</p>
                        @endif
                    </div>
                @endif

                {{-- Total a cobrar --}}
                <div class="rounded-lg bg-blue-50 px-4 py-3 ring-1 ring-blue-100">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-blue-700">Total a cobrar</span>
                        <span class="text-2xl font-black text-blue-700">{{ $grandTotal }}</span>
                    </div>
                </div>

                {{-- Metodos de pago + Restante/Cambio: comparten x-data para que
                el restante/cambio se recalcule EN VIVO mientras se escribe,
                sin esperar a perder el foco del input. --}}
                <div x-data="posPaymentSummary({{ (float) ($totals['grand_total'] ?? 0) }})">
                {{-- Metodos de pago --}}
                <div class="space-y-1.5">
                    <p class="text-xs font-semibold text-gray-700">Forma de pago</p>
                    @foreach ($payments as $paymentIndex => $payment)
                        <div class="grid grid-cols-[140px_1fr_36px] gap-1.5">
                            <select wire:model.live="payments.{{ $paymentIndex }}.payment_method_code"
                                class="h-9 rounded-lg border-gray-300 bg-white px-2 text-sm text-gray-800 focus:border-blue-600 focus:ring-blue-600">
                                @foreach ($this->paymentMethodOptions() as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div x-data="paymentAmount('{{ $payment['amount'] ?? '' }}', {{ $paymentIndex }})">
                                <input type="text"
                                    wire:ignore
                                    x-ref="input"
                                    x-on:focus="onFocus"
                                    x-on:input="onInput"
                                    x-on:blur="onBlur"
                                    placeholder="Valor"
                                    inputmode="numeric"
                                    class="h-9 w-full rounded-lg border-gray-300 bg-white px-2 text-right text-sm font-semibold text-gray-900 focus:border-blue-600 focus:ring-blue-600">
                            </div>
                            <button type="button" wire:click="removePaymentLine({{ (int) ($payment['_key'] ?? $paymentIndex) }})"
                                class="h-9 rounded-lg border border-gray-200 bg-white font-bold text-gray-400 transition hover:border-rose-300 hover:text-rose-600">×</button>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addPaymentLine" class="mt-1 text-xs font-semibold text-blue-700 transition hover:text-blue-800">
                        + Agregar metodo
                    </button>
                    @php
                        $hasCreditPayment = collect($payments)->contains(fn ($p) => ($p['payment_method_code'] ?? '') === 'credit');
                        $creditCustomer = $resolvedCustomerId ? $customers->firstWhere('id', $resolvedCustomerId) : null;
                        $suggestedCreditLimit = $this->creditPaymentsTotal();
                    @endphp
                    @if ($hasCreditPayment)
                        <div class="mt-1.5 rounded-lg px-2.5 py-1.5 text-xs ring-1 ring-inset {{ $creditCustomer && $creditCustomer->credit_enabled && $creditCustomer->creditAccount ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200' }}">
                            @if (! $resolvedCustomerId)
                                Selecciona un cliente para usar credito.
                            @elseif (! $creditCustomer?->credit_enabled)
                                <p>Este cliente no tiene credito habilitado.</p>
                                @if ($this->canManageCredit())
                                    <div x-data="{ limit: '{{ $suggestedCreditLimit }}' }" class="mt-2 flex items-center gap-2">
                                        <input type="text" x-model="limit" inputmode="numeric" placeholder="Cupo a otorgar"
                                            class="h-8 flex-1 rounded-md border-amber-300 bg-white px-2 text-xs text-gray-900 focus:border-blue-600 focus:ring-blue-600">
                                        <button type="button" x-on:click="$wire.call('enableCreditForResolvedCustomer', limit)"
                                            class="h-8 shrink-0 rounded-md bg-blue-600 px-3 text-xs font-semibold text-white transition hover:bg-blue-700">
                                            Habilitar
                                        </button>
                                    </div>
                                @endif
                            @elseif ($creditCustomer->creditAccount)
                                Credito disponible: <strong>{{ number_format((float) $creditCustomer->creditAccount->available_credit, 0, '.', '.') }}</strong>
                            @else
                                Sin cuenta de credito configurada.
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Restante / Cambio: calculado en JS a partir de $wire.payments,
                se actualiza con cada tecla (ver posPaymentSummary() arriba). --}}
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg bg-gray-50 p-2.5 ring-1 ring-gray-200">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Restante</span>
                        <span class="block text-right text-base font-semibold" :class="remaining() > 0 ? 'text-rose-600' : 'text-gray-400'" x-text="fmt(Math.max(remaining(), 0))"></span>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-2.5 ring-1 ring-gray-200">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500">Cambio</span>
                        <span class="block text-right text-base font-semibold" :class="remaining() < 0 ? 'text-emerald-700' : 'text-gray-400'" x-text="fmt(Math.max(-remaining(), 0))"></span>
                    </div>
                </div>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex flex-shrink-0 items-center justify-end gap-3 border-t border-stone-100 px-5 py-3">
                <button type="button" wire:click="closePaymentModal" class="text-sm font-medium text-gray-500 hover:text-gray-700">
                    Cancelar
                </button>
                <button type="button" wire:click="saveSale"
                    @if ($saleStatus === 'confirmed')
                        x-on:click="window.__posTicketTab = window.open('', '_blank')"
                    @endif
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                    {{ $saleStatus === 'confirmed' ? 'Confirmar y cobrar' : 'Guardar borrador' }}
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Modal: ventas congeladas --}}
@if ($showFrozenModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);" wire:click.self="closeFrozenSalesModal">
        <div class="flex w-full max-w-xl flex-col rounded-xl bg-white shadow-xl" style="max-height: 90vh;">
            {{-- Cabecera --}}
            <div class="flex flex-shrink-0 items-center justify-between border-b border-stone-100 px-5 py-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Carritos guardados</p>
                    <h3 class="mt-0.5 text-lg font-black text-gray-900">Ventas congeladas</h3>
                </div>
                <button wire:click="closeFrozenSalesModal" class="px-1 text-xl leading-none text-gray-400 hover:text-gray-700" title="Cerrar">&times;</button>
            </div>
            {{-- Lista --}}
            <div class="flex-1 divide-y divide-stone-100 overflow-y-auto">
                @forelse ($frozenSalesForModal as $fs)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $fs['label'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">
                                {{ $fs['created_at'] }} &middot; {{ $fs['creator_name'] }} &middot; {{ $fs['items_count'] }}&nbsp;{{ $fs['items_count'] === 1 ? 'item' : 'items' }}
                            </p>
                        </div>
                        <button
                            wire:click="resumeFrozenSale({{ $fs['id'] }})"
                            class="shrink-0 rounded-full bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700"
                        >
                            Retomar
                        </button>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-gray-400">
                        No hay ventas congeladas abiertas.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

@endif

{{-- Modal: confirmar salida al dashboard con venta sin terminar --}}
<div
    x-data="{ open: false, freezing: false }"
    x-on:pos-leave-guard-open.window="open = true"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center p-4"
    style="background: rgba(0,0,0,0.5);"
>
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" x-show="open" x-on:click.outside="open = false">
        @if ($modifyingSaleId)
            <h3 class="text-lg font-black text-gray-900">Tienes cambios sin confirmar</h3>
            <p class="mt-2 text-sm leading-6 text-gray-600">
                Estas modificando una venta ya confirmada. Si sales sin confirmar el cambio, la venta original queda tal como estaba.
            </p>
        @else
            <h3 class="text-lg font-black text-gray-900">Tienes una venta sin terminar</h3>
            <p class="mt-2 text-sm leading-6 text-gray-600">
                Hay productos en el carrito. ¿Qué quieres hacer antes de ir al panel principal?
            </p>
        @endif
        <div class="mt-6 flex flex-col gap-2">
            @if (! $modifyingSaleId && $this->canFreezeCurrentSale())
                <button
                    type="button"
                    x-bind:disabled="freezing"
                    x-on:click="
                        freezing = true;
                        $wire.freezeCurrentSale().then(() => {
                            freezing = false;
                            const stillHasItems = ($wire.items || []).some(i => i.product_id);
                            if (stillHasItems) { return; }
                            window.location.href = window.posPendingHomeHref;
                        });
                    "
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                >
                    <span x-show="!freezing">Sí, congelar venta y salir</span>
                    <span x-show="freezing" x-cloak>Congelando venta...</span>
                </button>
            @endif
            <button
                type="button"
                x-on:click="window.location.href = window.posPendingHomeHref"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
            >
                @if ($modifyingSaleId)
                    Sí, salir y dejar la venta como estaba
                @else
                    Sí, salir sin guardar nada
                @endif
            </button>
            <button
                type="button"
                x-on:click="open = false"
                class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-500 transition hover:bg-gray-100"
            >
                Cancelar
            </button>
        </div>
    </div>
</div>
</div>
