import './bootstrap';

window.retailSaas = window.retailSaas ?? {};
window.retailSaas.toastDuration = 5000;

const posDebugStorageKey = 'retail-saas-pos-debug';

window.posDebug = (stage, details = {}) => {
    const entry = {
        stage,
        details,
        timestamp: new Date().toISOString(),
    };

    console.info(`[pos] ${stage}`, details, entry.timestamp);

    try {
        const history = JSON.parse(sessionStorage.getItem(posDebugStorageKey) ?? '[]');
        history.push(entry);
        sessionStorage.setItem(posDebugStorageKey, JSON.stringify(history.slice(-80)));
    } catch (error) {
        console.warn('[pos] no se pudo guardar el historial de depuracion', error);
    }
};

window.addEventListener('pos-debug', (event) => {
    window.posDebug(event.detail?.stage ?? 'server.event', event.detail?.details ?? event.detail ?? {});
});

document.addEventListener('alpine:init', () => {
    // Input numerico con puntos de miles mientras se escribe (ej "200.000").
    // No depende del detector global de campos "money" (que solo actua sobre
    // <input type=number> por nombre): aqui se controla explicitamente que
    // propiedad de Livewire (por path, soporta arrays: "openFunds.0.amount")
    // recibe el valor limpio (sin puntos), y si el update es inmediato o
    // diferido hasta el proximo request.
    Alpine.data('digitGroupInput', ({ path, live = true } = {}) => ({
        group(digits) {
            return digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
        },
        onInput(event) {
            const digits = event.target.value.replace(/\D+/g, '');
            const formatted = this.group(digits);
            event.target.value = formatted;
            this.$wire.set(path, digits, live);
        },
    }));

    // Ajusta cuantos registros pide el servidor por pagina segun el alto
    // real de la ventana, para que la tabla + paginacion siempre quepan
    // sin scroll interno ni desborde, sin importar el tamano del monitor.
    Alpine.data('responsivePageSize', ({ rowHeight = 64, reserved = 300, min = 4, max = 50 } = {}) => ({
        lastApplied: null,
        init() {
            this.apply();
            this.onResize = () => {
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => this.apply(), 200);
            };
            window.addEventListener('resize', this.onResize);
        },
        destroy() {
            window.removeEventListener('resize', this.onResize);
        },
        apply() {
            const available = window.innerHeight - reserved;
            const rows = Math.max(min, Math.min(max, Math.floor(available / rowHeight)));

            if (rows === this.lastApplied) {
                return;
            }

            this.lastApplied = rows;
            this.$wire.call('setPerPage', rows);
        },
    }));

    // Combobox generico: input de texto que filtra client-side sobre una
    // lista de opciones ya cargada (categorias, marcas, unidades, ...) y se
    // sincroniza en dos vias con una propiedad Livewire via $wire.entangle.
    // Con allowCreate:true, si el texto tecleado no coincide con ninguna
    // opcion existente, se ofrece un item sintetico "Crear ..." y `selected`
    // termina valiendo el texto libre (no un id numerico); el backend
    // (ProductsPage::resolveCategoryValue/resolveBrandValue) es quien decide
    // si eso hay que crearlo, al guardar el formulario.
    Alpine.data('searchableSelect', ({ selected, allowCreate = false }) => ({
        selected,
        allowCreate,
        options: [],
        query: '',
        open: false,
        touched: false,
        highlighted: -1,
        init() {
            this.readOptions();
            this.syncFromSelected();

            // El atributo `data-options` lo reescribe Livewire en cada morph
            // (p. ej. tras crear una unidad con "+ Nueva"); observarlo es lo
            // que mantiene la lista disponible sin recargar la pagina, ya que
            // `x-data` solo se evalua una vez al montar el componente.
            this.optionsObserver = new MutationObserver(() => {
                this.readOptions();
                this.syncFromSelected();
            });
            this.optionsObserver.observe(this.$el, { attributes: true, attributeFilter: ['data-options'] });

            this.$watch('selected', () => this.syncFromSelected());
        },
        readOptions() {
            try {
                this.options = JSON.parse(this.$el.dataset.options ?? '[]');
            } catch (error) {
                this.options = [];
            }
        },
        findExact(term) {
            const normalized = term.trim().toLowerCase();

            return normalized
                ? this.options.find((option) => option.label.toLowerCase() === normalized)
                : undefined;
        },
        syncFromSelected() {
            const match = this.options.find((option) => String(option.id) === String(this.selected));

            if (match) {
                this.query = match.label;
            } else if (this.allowCreate && this.selected) {
                // `selected` es texto libre pendiente de crear al guardar.
                this.query = String(this.selected);
            } else {
                this.query = '';
            }

            this.touched = false;
        },
        get filtered() {
            const base = (() => {
                if (! this.touched) {
                    return this.options;
                }

                const term = this.query.trim().toLowerCase();

                return term
                    ? this.options.filter((option) => option.label.toLowerCase().includes(term))
                    : this.options;
            })();

            const trimmed = this.query.trim();

            if (this.allowCreate && this.touched && trimmed !== '' && ! this.findExact(trimmed)) {
                return [...base, { id: trimmed, label: 'Crear "' + trimmed + '"', __create: true }];
            }

            return base;
        },
        onFocus(event) {
            this.open = true;
            event.target.select();
        },
        onInput() {
            this.touched = true;
            this.open = true;
            this.highlighted = -1;
        },
        choose(option) {
            this.selected = option.id;
            this.query = option.__create ? option.id : option.label;
            this.open = false;
            this.touched = false;
            this.highlighted = -1;
        },
        close() {
            this.open = false;

            const trimmed = this.query.trim();

            if (trimmed === '') {
                this.selected = '';
            } else if (this.allowCreate) {
                const exact = this.findExact(trimmed);
                this.selected = exact ? exact.id : trimmed;
            }

            this.syncFromSelected();
        },
        highlightNext() {
            this.open = true;
            this.highlighted = Math.min(this.highlighted + 1, this.filtered.length - 1);
        },
        highlightPrev() {
            this.open = true;
            this.highlighted = Math.max(this.highlighted - 1, 0);
        },
        selectHighlighted() {
            const option = this.filtered[this.highlighted] ?? (this.filtered.length === 1 ? this.filtered[0] : null);

            if (option) {
                this.choose(option);
            }
        },
    }));
});

