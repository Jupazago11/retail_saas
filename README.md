# Retail SaaS

SaaS multiempresa para tiendas, minimercados, supermercados pequenos y comercios minoristas con foco en compras, inventario, POS, caja, credito, fidelizacion, promociones y suscripciones.

## Stack

- PHP 8.4
- Laravel 13
- PostgreSQL
- Blade
- Livewire
- Tailwind CSS
- Docker Compose

## Documentacion principal

- [AGENTS.md](AGENTS.md)
- [docs/README.md](docs/README.md)
- [docs/arquitectura-saas.md](docs/arquitectura-saas.md)
- [docs/modelo-datos.md](docs/modelo-datos.md)
- [docs/decisiones-tecnicas.md](docs/decisiones-tecnicas.md)

## Arranque local

1. Copiar variables:

```bash
cp .env.example .env
```

2. Levantar servicios base:

```bash
docker compose up -d --build postgres mailpit app web
```

3. Ejecutar migraciones:

```bash
docker compose exec app php artisan migrate
```

Opcional: cargar catalogos y empresas demo:

```bash
docker compose exec app php artisan db:seed
```

4. Compilar assets con el contenedor Node 24:

```bash
docker compose run --rm --entrypoint /bin/sh vite -lc "npm install && npm run build"
```

5. Si se quiere desarrollo con recarga en caliente:

```bash
docker compose up vite
```

## Datos demo

El seeder principal ahora crea 3 usuarios/empresas demo con planes distintos:

- `demo.basic` / `demo.basic@retailsaas.test` / `password`
  Empresa: `Demo Basic Market`
  Plan: `basic`
- `demo.pro` / `demo.pro@retailsaas.test` / `password`
  Empresa: `Demo Pro Retail`
  Plan: `pro`
- `demo.premium` / `demo.premium@retailsaas.test` / `password`
  Empresa: `Demo Premium Commerce`
  Plan: `premium`

Ademas del tenant y plan, el seeding demo deja:

- Catalogo base por empresa con categorias, marcas, unidad y productos operativos.
- Proveedores, compras historicas y cuentas por pagar abiertas.
- Clientes compartidos entre empresas y clientes especificos por plan.
- Historial de ventas al contado para compradores recurrentes.
- Ventas a credito con casos de buen pagador, deuda vigente y mora en `pro`/`premium`.
- Deudores cruzados en mas de una empresa demo para probar cartera multi-tenant con datos mas realistas.
- Promociones activas en `pro` y `premium`, incluyendo combo fijo en `premium`.
- Saldos iniciales y redencion real de puntos de fidelizacion en `pro` y `premium`.
- Casos historicos de devolucion parcial, anulacion de venta y cierre de caja para probar reportes y conciliacion.

## Estado actual

