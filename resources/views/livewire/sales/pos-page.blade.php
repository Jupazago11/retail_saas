@php
    use App\Enums\SaleStatus;
    use App\Support\Money;

    $totals = $preview['totals'] ?? [];
    $promotions = $preview['promotions'] ?? [];
    $selectedCustomer = $this->selectedCustomer();
    $grandTotal = Money::format((float) ($totals['grand_total'] ?? 0));
    $pointsEquivalent = Money::format((float) $this->loyaltyCashEquivalentPreview());
    $remaining = $this->remainingToCollect();
    $isChange = bccomp($remaining, '0', 2) < 0;
    $remainingDisplay = $isChange ? '0' : Money::format((float) $remaining);
    $changeDisplay = $isChange ? Money::format((float) bcmul($remaining, '-1', 2)) : '0';
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

    .pos-shell {
        border: 1px solid #7c7c7c;
        background: linear-gradient(180deg, #d7d7d7 0%, #cecece 100%);
        color: rgb(28 25 23);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        display: flex;
        flex-direction: column;
    }

    /* â”€â”€ TOP BAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pos-top {
        flex-shrink: 0;
        display: grid;
        gap: 0.3rem;
        border-bottom: 1px solid #8b8b8b;
        padding: 0.25rem;
        background: linear-gradient(180deg, #e8e8e8 0%, #dadada 100%);
    }

    @media (min-width: 1024px) {
        .pos-top {
            grid-template-columns: 210px minmax(0, 1fr);
        }

        .pos-top--with-register {
            grid-template-columns: 210px 160px minmax(0, 1fr);
        }
    }

    .pos-audit {
        font-family: "Consolas", "Courier New", monospace;
        letter-spacing: 0.22em;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
    }

    /* â”€â”€ TOOLBAR (2 filas) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pos-toolbar-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .pos-tools {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.2rem;
        align-content: start;
    }

    .pos-tool {
        position: relative;
        display: flex;
        height: 2.9rem;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 1px solid #9b9b9b;
        background: linear-gradient(180deg, #fdfdfd 0%, #ececec 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
        transition: background 150ms ease, transform 120ms ease;
        cursor: pointer;
        text-decoration: none;
    }

    .pos-tool:hover {
        background: linear-gradient(180deg, #fff7db 0%, #f6e5a9 100%);
    }

    .pos-tool:active {
        transform: translateY(1px);
    }

    .pos-tool-badge {
        position: absolute;
        right: 0.22rem;
        top: 0.22rem;
        height: 0.6rem;
        width: 0.6rem;
        border-radius: 9999px;
        background-color: #f59e0b;
    }

    .pos-tool-badge--red {
        background-color: #ef4444;
    }

    .pos-tool svg {
        width: 1.375rem;
        height: 1.375rem;
        flex-shrink: 0;
        pointer-events: none;
    }

    /* â”€â”€ TABLE (area principal de lineas) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pos-table {
        height: 42vh;
        min-height: 220px;
        overflow-y: auto;
        border-bottom: 1px solid #8b8b8b;
        background: #fff;
    }

    .pos-table-head {
        display: grid;
        grid-template-columns: 96px 1.35fr 152px 110px 94px 112px 44px;
        border-bottom: 1px solid #8b8b8b;
        background: linear-gradient(180deg, #d4d4d4 0%, #c3c3c3 100%);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #44403c;
    }

    .pos-table-row {
        display: grid;
        grid-template-columns: 96px 1.35fr 152px 110px 94px 112px 44px;
        border-bottom: 1px solid #ececec;
        font-size: 12px;
    }

    /* â”€â”€ ROUND BUTTONS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pos-round-button {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 2.75rem;
        width: 2.75rem;
        border: 1px solid #7a7a7a;
        border-radius: 9999px;
        background: linear-gradient(180deg, #737373 0%, #535353 100%);
        color: #ffb200;
        font-size: 1.45rem;
        font-weight: 900;
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.18);
        cursor: pointer;
        flex-shrink: 0;
    }

    .pos-round-button--confirm {
        background: linear-gradient(180deg, #686868 0%, #404040 100%);
        color: #ffc933;
    }

    .pos-round-button:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    /* â”€â”€ BOTTOM STRIP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pos-bottom {
        flex-shrink: 0;
        border-top: 1px solid #8b8b8b;
        background: linear-gradient(180deg, #d8d8d8 0%, #cacaca 100%);
        padding: 0.3rem 0.4rem;
    }

    .pos-bottom-grid {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0.4rem;
        align-items: start;
    }

    .pos-bottom-buttons {
        display: flex;
        gap: 0.3rem;
        align-items: start;
        padding-top: 0.1rem;
    }

    .pos-btn-col {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .pos-bottom-sections {
        display: grid;
        gap: 0.3rem;
        grid-template-columns: 1fr 1fr;
    }

    @media (min-width: 1024px) {
        .pos-bottom-sections {
            grid-template-columns: minmax(0, 1fr) 160px 120px 120px 150px;
        }
    }

    /* â”€â”€ STAT BOXES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .pos-stat-box {
        border: 1px solid #9e9e9e;
        background: linear-gradient(180deg, #fff 0%, #f1f1f1 100%);
        padding: 0.2rem 0.4rem 0.25rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }

    .pos-stat-label {
        display: block;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #555;
        line-height: 1;
    }

    .pos-stat-value {
        display: block;
        font-size: 17px;
        font-weight: 600;
        text-align: right;
        color: #1c1917;
        line-height: 1.15;
        margin-top: 2px;
    }

    .pos-stat-value--sm {
        font-size: 13px;
    }

    .pos-total-box {
        border: 1px solid #7f7f7f;
        background: linear-gradient(180deg, #fffef1 0%, #f3edc5 100%);
        padding: 0.2rem 0.4rem 0.25rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }

    .pos-total-value {
        display: block;
        font-size: 26px;
        font-weight: 900;
        text-align: right;
        color: #1c1917;
        line-height: 1.1;
        margin-top: 2px;
    }

    /* -- PRODUCT SEARCH DROPDOWN -------------------------------- */
    .pos-product-dropdown {
        position: absolute;
        bottom: calc(100% + 2px);
        left: 0;
        right: 0;
        z-index: 100;
        background: #fff;
        border: 1px solid #7c7c7c;
        box-shadow: 0 -4px 14px rgba(0, 0, 0, 0.18);
        max-height: 220px;
        overflow-y: auto;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .pos-product-dropdown-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        padding: 0.32rem 0.5rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        font-size: 12px;
    }
    .pos-product-dropdown-item:hover,
    .pos-product-dropdown-item--active {
        background: linear-gradient(90deg, #fff7db 0%, #f6e5a9 100%);
    }
    .pos-product-dropdown-name {
        font-weight: 600;
        color: #1c1917;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pos-product-dropdown-code {
        font-size: 10px;
        color: #78716c;
        font-family: "Consolas", "Courier New", monospace;
        flex-shrink: 0;
    }
    .pos-tool--primary {
        background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%);
        border-color: #b45309;
        color: #7c2d12;
    }
    .pos-tool--primary:hover {
        background: linear-gradient(180deg, #fcd34d 0%, #f59e0b 100%);
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
            },
            onBlur: function() {
                var raw = this.$refs.input.value.replace(/[^\d]/g, '');
                this.raw = raw;
                this.$refs.input.value = raw ? this.fmt(raw) : '';
                this.$wire.set('payments.' + this.idx + '.amount', raw || '');
            }
        };
    }