document.addEventListener('livewire:init', () => {
    window.posDebug('livewire.init', {
        available: Boolean(window.Livewire),
        version: window.Livewire?.version ?? 'desconocida',
    });

    window.Livewire?.hook('request', ({ payload, succeed, fail }) => {
        if (! document.querySelector('[data-pos-shell]')) {
            return;
        }

        const methods = (payload?.components ?? [])
            .flatMap((component) => component.calls ?? [])
            .map((call) => call.method);

        window.posDebug('livewire.request', { methods });
        succeed(({ status }) => window.posDebug('livewire.request.succeeded', { methods, status }));
        fail(({ status, content }) => window.posDebug('livewire.request.failed', {
            methods,
            status,
            responsePreview: String(content ?? '').slice(0, 500),
        }));
    });
});

function findPosWire(input) {
    // El wire:id puede estar en un antecesor directo o en un hermano (<style> es
    // el primer elemento raíz del componente y recibe wire:id en lugar del <div>).
    const fromAncestor = input.closest('[wire\\:id]');
    if (fromAncestor) return window.Livewire?.find(fromAncestor.getAttribute('wire:id'));

    // Buscar wire:id como hermano del <div class="space-y-3"> (raíz visual del POS).
    // Sin fallback a "cualquier wire:id de la página": fuera del POS no hay
    // carrito que proteger, y adivinar un componente ajeno (p. ej. en el
    // dashboard o en Inventario) rompía este guard con datos que no son suyos.
    const shell = document.querySelector('[data-pos-shell]');
    const posRoot = shell?.closest('.space-y-3') ?? shell?.parentElement;
    if (posRoot) {
        const sibling = posRoot.closest('[wire\\:id]')
            ?? Array.from(posRoot.parentElement?.children ?? []).find(el => el.hasAttribute('wire:id'));
        if (sibling) return window.Livewire?.find(sibling.getAttribute('wire:id'));
    }

    return null;
}

async function submitPosProductLookup(input, source) {
    const value = input.value.trim();
    const wire = findPosWire(input);
    const componentId = wire?.$id ?? wire?.id ?? '(resuelto)';

    window.posDebug('lookup.livewire.call.started', {
        source,
        value,
        componentId,
        wireFound: Boolean(wire),
        callAvailable: typeof wire?.$call === 'function',
    });

    if (! value || ! wire || typeof wire.$call !== 'function') {
        window.posDebug('lookup.livewire.call.unavailable', { source, value, componentId });
        return;
    }

    if (input.dataset.posLookupPending === '1') {
        window.posDebug('lookup.livewire.call.skipped-pending', { source, value });
        return;
    }

    input.dataset.posLookupPending = '1';

    try {
        const result = await wire.$call('submitProductLookup', value);
        window.posDebug('lookup.livewire.call.succeeded', { source, value, result });
        input.value = '';
        delete input.dataset.lastSelectedValue;
    } catch (error) {
        window.posDebug('lookup.livewire.call.failed', {
            source,
            value,
            message: error?.message ?? String(error),
            error,
        });
    } finally {
        delete input.dataset.posLookupPending;
    }
}

