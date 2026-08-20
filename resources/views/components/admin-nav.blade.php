@props(['active' => ''])

@php
    $canManageSettings = auth()->user()?->hasCurrentCompanyPermission('settings.manage') ?? false;
    $canManageRoles = auth()->user()?->hasCurrentCompanyPermission('roles.manage') ?? false;
@endphp

{{--
    El resaltado del tab activo usa un prop explicito ($active), no
    request()->routeIs(): durante una peticion de Livewire (wire:click,
    wire:model.live, etc.) la ruta "actual" que ve el servidor es el
    endpoint interno de Livewire, no la pagina que el usuario esta viendo,
    asi que routeIs() se apaga apenas se interactua con cualquier filtro
    o accion (guardar, activar, etc.).
--}}
<div class="flex flex-wrap gap-2">
    @if ($canManageSettings)
        <a href="{{ route('admin.subscription') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-blue-600 text-white' => $active === 'admin.subscription',
            'border border-gray-300 text-gray-700 hover:border-stone-400' => $active !== 'admin.subscription',
        ])>
            Suscripcion
        </a>
    @endif

    {{-- "Paquetes" (admin.bundles) y "Cupones" (admin.coupons) se quitaron
    de esta barra: no forman parte de ningun plan/membresia actual. Las
    rutas y componentes siguen existiendo, solo se dejo de enlazarlos
    aqui. --}}

    {{-- "Excepciones" (admin.overrides) se oculto a proposito: se
    implementara mas adelante. La ruta y el componente siguen intactos,
    solo se dejo de enlazar aqui. --}}

    @if ($canManageSettings)
        <a href="{{ route('admin.structure') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-blue-600 text-white' => $active === 'admin.structure',
            'border border-gray-300 text-gray-700 hover:border-stone-400' => $active !== 'admin.structure',
        ])>
            Estructura
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.settings') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-blue-600 text-white' => $active === 'admin.settings',
            'border border-gray-300 text-gray-700 hover:border-stone-400' => $active !== 'admin.settings',
        ])>
            Configuracion
        </a>
    @endif

    @if ($canManageSettings)
        <a href="{{ route('admin.audit-logs') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-blue-600 text-white' => $active === 'admin.audit-logs',
            'border border-gray-300 text-gray-700 hover:border-stone-400' => $active !== 'admin.audit-logs',
        ])>
            Auditoria
        </a>
    @endif

    @if ($canManageRoles)
        <a href="{{ route('admin.roles') }}" wire:navigate @class([
            'rounded-full px-4 py-2 text-sm font-semibold transition',
            'bg-blue-600 text-white' => $active === 'admin.roles',
            'border border-gray-300 text-gray-700 hover:border-stone-400' => $active !== 'admin.roles',
        ])>
            Roles
        </a>
    @endif
</div>