</script>

<script>
    document.addEventListener('livewire:initialized', function () {
        var POS = {
            log: function() { var a = ['[POS]'].concat(Array.from(arguments)); console.log.apply(console, a); },
            err: function() { var a = ['[POS ERR]'].concat(Array.from(arguments)); console.error.apply(console, a); }
        };

        Livewire.hook('request', function(ctx) {
            var methods = (ctx.payload && ctx.payload.calls) ? ctx.payload.calls.map(function(c){ return c.method; }) : [];
            POS.log('request →', methods);
            ctx.succeed(function(resp) { POS.log('response OK', resp.status); });
            ctx.fail(function(resp) { POS.err('response FAIL', resp.status, resp.content && resp.content.substring(0,200)); });
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

        POS.log('Debug hooks OK');
    });
</script>

<div class="space-y-3">
    <span class="sr-only">Redencion</span>

    @if (! $canCreateSales)
        <section class="border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ $salesRequirementsMessage }}
        </section>
    @endif

    <div class="pos-shell" data-pos-shell>

        {{-- â•â• TOP: Brand + Toolbar (2 filas) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="pos-top {{ $showRegisterSelector ? 'pos-top--with-register' : '' }}">

            {{-- Marca y referencia de operacion --}}
            <div class="flex flex-col justify-end">
                <label for="pos-audit" class="mb-0.5 block text-[10px] font-semibold uppercase text-gray-600">Operacion</label>
                <input
                    id="pos-audit"
                    type="text"
                    value="{{ $auditReference }}"
                    readonly
                    class="pos-audit h-9 w-full border border-stone-500 bg-white px-3 text-[14px] font-semibold text-gray-900"
                >
            </div>

            {{-- Selector de caja: solo si hay mas de una activa en la sucursal --}}
            @if ($showRegisterSelector)
                <div class="flex flex-col justify-end">
                    <label for="pos-cash-register" class="mb-0.5 block text-[10px] font-semibold uppercase text-gray-600">Caja</label>
                    <select
                        id="pos-cash-register"
                        wire:model.live="cashRegisterId"
                        class="h-9 w-full border border-stone-500 bg-white px-2 text-[13px] font-semibold text-gray-900"
                    >
                        @foreach ($cashRegisters as $register)
                            <option value="{{ $register->id }}">{{ $register->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Toolbar: 1 fila de 7 botones --}}
            <div class="pos-tools">
                {{-- Cobrar --}}
                <button type="button" wire:click="openPaymentModal" class="pos-tool pos-tool--primary" title="Cobrar">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386a1.5 1.5 0 0 1 1.455 1.136L5.4 5.25m0 0h13.95l-1.5 7.5H6.75m-1.35-7.5L6.75 12.75m0 0-1.125 5.625A1.5 1.5 0 0 0 7.095 20.25H18M9 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm8.25 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                    <span class="pos-tool-badge"></span>
                </button>
                {{-- Ticket ultima venta --}}
                @if ($latestSale)
                    <a href="{{ route('sales.ticket', $latestSale) }}" target="_blank" rel="noopener noreferrer" class="pos-tool" title="Ticket ultima venta">
                        <svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25h-10.5A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18v-2.25m-6-3h7.5m0 0-3-3m3 3-3 3"/></svg>
                        <span class="pos-tool-badge"></span>
                        <span class="sr-only">Ticket ultima venta</span>
                    </a>
                @else
                    <div class="pos-tool cursor-default opacity-30" title="Sin ventas aun">
                        <svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25h-10.5A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18v-2.25m-6-3h7.5m0 0-3-3m3 3-3 3"/></svg>
                    </div>
                @endif
                {{-- Congelar venta actual --}}
                @if ($this->canViewFrozenSales())
                    <button type="button" wire:click="freezeCurrentSale" class="pos-tool" title="Congelar venta actual">
                        {{-- cubo de hielo: cube-transparent Heroicon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
                        <span class="pos-tool-badge pos-tool-badge--red"></span>
                    </button>
                @else
                    <div class="pos-tool cursor-default opacity-20"></div>
                @endif
                {{-- Congeladas (modal) --}}
                @if ($this->canViewFrozenSales())
                    <button type="button" wire:click="openFrozenSalesModal" class="pos-tool" title="Ventas congeladas">
                        {{-- cubo de hielo pequeño + flecha hacia abajo --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 8.5l-6-3.5-6 3.5m12 0-6 3.25m6-3.25v5.9l-6 3.5M6 8.5l6 3.25M6 8.5v5.9l6 3.5m0-5.9v5.9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v2.5m0 0-1.5-1.5m1.5 1.5 1.5-1.5"/>
                        </svg>
                        <span class="pos-tool-badge pos-tool-badge--red"></span>
                    </button>
                @else
                    <div class="pos-tool cursor-default opacity-20"></div>
                @endif
                {{-- Historial --}}
                <a href="{{ route('sales.index') }}" class="pos-tool" title="Historial de ventas">
                    <svg xmlns="http://www.w3.org/2000/svg" class="text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M3 9.75h18M3 15h18M3 20.25h18"/></svg>
                    <span class="pos-tool-badge pos-tool-badge--red"></span>
                </a>
            </div>
        </div>

        {{-- â•â• TABLE: area principal de lineas â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="pos-table">
            <div class="pos-table-head">
                <div class="border-r border-gray-300 px-2 py-2">Codigo</div>
                <div class="border-r border-gray-300 px-2 py-2">Producto</div>
                <div class="border-r border-gray-300 px-2 py-2 text-right">Cant.</div>
                <div class="border-r border-gray-300 px-2 py-2 text-right">Precio</div>
                <div class="border-r border-gray-300 px-2 py-2 text-center">Lista</div>
                <div class="border-r border-gray-300 px-2 py-2 text-right">Total</div>
                <div class="px-2 py-2 text-center">Acc.</div>
            </div>

            @php $hasProducts = collect($items)->contains(fn($i) => !blank($i['product_id'] ?? null)); @endphp
            @if (! $hasProducts)
                <div class="flex min-h-[340px] items-center justify-center text-sm text-gray-500">
                    Escanea o busca un producto para iniciar la venta.
                </div>
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
                    <div class="pos-table-row">
                        <div class="border-r border-gray-200 px-2 py-2 text-gray-600">{{ $barcode }}</div>
                        <div class="border-r border-gray-200 px-2 py-2">
                            <div class="font-semibold text-gray-900">{{ $product?->name ?? '—' }}</div>
                            @if ($product?->presentations?->isNotEmpty())
                                <select wire:model.live="items.{{ $index }}.product_presentation_id" class="mt-1 h-7 w-full border border-gray-300 bg-white px-2 text-[11px] text-gray-700">
                                    <option value="">Presentacion base</option>
                                    @foreach ($product->presentations as $presentation)
                                        <option value="{{ $presentation->id }}">{{ $presentation->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="border-r border-gray-200 px-1.5 py-2">
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="decreaseQuantity({{ $lineKey }})" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-stone-400 bg-[#5c5c5c] text-xl font-black text-amber-400">-</button>
                                <input type="text" wire:model.live="items.{{ $index }}.quantity" class="h-8 min-w-0 flex-1 border border-gray-300 bg-white px-1 text-center text-[15px] text-gray-900">
                                <button type="button" wire:click="increaseQuantity({{ $lineKey }})" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-stone-400 bg-[#5c5c5c] text-xl font-black text-amber-400">+</button>
                            </div>
                        </div>
                        <div class="border-r border-gray-200 px-2 py-2 text-right text-[15px] font-semibold text-gray-900">
                            {{ Money::format((float) ($item['unit_price'] ?? 0)) }}
                        </div>
                        <div class="border-r border-gray-200 px-1 py-2">
                            @if (count($priceTierOptions) > 1)
                                <div class="grid grid-cols-3 gap-1">
                                    @foreach ($priceTierOptions as $tier => $option)
                                        <button
                                            type="button"
                                            wire:click="switchPriceTier({{ $lineKey }}, '{{ $tier }}')"
                                            class="h-8 border text-[11px] font-bold {{ ($item['price_tier'] ?? 'price_1') === $tier ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100' }}"
                                        >
                                            {{ $option['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <button type="button" wire:click="cyclePriceTier({{ $lineKey }})" class="h-8 w-full border border-gray-300 bg-[#f7f7f7] text-[11px] font-bold text-gray-700">
                                    {{ strtoupper(str_replace('price_', 'V', (string) ($item['price_tier'] ?? 'price_1'))) }}
                                </button>
                            @endif
                        </div>
                        <div class="border-r border-gray-200 px-2 py-2 text-right text-[17px] font-semibold text-gray-900">{{ $lineTotal }}</div>
                        <div class="flex items-center justify-center px-1 py-2">
                            <button type="button" wire:click="removeItemLine({{ $lineKey }})" class="flex h-8 w-8 items-center justify-center border border-rose-400 bg-gradient-to-b from-rose-50 to-rose-100 text-rose-500 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] transition-all hover:border-rose-600 hover:from-rose-100 hover:to-rose-200 hover:text-rose-700 active:translate-y-px active:shadow-none" title="Eliminar linea">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7.5h12m-9.75 0V6a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 .75.75v1.5m-8.25 0v10.125A1.875 1.875 0 0 0 9.375 19.5h5.25a1.875 1.875 0 0 0 1.875-1.875V7.5"/></svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>


        {{-- FIDELIZACION (solo si aplica) â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        @if ($this->canRedeemLoyalty() || $selectedCustomer || $customerId || $loyaltyPointsToRedeem !== '')
            <div class="border-t border-gray-300 bg-[#f0efe9] px-3 py-2">
                <div class="grid gap-1 sm:grid-cols-[1fr_140px_140px]">
                    <div class="pos-stat-box">
                        <span class="pos-stat-label">Cliente</span>
                        <span class="mt-1 block text-sm font-semibold text-gray-900">{{ $selectedCustomer?->person?->full_name ?? 'Cliente fidelizado' }}</span>
                    </div>
                    <div class="pos-stat-box">
                        <label class="pos-stat-label">Redencion</label>
                        <input type="text" wire:model.live="loyaltyPointsToRedeem" class="mt-1 h-7 w-full border border-gray-300 bg-white px-2 text-right text-sm text-gray-900">
                        <span class="sr-only">Redencion</span>
                    </div>
                    <div class="pos-stat-box">
                        <span class="pos-stat-label">Equivalente</span>
                        <span class="mt-1 block text-right text-[15px] font-semibold text-gray-900">{{ $pointsEquivalent }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- â•â• PROMOCIONES â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        @if ($promotions !== [])
            <div class="border-t border-gray-300 bg-[#fff7e8] px-3 py-1.5 text-xs text-gray-700">
                <span class="font-semibold text-gray-900">Promociones:</span> {{ $promotionNames }}
            </div>
        @endif

        {{-- â•â• BOTTOM STRIP: captura + indicadores + totales + info â•â•â•â•â•â•â•â• --}}
        <div class="pos-bottom">
            <div class="pos-bottom-sections">

                    {{-- Seccion 1: Busqueda de producto --}}
                    <div x-data="posProductSearch()" class="relative">
                        <label class="pos-stat-label">Producto</label>
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
                            class="h-9 w-full border border-stone-400 bg-white px-2 text-[13px] font-medium text-gray-900"
                            placeholder="Codigo de barras o nombre"
                        >
                        <ul
                            x-show="open && results.length > 0"
                            x-cloak
                            class="pos-product-dropdown"
                        >
                            <template x-for="(p, i) in results" :key="p.id">
                                <li
                                    @mousedown.prevent="addProduct(p)"
                                    :class="{ 'pos-product-dropdown-item--active': i === highlighted }"
                                    class="pos-product-dropdown-item"
                                >
                                    <span class="pos-product-dropdown-name" x-text="p.name"></span>
                                    <span class="pos-product-dropdown-code" x-text="p.barcode || p.sku || ''"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Total a cobrar --}}
                    <div class="pos-total-box flex flex-col justify-between">
                        <span class="pos-stat-label">Total a cobrar</span>
                        <span class="pos-total-value">{{ $grandTotal }}</span>
                    </div>

                    {{-- Restante a pagar --}}
                    <div class="pos-stat-box flex flex-col justify-between">
                        <span class="pos-stat-label">Restante</span>
                        <span class="pos-stat-value {{ $isChange ? 'text-emerald-700' : '' }}">{{ $remainingDisplay }}</span>
                    </div>

                    {{-- Cambio a devolver --}}
                    <div class="pos-stat-box flex flex-col justify-between">
                        <span class="pos-stat-label">Cambio</span>
                        <span class="pos-stat-value {{ $isChange ? 'text-emerald-700' : 'text-gray-400' }}">{{ $changeDisplay }}</span>
                    </div>

                    {{-- Estado + Info --}}
                    <div class="space-y-1">
                        <button
                            type="button"
                            wire:click="$set('saleStatus', '{{ $saleStatus === SaleStatus::Confirmed->value ? SaleStatus::Draft->value : SaleStatus::Confirmed->value }}')"
                            class="pos-stat-box w-full text-left hover:bg-gray-50 transition-colors"
                            title="Cambiar estado"
                        >
                            <span class="pos-stat-label">Estado</span>
                            <span class="mt-0.5 block text-right text-[13px] font-bold {{ $saleStatus === SaleStatus::Confirmed->value ? 'text-emerald-700' : 'text-blue-700' }}">
                                {{ $saleStatus === SaleStatus::Confirmed->value ? 'CONTADO' : 'BORRADOR' }}
                            </span>
                        </button>
                        <div class="pos-stat-box space-y-0.5 text-[10px] text-gray-600">
                            <p><span class="font-semibold">{{ $this->cashierName() }}</span></p>
                            <p>{{ $this->terminalLabel() }}</p>
                            <p>{{ $this->workDateLabel() }}</p>
                        </div>
                    </div>

            </div>
        </div>

        </div>

    </div>
{{-- /root closes at end, after modals --}}

{{-- Modal: cobro de venta --}}
@if ($showPaymentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background: rgba(0,0,0,0.55);" wire:click.self="closePaymentModal">
        <div style="width: 100%; max-width: 520px; border: 1px solid #7c7c7c; background: linear-gradient(180deg,#e8e8e8 0%,#dadada 100%); box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
            {{-- Cabecera --}}
            <div class="flex items-center justify-between border-b border-stone-400 px-4 py-2.5" style="background: linear-gradient(180deg,#d4d4d4 0%,#c3c3c3 100%);">
                <span class="text-[11px] font-bold uppercase tracking-widest text-gray-700">Cobrar Venta</span>
                <button wire:click="closePaymentModal" class="flex h-7 w-7 items-center justify-center border border-stone-400 bg-gradient-to-b from-[#fdfdfd] to-[#ececec] text-gray-500 hover:border-rose-400 hover:text-rose-600" title="Cancelar">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Cliente --}}
            <div class="border-b border-stone-400 px-4 py-3">
                <p class="mb-1.5 text-[10px] font-bold uppercase tracking-widest text-gray-500">Cliente</p>

                @if ($resolvedCustomerId || $paymentCustomerDocument !== '')
                    <div class="flex items-center gap-2">
                        <div class="flex flex-1 items-center gap-2 border px-3 py-1.5 {{ $resolvedCustomerId ? 'border-emerald-400 bg-emerald-50' : 'border-amber-400 bg-amber-50' }}">
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
                            class="flex h-9 w-9 shrink-0 items-center justify-center border border-gray-300 bg-white text-lg leading-none text-gray-400 hover:border-rose-400 hover:text-rose-600">
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
                            class="h-9 w-full border border-stone-400 bg-white px-3 text-sm text-gray-800 placeholder-stone-400"
                            autocomplete="off"
                        >
                        <ul x-show="open" x-cloak
                            class="absolute left-0 right-0 top-full z-50 max-h-48 overflow-y-auto border border-gray-300 bg-white shadow-lg">
                            <template x-for="(c, i) in results" :key="c.id">
                                <li @mousedown.prevent="select(c)"
                                    :class="{ 'bg-amber-50': i === highlighted }"
                                    class="flex cursor-pointer items-center justify-between gap-2 border-b border-stone-100 px-3 py-2 hover:bg-blue-50">
                                    <span class="text-sm font-semibold text-gray-800" x-text="c.name"></span>
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

            {{-- Total a cobrar --}}
            <div class="border-b border-gray-300 px-4 py-2.5" style="background: linear-gradient(180deg,#fffef1 0%,#f3edc5 100%);">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-gray-600">Total a cobrar</span>
                    <span class="text-2xl font-black text-gray-900">{{ $grandTotal }}</span>
                </div>
            </div>

            {{-- Metodos de pago --}}
            <div class="px-4 py-3 space-y-1.5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Forma de pago</p>
                @foreach ($payments as $paymentIndex => $payment)
                    <div class="grid gap-1 grid-cols-[160px_1fr_32px]">
                        <select wire:model.live="payments.{{ $paymentIndex }}.payment_method_code"
                            class="h-9 border border-stone-400 bg-white px-2 text-sm text-gray-800">
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
                                class="h-9 w-full border border-stone-400 bg-white px-2 text-right text-sm font-semibold text-gray-900">
                        </div>
                        <button type="button" wire:click="removePaymentLine({{ (int) ($payment['_key'] ?? $paymentIndex) }})"
                            class="h-9 border border-gray-300 bg-white font-bold text-gray-400 hover:border-rose-400 hover:text-rose-600">×</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addPaymentLine" class="mt-1 flex h-7 items-center gap-1 border border-stone-400 bg-gradient-to-b from-[#fdfdfd] to-[#ececec] px-3 text-[11px] font-semibold text-gray-600 hover:border-stone-500">
                    + Agregar metodo
                </button>
                @php
                    $hasCreditPayment = collect($payments)->contains(fn ($p) => ($p['payment_method_code'] ?? '') === 'credit');
                    $creditCustomer = $resolvedCustomerId ? $customers->firstWhere('id', $resolvedCustomerId) : null;
                @endphp
                @if ($hasCreditPayment)
                    <div class="mt-1.5 border px-2.5 py-1.5 text-xs {{ $creditCustomer && $creditCustomer->credit_enabled && $creditCustomer->creditAccount ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-amber-300 bg-amber-50 text-amber-700' }}">
                        @if (! $resolvedCustomerId)
                            Selecciona un cliente para usar credito.
                        @elseif (! $creditCustomer?->credit_enabled)
                            Este cliente no tiene credito habilitado.
                        @elseif ($creditCustomer->creditAccount)
                            Credito disponible: <strong>{{ number_format((float) $creditCustomer->creditAccount->available_credit, 0, '.', '.') }}</strong>
                        @else
                            Sin cuenta de credito configurada.
                        @endif
                    </div>
                @endif
            </div>
                        {{-- Restante / Cambio --}}
            <div class="grid grid-cols-2 gap-2 px-4 pb-3">
                <div class="pos-stat-box">
                    <span class="pos-stat-label">Restante</span>
                    <span class="pos-stat-value {{ $isChange ? 'text-gray-400' : 'text-rose-600' }}">{{ $remainingDisplay }}</span>
                </div>
                <div class="pos-stat-box">
                    <span class="pos-stat-label">Cambio</span>
                    <span class="pos-stat-value {{ $isChange ? 'text-emerald-700' : 'text-gray-400' }}">{{ $changeDisplay }}</span>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="flex gap-2 border-t border-stone-400 px-4 py-3" style="background: linear-gradient(180deg,#d4d4d4 0%,#c3c3c3 100%);">
                <button type="button" wire:click="closePaymentModal" class="h-10 flex-1 border border-stone-500 bg-gradient-to-b from-[#fdfdfd] to-[#ececec] text-sm font-semibold text-gray-700 hover:border-stone-600">
                    Cancelar
                </button>
                <button type="button" wire:click="saveSale" class="h-10 flex-1 border border-amber-600 text-sm font-bold tracking-wide text-gray-900 hover:opacity-90" style="background: linear-gradient(180deg,#fbbf24 0%,#d97706 100%);">
                    {{ $saleStatus === 'confirmed' ? 'Confirmar y cobrar' : 'Guardar borrador' }}
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Modal: ventas congeladas --}}
@if ($showFrozenModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background: rgba(0,0,0,0.55);" wire:click.self="closeFrozenSalesModal">
        <div style="width: 100%; max-width: 560px; border: 1px solid #7c7c7c; background: linear-gradient(180deg,#e8e8e8 0%,#dadada 100%); box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
            {{-- Cabecera --}}
            <div class="flex items-center justify-between border-b border-stone-400 px-4 py-2.5" style="background: linear-gradient(180deg,#d4d4d4 0%,#c3c3c3 100%);">
                <span class="text-[11px] font-bold uppercase tracking-widest text-gray-700">Ventas Congeladas</span>
                <button wire:click="closeFrozenSalesModal" class="flex h-7 w-7 items-center justify-center border border-stone-400 bg-gradient-to-b from-[#fdfdfd] to-[#ececec] text-gray-500 hover:border-rose-400 hover:text-rose-600" title="Cerrar">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            {{-- Lista --}}
            <div class="max-h-80 overflow-y-auto">
                @forelse ($frozenSalesForModal as $fs)
                    <div class="flex items-center gap-3 border-b border-gray-300 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold text-gray-900">{{ $fs['label'] }}</p>
                            <p class="mt-0.5 text-[11px] text-gray-500">
                                {{ $fs['created_at'] }} &middot; {{ $fs['creator_name'] }} &middot; {{ $fs['items_count'] }}&nbsp;{{ $fs['items_count'] === 1 ? 'item' : 'items' }}
                            </p>
                        </div>
                        <button
                            wire:click="resumeFrozenSale({{ $fs['id'] }})"
                            class="flex h-8 shrink-0 items-center border border-stone-400 bg-gradient-to-b from-[#fdfdfd] to-[#ececec] px-3 text-[11px] font-bold uppercase tracking-wide text-gray-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.95)] transition-all hover:border-amber-500 hover:from-amber-50 hover:to-amber-100 hover:text-amber-800 active:translate-y-px"
                        >
                            Retomar
                        </button>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500">
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
        <h3 class="text-lg font-black text-gray-900">Tienes una venta sin terminar</h3>
        <p class="mt-2 text-sm leading-6 text-gray-600">
            Hay productos en el carrito. ¿Qué quieres hacer antes de ir al panel principal?
        </p>
        <div class="mt-6 flex flex-col gap-2">
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
            <button
                type="button"
                x-on:click="window.location.href = window.posPendingHomeHref"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
            >
                Sí, salir sin guardar nada
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