document.addEventListener('input', (event) => {
    if (! event.target?.matches('[data-pos-product-input]')) {
        return;
    }

    const input = event.target;
    const exactOptionSelected = Array.from(input.list?.options ?? [])
        .some((option) => option.value === input.value);

    window.posDebug('lookup.native.input', {
        value: input.value,
        exactOptionSelected,
    });

    if (exactOptionSelected && input.dataset.lastSelectedValue !== input.value) {
        input.dataset.lastSelectedValue = input.value;
        queueMicrotask(() => submitPosProductLookup(input, 'native-option'));
    }
});

document.addEventListener('change', (event) => {
    if (event.target?.matches('[data-pos-product-input]')) {
        window.posDebug('lookup.native.change', { value: event.target.value });
        submitPosProductLookup(event.target, 'change');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && event.target?.matches('[data-pos-product-input]')) {
        event.preventDefault();
        window.posDebug('lookup.native.enter', { value: event.target.value });
        submitPosProductLookup(event.target, 'enter');
    }
});

// Si el cajero se va al dashboard con productos en el carrito del POS
// (click accidental en el logo), interceptamos y preguntamos que hacer
// con la venta en curso en vez de perderla en silencio.
//
// wire:navigate NO usa la navegacion nativa del <a> (esa si respeta
// event.preventDefault() en 'click'): Livewire/Alpine disparan el cambio
// de pagina por su cuenta desde mousedown/mouseup, sin mirar el 'click'
// para nada. El unico gancho que realmente frena esa navegacion es el
// evento cancelable 'livewire:navigate', que Livewire dispara justo antes
// de intercambiar el DOM (ver navigateTo() + fireEventForOtherLibrariesToHookInto
// en vendor/livewire/livewire/dist/livewire.esm.js). Un listener de 'click'
// aqui (como habia antes) nunca alcanza a frenarlo.
document.addEventListener('livewire:navigate', (event) => {
    const destination = event.detail?.url;
    if (!destination) return;

    const homeLinks = Array.from(document.querySelectorAll('[data-app-home-link]'));
    const isHomeDestination = homeLinks.some((link) => link.href === destination.href);
    if (!isHomeDestination) return;

    const shell = document.querySelector('[data-pos-shell]');
    if (!shell) return;

    const wire = findPosWire(shell);
    if (!wire) return;

    const items = Array.isArray(wire.items) ? wire.items : [];
    const hasProducts = items.some((item) => item?.product_id);
    if (!hasProducts) return;

    event.preventDefault();
    window.posPendingHomeHref = destination.href;
    window.dispatchEvent(new CustomEvent('pos-leave-guard-open'));
});

window.toastStack = (sessionToast = null) => ({
    toasts: [],
    nextId: 0,
    duration: window.retailSaas.toastDuration,
    init() {
        if (sessionToast?.message) {
            this.push(sessionToast);
        }
    },
    push(toast) {
        const normalized = {
            id: ++this.nextId,
            type: toast?.type ?? 'success',
            title: toast?.title ?? null,
            message: toast?.message ?? '',
            duration: toast?.duration ?? this.duration,
            visible: false,
        };

        if (! normalized.message) {
            return;
        }

        this.toasts.unshift(normalized);
        requestAnimationFrame(() => {
            const item = this.toasts.find((entry) => entry.id === normalized.id);

            if (item) {
                item.visible = true;
            }
        });

        window.setTimeout(() => this.dismiss(normalized.id), normalized.duration);
    },
    dismiss(id) {
        const toast = this.toasts.find((entry) => entry.id === id);

        if (! toast) {
            return;
        }

        toast.visible = false;
        window.setTimeout(() => {
            this.toasts = this.toasts.filter((entry) => entry.id !== id);
        }, 220);
    },
    toastTheme(type) {
        return {
            success: 'border-l-emerald-500 bg-emerald-50/95 text-emerald-900',
            error: 'border-l-rose-500 bg-rose-50/95 text-rose-900',
            warning: 'border-l-amber-500 bg-amber-50/95 text-amber-900',
            info: 'border-l-sky-500 bg-sky-50/95 text-sky-900',
        }[type] ?? 'border-l-stone-400 bg-white/95 text-stone-900';
    },
    toastIconTheme(type) {
        return {
            success: 'bg-emerald-500 text-white',
            error: 'bg-rose-500 text-white',
            warning: 'bg-amber-500 text-white',
            info: 'bg-sky-500 text-white',
        }[type] ?? 'bg-stone-500 text-white';
    },
});

