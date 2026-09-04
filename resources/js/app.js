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

// Si el usuario escribio todo en minuscula ("bebida"), se ve descuidado en
// tablas/listas ("Bebida" es lo esperado). Solo toca el texto cuando NO hay
// ninguna mayuscula en absoluto: si ya escribieron algo con mayusculas a
// proposito (siglas, "iPhone", etc.) se respeta tal cual, sin tocar el resto
// de las palabras (solo la primera letra).
function capitalizeIfAllLowercase(text) {
    if (typeof text !== 'string' || text === '' || text !== text.toLowerCase()) {
        return text;
    }

    return text.replace(/\p{L}/u, (letter) => letter.toUpperCase());
}
window.retailSaas.capitalizeIfAllLowercase = capitalizeIfAllLowercase;

// fetch() liso con el CSRF token de la pagina, usado por el editor del
// plano de mesas (diningFloorPlanEditor mas abajo) para guardar
// crear/mover/eliminar una mesa sin pasar por una accion de Livewire.
async function csrfFetch(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
    });

    if (! response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.status === 204 ? null : response.json();
}

document.addEventListener('alpine:init', () => {
    Alpine.magic('capitalize', () => capitalizeIfAllLowercase);

    // Editor visual del plano del salon (dining-floor-plan-page.blade.php).
    // Vive aca (no inline en x-data="{...}") a proposito: ese atributo es un
    // valor HTML delimitado por comillas dobles, y una comilla doble suelta
    // dentro de CUALQUIER comentario del objeto corta el atributo a la mitad
    // (el resto se cae como texto suelto en la pagina y Alpine nunca llega a
    // inicializar `mode`/`tables`/etc.) — nos paso varias veces seguidas
    // editando esto inline. Aca adentro (contenido de <script>, no un
    // atributo) esa clase de bug es imposible.
    //
    // Crear/mover/eliminar una mesa o un obstaculo (addTable/addObstacle y
    // companeros mas abajo, via csrfFetch) NO pasan por $wire ni por
    // ninguna accion de Livewire: cada accion de Livewire dispara un
    // re-render + remorfeo de TODO el componente, y el x-for anidado de las
    // sillas (mesa > silla) no sobrevivia ese remorfeo de forma confiable en
    // cada interaccion real de navegador (ni siquiera con wire:ignore en el
    // contenedor: una mesa creada DESPUES del montaje inicial quedaba sin
    // poder arrastrarse hasta refrescar la pagina). Un fetch() liso contra
    // DiningFloorPlanTablesController/DiningFloorPlanObstaclesController
    // nunca toca el ciclo de Livewire, asi que ese problema no puede volver
    // a pasar por esta via. El tamaño (ancho/alto de una mesa cuadrada, o
    // de cualquier obstaculo) se sigue guardando solo con "Guardar plano"
    // (submit(), accion de Livewire de siempre — nunca causo el bug de
    // arriba porque no crea elementos nuevos, solo ajusta los existentes).
    Alpine.data('diningFloorPlanEditor', ({ branchId, outline, tables, registers, obstacles, obstacleColor }) => ({
        branchId,
        outline,
        tables,
        registers,
        obstacles,
        obstacleColor,
        dragging: null,
        justDragged: false,
        // Tamaño/forma de la PROXIMA mesa a crear: arrancan igual a la
        // ultima mesa existente y se actualizan cada vez que el dueño
        // redimensiona (onMouseUp) o cambia la forma (toggleShape) una
        // mesa — asi, si quiere todas redondas, solo cambia la primera y
        // el resto que cree con "+" ya nacen redondas, sin repetir el
        // cambio mesa por mesa.
        defaultTableSize: tables.length ? Math.max(...tables.map((t) => t.size)) : 8,
        defaultTableHeight: tables.length ? tables[tables.length - 1].height : 8,
        defaultTableShape: tables.length ? tables[tables.length - 1].shape : 'square',

        // Sillas alrededor de la mesa, repartidas en circulo segun la
        // capacidad (4 por defecto si no se definio). Puramente visual. La
        // distancia es el radio de la mesa en cada eje por separado (no un
        // solo radio): en una mesa rectangular, un solo radio dejaria las
        // sillas flotando lejos del lado corto o encimadas en el largo.
        chairPositions(table) {
            const count = table.capacity && table.capacity > 0 ? Math.min(table.capacity, 12) : 4;
            const distanceX = table.size / 2;
            const distanceY = table.height / 2;
            const chairs = [];

            for (let i = 0; i < count; i++) {
                const angle = ((2 * Math.PI) / count) * i - (Math.PI / 2);
                chairs.push({
                    x: table.x + Math.cos(angle) * distanceX,
                    y: table.y + Math.sin(angle) * distanceY,
                });
            }

            return chairs;
        },

        svgPoint(event) {
            const rect = this.$refs.canvas.getBoundingClientRect();
            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            const clientY = event.touches ? event.touches[0].clientY : event.clientY;
            const x = ((clientX - rect.left) / rect.width) * 100;
            const y = ((clientY - rect.top) / rect.height) * 100;

            return {
                x: Math.min(100, Math.max(0, Math.round(x * 100) / 100)),
                y: Math.min(100, Math.max(0, Math.round(y * 100) / 100)),
            };
        },

        // Ajusta un punto para que la linea hacia `anchor` quede horizontal
        // o vertical (la que este mas cerca de lo que realmente se movio),
        // asi las paredes del salon salen rectas sin necesidad de pulso.
        snapToAxis(point, anchor) {
            const dx = Math.abs(point.x - anchor.x);
            const dy = Math.abs(point.y - anchor.y);

            return dx > dy ? { x: point.x, y: anchor.y } : { x: anchor.x, y: point.y };
        },

        canvasClick(event) {
            // Un mousedown+drag+mouseup termina disparando igual un evento
            // click final (aunque haya movido el mouse) — sin este guard,
            // soltar un punto arrastrado o una mesa sobre el lienzo vacio
            // agregaria un punto nuevo justo ahi por accidente.
            if (this.justDragged) return;

            let point = this.svgPoint(event);
            if (this.outline.length > 0) {
                point = this.snapToAxis(point, this.outline[this.outline.length - 1]);
            }
            this.outline.push(point);
        },

        startDragPoint(index) {
            const n = this.outline.length;
            const current = this.outline[index];
            const prevIndex = n > 1 ? (index - 1 + n) % n : null;
            const nextIndex = n > 2 ? (index + 1) % n : null;

            // Al arrastrar una esquina, las dos paredes que llegan a ella se
            // mantienen rectas moviendo la coordenada compartida del punto
            // vecino correspondiente (igual que redimensionar un rectangulo
            // arrastrando una esquina).
            this.dragging = {
                type: 'point',
                index: index,
                moved: false,
                prevIndex: (prevIndex !== null && prevIndex !== index) ? prevIndex : null,
                nextIndex: (nextIndex !== null && nextIndex !== index && nextIndex !== prevIndex) ? nextIndex : null,
                prevHorizontal: prevIndex !== null ? Math.abs(this.outline[prevIndex].y - current.y) <= Math.abs(this.outline[prevIndex].x - current.x) : null,
                nextHorizontal: nextIndex !== null ? Math.abs(this.outline[nextIndex].y - current.y) <= Math.abs(this.outline[nextIndex].x - current.x) : null,
            };
        },

        startDragTable(id) {
            this.dragging = { type: 'table', id: id, moved: false };
        },

        startResizeTable(id) {
            this.dragging = { type: 'resize', id: id, moved: false };
        },

        startDragRegister(id) {
            this.dragging = { type: 'register', id: id, moved: false };
        },

        startResizeRegister(id) {
            this.dragging = { type: 'resize-register', id: id, moved: false };
        },

        startDragObstacle(id) {
            this.dragging = { type: 'obstacle', id: id, moved: false };
        },

        startResizeObstacle(id) {
            this.dragging = { type: 'resize-obstacle', id: id, moved: false };
        },

        onMouseMove(event) {
            if (! this.dragging) return;
            this.dragging.moved = true;
            const point = this.svgPoint(event);

            if (this.dragging.type === 'point') {
                this.outline[this.dragging.index] = point;

                if (this.dragging.prevIndex !== null) {
                    if (this.dragging.prevHorizontal) {
                        this.outline[this.dragging.prevIndex].y = point.y;
                    } else {
                        this.outline[this.dragging.prevIndex].x = point.x;
                    }
                }

                if (this.dragging.nextIndex !== null) {
                    if (this.dragging.nextHorizontal) {
                        this.outline[this.dragging.nextIndex].y = point.y;
                    } else {
                        this.outline[this.dragging.nextIndex].x = point.x;
                    }
                }
            } else if (this.dragging.type === 'table') {
                const table = this.tables.find((t) => t.id === this.dragging.id);
                if (table) {
                    table.x = point.x;
                    table.y = point.y;
                }
            } else if (this.dragging.type === 'resize') {
                const table = this.tables.find((t) => t.id === this.dragging.id);
                if (table) {
                    // Redonda: un solo radio, sigue siendo circulo perfecto.
                    // Cuadrada: ancho y alto se sueltan por separado, asi
                    // arrastrar la esquina la puede dejar rectangular.
                    if (table.shape === 'round') {
                        const half = Math.max(point.x - table.x, point.y - table.y, 2);
                        const size = Math.min(20, Math.max(4, Math.round(half * 2 * 100) / 100));
                        table.size = size;
                        table.height = size;
                    } else {
                        const halfW = Math.max(point.x - table.x, 2);
                        const halfH = Math.max(point.y - table.y, 2);
                        table.size = Math.min(20, Math.max(4, Math.round(halfW * 2 * 100) / 100));
                        table.height = Math.min(20, Math.max(4, Math.round(halfH * 2 * 100) / 100));
                    }
                }
            } else if (this.dragging.type === 'register') {
                const register = this.registers.find((r) => r.id === this.dragging.id);
                if (register) {
                    register.x = point.x;
                    register.y = point.y;
                }
            } else if (this.dragging.type === 'resize-register') {
                const register = this.registers.find((r) => r.id === this.dragging.id);
                if (register) {
                    const half = Math.max(point.x - register.x, point.y - register.y, 2);
                    register.size = Math.min(20, Math.max(4, Math.round(half * 2 * 100) / 100));
                }
            } else if (this.dragging.type === 'obstacle') {
                const obstacle = this.obstacles.find((o) => o.id === this.dragging.id);
                if (obstacle) {
                    obstacle.x = point.x;
                    obstacle.y = point.y;
                }
            } else if (this.dragging.type === 'resize-obstacle') {
                const obstacle = this.obstacles.find((o) => o.id === this.dragging.id);
                if (obstacle) {
                    const halfW = Math.max(point.x - obstacle.x, 1);
                    const halfH = Math.max(point.y - obstacle.y, 1);
                    obstacle.width = Math.min(60, Math.max(2, Math.round(halfW * 2 * 100) / 100));
                    obstacle.height = Math.min(60, Math.max(2, Math.round(halfH * 2 * 100) / 100));
                }
            }
        },

        onMouseUp() {
            if (! this.dragging) return;

            // Click sin arrastre sobre un punto = borrarlo. Si hubo
            // movimiento, el punto ya quedo reposicionado y no se borra.
            if (this.dragging.type === 'point' && ! this.dragging.moved) {
                this.outline.splice(this.dragging.index, 1);
            }

            // El tamaño que quede tras redimensionar una mesa se vuelve el
            // tamaño por defecto de las siguientes mesas que se agreguen.
            if (this.dragging.type === 'resize' && this.dragging.moved) {
                const table = this.tables.find((t) => t.id === this.dragging.id);
                if (table) {
                    this.defaultTableSize = table.size;
                    this.defaultTableHeight = table.height;
                }
            }

            // Mover una mesa o un obstaculo se guarda solo al soltarlo (no
            // en cada mousemove) — a diferencia del contorno, el
            // redimensionado y las cajas, que siguen quedando pendientes
            // hasta pulsar "Guardar plano".
            if (this.dragging.type === 'table' && this.dragging.moved) {
                const table = this.tables.find((t) => t.id === this.dragging.id);
                if (table) {
                    csrfFetch(`/dining/floor-plan/tables/${table.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ x: table.x, y: table.y }),
                    }).catch(() => alert('No se pudo guardar la posicion de la mesa. Intenta de nuevo.'));
                }
            }

            if (this.dragging.type === 'obstacle' && this.dragging.moved) {
                const obstacle = this.obstacles.find((o) => o.id === this.dragging.id);
                if (obstacle) {
                    csrfFetch(`/dining/floor-plan/obstacles/${obstacle.id}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ x: obstacle.x, y: obstacle.y }),
                    }).catch(() => alert('No se pudo guardar la posicion del obstaculo. Intenta de nuevo.'));
                }
            }

            if (this.dragging.moved) {
                this.justDragged = true;
                setTimeout(() => { this.justDragged = false; }, 0);
            }

            this.dragging = null;
        },

        toggleShape(id) {
            const table = this.tables.find((t) => t.id === id);
            if (table) {
                table.shape = table.shape === 'round' ? 'square' : 'round';
                // Redonda siempre uniforme: una mesa rectangular que se
                // vuelve redonda pierde el estiramiento (una "redonda
                // rectangular" no tiene sentido visual).
                if (table.shape === 'round') {
                    table.height = table.size;
                }
                this.defaultTableShape = table.shape;
                this.defaultTableHeight = table.height;
            }
        },

        // Quitar una mesa la archiva de inmediato en el servidor (no solo
        // al guardar el plano) — la respuesta trae la lista ya renumerada,
        // asi los numeros de las mesas restantes quedan correctos al
        // instante en pantalla.
        async removeTable(id) {
            if (! confirm('¿Quitar esta mesa del plano?')) return;

            try {
                this.tables = await csrfFetch(`/dining/floor-plan/tables/${id}`, { method: 'DELETE' });
            } catch (error) {
                alert('No se pudo quitar la mesa. Intenta de nuevo.');
            }
        },

        // Crear una mesa tambien queda persistida de inmediato — el
        // servidor calcula el numero real (siguiente libre) y devuelve la
        // mesa ya creada, asi que aca no se adivina ningun id ni nombre.
        //
        // La posicion inicial se escalona en una cuadricula (no siempre el
        // mismo 50,50 del centro): crear varias mesas seguidas sin moverlas
        // las apilaba exactas una sobre otra, asi que solo la de encima
        // quedaba visible/arrastrable — parecia que las demas "no se
        // dejaban mover" cuando en realidad estaban escondidas debajo.
        async addTable() {
            const index = this.tables.length;
            const x = Math.min(80, 20 + (index % 5) * 14);
            const y = Math.min(80, 20 + Math.floor(index / 5) * 14);

            try {
                const created = await csrfFetch('/dining/floor-plan/tables', {
                    method: 'POST',
                    body: JSON.stringify({
                        branch_id: this.branchId,
                        x,
                        y,
                        shape: this.defaultTableShape,
                        size: this.defaultTableSize,
                        height: this.defaultTableHeight,
                    }),
                });
                this.tables.push(created);
            } catch (error) {
                alert('No se pudo crear la mesa. Intenta de nuevo.');
            }
        },

        // Igual que addTable(): queda creado en el servidor de inmediato y
        // escalonado en cuadricula para no apilarse con obstaculos previos.
        async addObstacle() {
            const index = this.obstacles.length;
            const x = Math.min(80, 20 + (index % 5) * 14);
            const y = Math.min(80, 55 + Math.floor(index / 5) * 14);

            try {
                const created = await csrfFetch('/dining/floor-plan/obstacles', {
                    method: 'POST',
                    body: JSON.stringify({ branch_id: this.branchId, x, y }),
                });
                this.obstacles.push(created);
            } catch (error) {
                alert('No se pudo crear el obstaculo. Intenta de nuevo.');
            }
        },

        async removeObstacle(id) {
            if (! confirm('¿Quitar este obstaculo del plano?')) return;

            try {
                await csrfFetch(`/dining/floor-plan/obstacles/${id}`, { method: 'DELETE' });
                this.obstacles = this.obstacles.filter((o) => o.id !== id);
            } catch (error) {
                alert('No se pudo quitar el obstaculo. Intenta de nuevo.');
            }
        },

        // Un solo color por empresa (no por obstaculo) — se guarda al
        // cerrar el selector nativo (evento "change", no "input", para no
        // mandar una peticion por cada pixel de matiz que el usuario
        // arrastra dentro del selector).
        saveObstacleColor() {
            csrfFetch('/dining/floor-plan/obstacle-color', {
                method: 'PATCH',
                body: JSON.stringify({ color: this.obstacleColor }),
            }).catch(() => alert('No se pudo guardar el color de los obstaculos. Intenta de nuevo.'));
        },

        polygonPoints() {
            return this.outline.map((p) => p.x + ',' + p.y).join(' ');
        },

        submit() {
            this.$wire.call('save', this.outline, this.tables.map((t) => ({
                id: t.id,
                name: t.name,
                capacity: t.capacity,
                shape: t.shape,
                size: t.size,
                height: t.height,
                x: t.x,
                y: t.y,
            })), this.registers.map((r) => ({
                id: r.id,
                placed: r.placed,
                size: r.size,
                x: r.x,
                y: r.y,
            })), this.obstacles.map((o) => ({
                id: o.id,
                width: o.width,
                height: o.height,
            })));
        },
    }));

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
            const digits = event.target.value.replace(/\D+/g, '').replace(/^0+(?=\d)/, '');
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
    // null (valor inicial de una prop PHP sin seleccion), undefined y '' se
    // tratan como "lo mismo: nada seleccionado" — ver el porque en close().
    const isBlankSelection = (value) => value === null || value === undefined || value === '';

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
                const value = capitalizeIfAllLowercase(trimmed);

                return [...base, { id: value, label: 'Crear "' + value + '"', __create: true }];
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
            // No reasignar si ya es el mismo valor: en un campo .live
            // (ej. filtro "Marca" fuera de un modal), CUALQUIER asignacion a
            // `selected` -aunque sea al mismo valor- dispara un commit en
            // vivo. Si ese commit trae un valor "" (cadena vacia), un bug de
            // Livewire en mergeQueuedUpdates() (diffKey.startsWith("") es
            // SIEMPRE true) borra TODOS los demas cambios pendientes de ese
            // mismo request -incluyendo lo que se acaba de escribir en OTROS
            // campos del formulario, aunque no tengan relacion alguna-.
            if (!(isBlankSelection(this.selected) && isBlankSelection(option.id)) && this.selected !== option.id) {
                this.selected = option.id;
            }
            this.query = option.__create ? option.id : option.label;
            this.open = false;
            this.touched = false;
            this.highlighted = -1;
        },
        close() {
            this.open = false;

            const trimmed = this.query.trim();
            let next = this.selected;

            if (trimmed === '') {
                next = '';
            } else if (this.allowCreate) {
                const exact = this.findExact(trimmed);
                next = exact ? exact.id : capitalizeIfAllLowercase(trimmed);
            }

            // Ver el comentario en choose() — evitar reasignar al mismo
            // valor (tratando null/undefined/'' como equivalentes) es lo
            // que evita el commit-en-vivo espurio.
            if (!(isBlankSelection(this.selected) && isBlankSelection(next)) && next !== this.selected) {
                this.selected = next;
            }

            this.syncFromSelected();
        },
        clear() {
            if (!isBlankSelection(this.selected)) {
                this.selected = '';
            }
            this.query = '';
            this.open = false;
            this.touched = false;
            this.highlighted = -1;
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
// Expuesto para el guard de beforeunload (F5/cerrar pestaña) en
// pos-page.blade.php — mismo localizador de $wire que usa el guard de
// "click en el logo" de arriba, sin duplicar la logica de busqueda.
window.retailSaas.findPosWire = findPosWire;

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
            actionUrl: toast?.actionUrl ?? null,
            actionLabel: toast?.actionLabel ?? null,
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
