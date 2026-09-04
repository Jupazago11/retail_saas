<div
    x-data="diningFloorPlanEditor({
        branchId: @js($branchId),
        outline: @js($floorPlan?->outline_points ?? []),
        tables: @js($tables->map(fn ($table) => [
            'id' => $table->id,
            'name' => $table->name,
            'capacity' => $table->capacity,
            'shape' => $table->shape,
            'size' => (float) $table->size,
            'height' => (float) ($table->height ?? $table->size),
            'x' => (float) ($table->pos_x ?? 50),
            'y' => (float) ($table->pos_y ?? 50),
        ])),
        registers: @js($cashRegisters->map(fn ($register) => [
            'id' => $register->id,
            'name' => $register->name,
            'placed' => $register->pos_x !== null,
            'size' => (float) $register->size,
            'x' => (float) ($register->pos_x ?? 50),
            'y' => (float) ($register->pos_y ?? 50),
        ])),
        obstacles: @js($obstacles->map(fn ($obstacle) => [
            'id' => $obstacle->id,
            'width' => (float) $obstacle->width,
            'height' => (float) $obstacle->height,
            'x' => (float) $obstacle->pos_x,
            'y' => (float) $obstacle->pos_y,
        ])),
        obstacleColor: @js($obstacleColor),
    })"
    x-on:mousemove.window="onMouseMove($event)"
    x-on:mouseup.window="onMouseUp()"
    wire:key="floor-plan-{{ $branchId }}"
    class="space-y-6"