const moneyFieldPattern = /(amount|price|cost|total|subtotal|discount|balance|credit|cash|opening|paid|change|equivalent|limit)/i;
// editLimits: plan limits (max_users, max_products, max_cash_registers, ...) are
// plain integer counts, not currency, but their keys collide with the
// "limit"/"cash" money keywords above.
const excludedMoneyFieldPattern = /(quantity|tax_rate|points|stock|minimum|base_quantity|rate|percentage|editlimits)/i;

function moneyInputKey(input) {
    return [
        input.getAttribute('wire:model'),
        input.getAttribute('wire:model.live'),
        input.getAttribute('wire:model.blur'),
        input.getAttribute('wire:model.lazy'),
        input.name,
        input.id,
    ].filter(Boolean).join(' ');
}

function isMoneyInput(input) {
    if (!(input instanceof HTMLInputElement)) {
        return false;
    }

    // Un checkbox/radio nunca es un campo de dinero — sin este guard, el
    // detector por nombre (ve mas abajo) puede hacer match por texto (ej:
    // un futuro checkbox llamado "ruleRequiresCashPayment" contiene "cash")
    // y forzar type="text" sobre un checkbox real, rompiendolo visualmente.
    if (input.type === 'checkbox' || input.type === 'radio') {
        return false;
    }

    const key = moneyInputKey(input);

    if (! key) {
        return false;
    }

    return moneyFieldPattern.test(key) && ! excludedMoneyFieldPattern.test(key);
}

function moneyDigits(value) {
    return String(value ?? '').replace(/\D+/g, '');
}

function formatMoneyDigits(value) {
    const digits = moneyDigits(value).replace(/^0+(?=\d)/, '');

    if (! digits) {
        return '';
    }

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function prepareMoneyInput(input) {
    if (! isMoneyInput(input) || input.dataset.moneyPrepared === '1') {
        return;
    }

    input.dataset.moneyPrepared = '1';
    input.dataset.moneyInput = '1';
    input.type = 'text';
    input.inputMode = 'numeric';
    input.autocomplete = 'off';

    if (input.value !== '') {
        input.value = formatMoneyDigits(input.value);
    }
}

function prepareMoneyInputs(root = document) {
    root.querySelectorAll('input').forEach((input) => prepareMoneyInput(input));
}

function digitsBeforeCursor(value, cursorPos) {
    return moneyDigits(value.slice(0, cursorPos)).length;
}

function cursorPositionAfterDigits(formattedValue, digitCount) {
    if (digitCount <= 0) {
        return 0;
    }

    let digitsSeen = 0;

    for (let i = 0; i < formattedValue.length; i++) {
        if (/\d/.test(formattedValue[i])) {
            digitsSeen++;

            if (digitsSeen === digitCount) {
                return i + 1;
            }
        }
    }

    return formattedValue.length;
}

document.addEventListener('input', (event) => {
    const input = event.target;

    if (! (input instanceof HTMLInputElement)) {
        return;
    }

    prepareMoneyInput(input);

    if (input.dataset.moneyInput !== '1') {
        return;
    }

    const rawValue = input.value;
    const cursorPos = input.selectionStart ?? rawValue.length;
    const digitsBefore = digitsBeforeCursor(rawValue, cursorPos);

    const formattedValue = formatMoneyDigits(rawValue);

    if (input.value !== formattedValue) {
        input.value = formattedValue;
        const newPos = cursorPositionAfterDigits(formattedValue, digitsBefore);
        input.setSelectionRange(newPos, newPos);
    }
});

document.addEventListener('focusin', (event) => {
    const input = event.target;

    if (! (input instanceof HTMLInputElement)) {
        return;
    }

    prepareMoneyInput(input);
});

prepareMoneyInputs();

const moneyInputObserver = new MutationObserver(() => prepareMoneyInputs());
moneyInputObserver.observe(document.body, { childList: true, subtree: true });