- Bootstrap Laravel listo.
- Docker local propio creado.
- Nucleo tenant inicial modelado con `companies`, `branches`, `warehouses` y `cash_registers`.
- Autenticacion base instalada con Breeze + Livewire.
- Login ajustado a `username`.
- Registro redirige al selector de empresa.
- Selector de empresa activa y aprovisionamiento inicial funcionando.
- Primer bloque de maestras operativo con `categories`, `brands` y `units`.
- Catalogo base de `products` operativo con relacion a categoria, marca y unidad.
- Presentaciones de producto operativas con conversion exacta a unidad base.
- Atributos y valores de producto operativos por empresa.
- Variantes de producto operativas por combinacion de atributos.
- Notificaciones UI unificadas con `toast` global animado.
- Permisos tecnicos y politicas base operativos sobre el catalogo autenticado.
- Configuracion tipada por empresa disponible en backend y con UI administrativa inicial para editar parametros operativos por empresa.
- Roles empresariales ya cuentan con UI inicial para crear roles personalizados y asignarlos a usuarios de la empresa.
- Auditoria ya cuenta con consulta visual operativa por empresa, filtros y snapshots before/after desde UI interna.
- El modulo interno `Admin` ya agrupa configuracion, roles y auditoria con navegacion compartida y permisos separados.
- Backend base de compras operativo con lineas, totales y conversion a unidad base.
- Compras confirmadas y sus devoluciones ya postean a kardex y saldos con costo promedio base.
- Compras ya cuentan con base de cuentas por pagar, ledger de saldos y registro de pagos a proveedor por compra.
- UI operativa inicial de compras ya permite registrar documentos, lineas, pagos posteriores y devoluciones sobre el mismo flujo.
- Los borradores de compra ya pueden editarse antes del posting definitivo y luego confirmarse desde la misma UI.
- Proveedores formales ya cuentan con backend base enlazado a `people`, compras y plazos de pago.
- Cuentas por pagar ya soportan saldo a favor del proveedor por devoluciones pagadas y aplicacion posterior sobre compras pendientes, exigiendo referencia obligatoria al momento de aplicar ese credito para fortalecer conciliacion y auditoria.
- El backend ya expone consulta consolidada de movimientos financieros por proveedor para futuras vistas, reportes y auditoria operativa.
- El backend ya expone un resumen consolidado por proveedor con saldo abierto, saldo vigente, saldo vencido, buckets de aging, exposicion neta, credito disponible, ultimo movimiento y proximo vencimiento.
- UI operativa inicial de proveedores y cuentas por pagar ya permite mantener proveedores, consultar compras abiertas, sincronizar filtros por aging y saldo a favor entre resumen y detalle, ver resumen agregado por proveedor y aplicar saldo a favor manualmente.
- El ledger financiero por compra ya puede revisarse visualmente desde compras y cuentas por pagar.
- Ajustes de inventario ya postean entradas y salidas directas sobre el mismo kardex.
- Traslados entre bodegas ya postean salida en origen e ingreso en destino sobre el mismo kardex.
- Ventas confirmadas ya calculan totales, guardan snapshot de costo y descuentan inventario.
- Ventas ya generan numeracion documental interna secuencial por empresa para el flujo POS/facturacion normal interna, con prefijo y consecutivo inicial configurables desde Settings.
- Ventas ya cuentan con una primera UI operativa de consulta desde backoffice y acceso directo al ticket imprimible por documento.
- El modulo comercial ya cuenta con una primera UI POS para crear ventas `draft/confirmed`, cobrar ventas POS confirmadas con uno o varios pagos inmediatos y abrir el ticket tras confirmar.
- Los borradores de venta ya pueden reabrirse desde el listado, editarse en el POS y confirmarse despues sin perder su numero documental interno.
- Ventas congeladas ya pueden crearse, retomar su snapshot en formulario, cancelarse y convertirse a venta real desde una UI operativa sin tocar inventario hasta la conversion.
- Caja y pagos mixtos ya cuentan con sesiones de apertura/cierre, registro backend de pagos sobre ventas confirmadas y primer consumo operativo desde la UI POS.
- Caja ya cuenta tambien con una primera UI operativa propia para abrir y cerrar sesiones, revisar efectivo esperado, distinguir cierres cuadrados vs cerrados con diferencia y listar historial reciente.
- Los `company_settings` ya gobiernan reglas reales de caja y POS como apertura requerida, descuentos manuales y stock negativo en ventas.
- Anulaciones y devoluciones de venta ya restituyen inventario, actualizan estado de la venta y revierten pagos confirmados cuando aplica, incluyendo una primera operacion directa desde la UI de ventas y motivo obligatorio trazable en notas del documento.
- Clientes, cuentas de credito, ventas a credito y abonos ya cuentan con backend transaccional enlazado a ventas y caja, incluyendo validacion de contexto operativo para que el abono use una sesion coherente con la sucursal y caja de la venta.
- Credito ya cuenta con una primera UI operativa para consultar cartera por cliente/venta y registrar abonos sobre ventas a credito.
- Los `company_settings` ya gobiernan tambien reglas reales de credito, incluyendo el bloqueo de nuevos creditos cuando existe cartera vencida.
- Los `company_settings` ya gobiernan tambien una primera salida imprimible de ventas, incluyendo formato de ticket, logo y branding del SaaS.
- Fidelizacion ya cuenta con cuentas de puntos, redencion en venta, restauracion por devolucion o anulacion y expiracion FIFO configurable.
- Fidelizacion ya cuenta tambien con una primera UI operativa para consultar cuentas, revisar movimientos y ejecutar expiracion manual con base en la configuracion vigente.
- Fidelizacion ya permite tambien ajustes manuales de puntos a favor o en contra desde backoffice con trazabilidad en el ledger.
- Promociones por producto y combos a precio fijo ya cuentan con motor backend, snapshots por linea y resumen en `pricing_snapshot`.
- Promociones ya cuentan tambien con una primera UI operativa para crear, editar y archivar descuentos por producto y combos a precio fijo desde backoffice.
- El POS ya permite redimir puntos de fidelizacion directamente al confirmar una venta con cliente elegible.
- El POS ya muestra un preview comercial provisional con totales, promociones aplicadas, redencion proyectada y saldo por cobrar antes de confirmar.
- Reportes ya cuentan con una UI operativa ampliada con resumen de ventas, recaudo, cartera, fidelizacion, actividad promocional, aging de cartera, desglose por medio de pago y exportacion CSV.
- Auditoria ya permite tambien busqueda general, filtro por IP y exportacion CSV de eventos.
- Auditoria inicial backend ya registra eventos criticos con snapshots `before` y `after` sobre acciones operativas sensibles.
- Platform/SaaS ya cuenta con backend base para catalogo de planes, modulos, features, limites, bundles multiempresa, cupones y suscripciones.
- La creacion de empresa ya aprovisiona una suscripcion inicial `basic` en estado `trialing`, y el dominio ya puede resolver acceso efectivo por plan, bundle y overrides por empresa.
- El dominio operativo ya aplica enforcement real de plan sobre promociones, combos, fidelizacion, redencion de puntos, descuentos manuales y ventas congeladas.
- La creacion de nuevas empresas ya aplica el limite `max_companies` del plan efectivo del propietario y bloquea altas adicionales cuando se alcanza el cupo.
- La administracion de roles ya puede vincular usuarios existentes a la empresa por correo o `username`, aplicando el limite `max_users` antes de crear la membresia.
- La estructura operativa ya cuenta con una primera UI administrativa para crear sucursales, bodegas y cajas, aplicando los limites `max_branches`, `max_warehouses` y `max_cash_registers` del plan efectivo.
- El backoffice ya cuenta con una primera pantalla `Suscripcion` para inspeccionar el plan efectivo, revisar historial directo, ver bundles asociados y cambiar manualmente la suscripcion directa de la empresa.
- La pantalla `Suscripcion` ya permite tambien definir fecha efectiva, finalizar suscripciones directas y renovarlas manualmente con trazabilidad.
- El backoffice ya cuenta tambien con una pantalla `Overrides` para aplicar overrides manuales por empresa sobre modulos, features y limites con vigencia controlada.
- El backoffice ya cuenta tambien con una pantalla `Bundles` para crear, editar y consultar bundles asociados a la empresa activa, incluyendo plan asignado, descuento, cupo y empresas vinculadas.
- El backoffice ya cuenta tambien con una pantalla `Cupones` para crear, editar y revisar reglas comerciales, alcance por planes o bundles y redenciones recientes visibles para la empresa.
- El dominio operativo ya aplica tambien enforcement real de `max_products` al alta de catalogo y `max_monthly_sales` al confirmar ventas.
- El limite `max_electronic_documents` ya quedo preparado a nivel de guard de dominio para conectarlo cuando exista el flujo real de emision electronica.
- El modulo de productos ya cuenta con una primera importacion CSV por empresa con validacion parcial por fila, enforcement de plan sobre `imports.excel` y auditoria resumida por lote.
- Inventario ya cuenta con una primera importacion CSV de ajustes masivos, donde el contexto operativo se define en UI y el archivo solo aporta lineas de producto o variante.
- Compras ya cuenta con una primera importacion CSV de lineas sobre un solo documento, reutilizando posting a inventario y cuentas por pagar cuando el estado lo exige.
- Proveedores ya cuenta con una primera importacion CSV del maestro comercial con control de duplicados por documento o correo dentro de la empresa.
- Credito ya cuenta tambien con una primera importacion CSV de clientes, incluyendo alta opcional de cuentas de credito y fidelizacion por fila.
- Configuracion ya expone una primera base tipada de facturacion electronica para planes con modulo `electronic_billing`.

## Notificaciones UI

- El proyecto usa un `toast` global en esquina superior derecha, no mensajes estaticos embebidos en cada vista.
- El `toast` entra y sale con animacion y se cierra automaticamente.
- La duracion por defecto se centraliza en `resources/js/app.js` con `window.retailSaas.toastDuration`.
- Las acciones Livewire en la misma pagina despachan el evento `toast`; los redirects usan `session('toast')`.