>
    @if ($branches->count() > 1)
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="branchId" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{--
        wire:ignore en todo este bloque: outline/tables/registers ya viven
        100% del lado de Alpine despues del montaje inicial (createTable,
        updateTablePosition y deleteTable actualizan el arreglo de JS
        directamente con la respuesta del servidor; "Guardar plano" no
        necesita reflejar nada de vuelta). Sin wire:ignore, cada llamada a
        $wire.* dispara igual un re-render de Livewire que intenta
        remorfear este HTML — y el x-for anidado de las sillas (mesa >
        silla) no sobrevive ese remorfeo: Alpine pierde el scope de la
        iteracion interna y tira "chair is not defined"/"table is not
        defined" en consola, dejando ademas de responder al arrastre.
        wire:key en el div raiz sigue forzando un remontaje completo (no
        un morph) al cambiar de sucursal, asi que ese caso no se ve
        afectado por el wire:ignore de aca adentro.
    --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]" wire:ignore>
        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="relative aspect-square w-full overflow-hidden rounded-lg bg-gray-50 ring-1 ring-inset ring-gray-200"
                x-ref="canvas">
                <!--
                    x-if/x-for sobre <template> dentro de <svg> no son confiables: el
                    parser HTML no le da a ese <template> el .content especial que
                    Alpine necesita para clonar (mas notorio todavia al insertar via
                    morph, como hace wire:navigate) — el sintoma es un error en
                    consola "cloneNode of undefined" y nada se dibuja. El contorno
                    (un solo binding :points, sin template) se queda en el svg; los
                    puntos y las mesas, que si necesitan x-for, se dibujan como
                    <div> normales superpuestos por porcentaje. La capa que los
                    contiene es pointer-events-none (solo cada marca individual es
                    pointer-events-auto) para no tapar los clics del svg de abajo.
                -->
                <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full select-none" preserveAspectRatio="xMidYMid meet">
                    <rect x="0" y="0" width="100" height="100" fill="transparent"
                        x-on:click="canvasClick($event)"></rect>

                    <polygon x-show="outline.length >= 2" :points="polygonPoints()" fill="#2563eb14" stroke="#2563eb" stroke-width="0.6"></polygon>
                </svg>

                <div class="pointer-events-none absolute inset-0">
                    <template x-for="(point, index) in outline" :key="index">
                        <div class="pointer-events-auto absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full border-2 border-white bg-blue-600 shadow"
                            :style="`left: ${point.x}%; top: ${point.y}%`"
                            x-on:mousedown.stop="startDragPoint(index)"></div>
                    </template>

                    <template x-for="table in tables" :key="'chairs-' + table.id">
                        <template x-for="(chair, chairIndex) in chairPositions(table)" :key="chairIndex">
                            <div class="pointer-events-none absolute h-[3.6%] w-[3.6%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-blue-400 bg-blue-200"
                                :style="`left: ${chair.x}%; top: ${chair.y}%`"></div>
                        </template>
                    </template>

                    <template x-for="table in tables" :key="table.id">
                        <div class="pointer-events-auto absolute flex -translate-x-1/2 -translate-y-1/2 cursor-move items-center justify-center overflow-hidden border-2 border-blue-300 bg-white text-[11px] font-bold leading-none text-blue-800 shadow-sm"
                            :class="table.shape === 'round' ? 'rounded-full' : 'rounded-md'"
                            :style="`left: ${table.x}%; top: ${table.y}%; width: ${table.size}%; height: ${table.height}%`"
                            x-on:mousedown.stop="startDragTable(table.id)">
                            <span class="pointer-events-none" x-text="table.name"></span>
                            <div class="pointer-events-auto absolute -bottom-1 -right-1 h-3 w-3 cursor-nwse-resize rounded-full border-2 border-white bg-blue-600"
                                x-on:mousedown.stop="startResizeTable(table.id)"></div>
                        </div>
                    </template>

                    <template x-for="obstacle in obstacles" :key="'obstacle-' + obstacle.id">
                        <div class="pointer-events-auto absolute -translate-x-1/2 -translate-y-1/2 cursor-move rounded-sm border-2 border-neutral-950"
                            :style="`left: ${obstacle.x}%; top: ${obstacle.y}%; width: ${obstacle.width}%; height: ${obstacle.height}%; background-color: ${obstacleColor}`"
                            x-on:mousedown.stop="startDragObstacle(obstacle.id)">
                            <div class="pointer-events-auto absolute -bottom-1 -right-1 h-3 w-3 cursor-nwse-resize rounded-full border-2 border-white bg-neutral-950"
                                x-on:mousedown.stop="startResizeObstacle(obstacle.id)"></div>
                        </div>
                    </template>

                    <template x-for="register in registers" :key="register.id">
                        <div x-show="register.placed"
                            class="pointer-events-auto absolute flex -translate-x-1/2 -translate-y-1/2 cursor-move items-center justify-center rounded-lg border-2 border-white bg-slate-800 text-sm font-black text-white shadow"
                            :style="`left: ${register.x}%; top: ${register.y}%; width: ${register.size}%; height: ${register.size}%`"
                            :title="register.name"
                            x-on:mousedown.stop="startDragRegister(register.id)">
                            <span class="pointer-events-none">$</span>
                            <div class="pointer-events-auto absolute -bottom-1 -right-1 h-3 w-3 cursor-nwse-resize rounded-full border-2 border-white bg-slate-600"
                                x-on:mousedown.stop="startResizeRegister(register.id)"></div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                <span x-text="outline.length + ' punto(s)'"></span>
                <div class="flex gap-3">
                    <button type="button" x-on:click="outline.pop()" class="font-semibold text-gray-600 hover:text-gray-900">Deshacer ultimo punto</button>
                    <button type="button" x-on:click="outline = []" class="font-semibold text-rose-600 hover:text-rose-700">Limpiar contorno</button>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-gray-900">Mesas</h3>
                    <button type="button" x-on:click="addTable()"
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-lg font-semibold leading-none text-white hover:bg-blue-700">
                        +
                    </button>
                </div>
                <ul class="mt-3 max-h-[30vh] space-y-2 overflow-y-auto pr-1">
                    <template x-for="table in tables" :key="table.id">
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                            <span class="font-semibold text-gray-800" x-text="table.name"></span>
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="toggleShape(table.id)"
                                    class="rounded-full border border-gray-300 px-2 py-0.5 text-xs font-semibold text-gray-600 hover:bg-white">
                                    <span x-text="table.shape === 'round' ? 'Redonda' : 'Cuadrada'"></span>
                                </button>
                                <button type="button" x-on:click="removeTable(table.id)" title="Quitar mesa"
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-rose-500 hover:bg-rose-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    </template>
                    <li x-show="tables.length === 0" class="text-xs text-gray-400">Todavia no hay mesas. Usa el boton "+".</li>
                </ul>
            </div>

            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-black text-gray-900">Obstaculos</h3>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="obstacleColor" x-on:change="saveObstacleColor()"
                            title="Color de los obstaculos"
                            class="h-7 w-7 cursor-pointer rounded border border-gray-300 p-0.5">
                        <button type="button" x-on:click="addObstacle()"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-neutral-700 text-lg font-semibold leading-none text-white hover:bg-neutral-800">
                            +
                        </button>
                    </div>
                </div>
                <ul class="mt-3 max-h-[30vh] space-y-2 overflow-y-auto pr-1">
                    <template x-for="(obstacle, index) in obstacles" :key="'obstacle-li-' + obstacle.id">
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                            <span class="font-semibold text-gray-800" x-text="'Obstaculo ' + (index + 1)"></span>
                            <button type="button" x-on:click="removeObstacle(obstacle.id)" title="Quitar obstaculo"
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-rose-500 hover:bg-rose-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </li>
                    </template>
                    <li x-show="obstacles.length === 0" class="text-xs text-gray-400">Todavia no hay obstaculos. Usa el boton "+".</li>
                </ul>
            </div>

            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <h3 class="text-sm font-black text-gray-900">Cajas</h3>
                <ul class="mt-3 max-h-[30vh] space-y-2 overflow-y-auto pr-1">
                    <template x-for="register in registers" :key="register.id">
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm">
                            <span class="font-semibold text-gray-800" x-text="register.name"></span>
                            <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600">
                                <input type="checkbox" x-model="register.placed"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                                En el plano
                            </label>
                        </li>
                    </template>
                    <li x-show="registers.length === 0" class="text-xs text-gray-400">Esta sucursal no tiene cajas creadas.</li>
                </ul>
            </div>

            <button type="button" x-on:click="submit()"
                class="w-full rounded-full bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                Guardar plano
            </button>
        </div>
    </div>
</div>
