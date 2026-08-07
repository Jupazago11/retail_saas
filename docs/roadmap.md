# Roadmap

## Fase 0

- Confirmar directorio seguro.
- Inspeccionar Docker y puertos sin cambios destructivos.
- Crear documentacion base.
- Definir nombres, puertos y arquitectura.

## Fase 1

- Crear proyecto Laravel.
- Configurar Docker Compose local.
- Instalar autenticacion base. Completado con Breeze + Livewire.
- Implementar `Company`, `Branch`, `Warehouse`, `CashRegister`. Completado.
- Implementar empresa activa y contexto tenant. Completado.
- Ajustar login por `username`. Completado.
- Agregar selector de empresa activa. Completado.

## Fase 2

- Implementar platform admin base.
- Implementar planes, modulos, features y limites. Backend base completado con catalogo bootstrappeable y resolvedor de acceso efectivo; falta UI platform/admin.
- Implementar bundles multiempresa. Backend base completado en modelo de datos y resolucion; falta gestion operativa desde UI.
- Implementar cupones y suscripciones manuales. Backend base completado en modelo de datos; faltan flujos operativos, redenciones y administracion visual.

## Fase 3

- Implementar permisos tecnicos y plantillas. Completado en base tecnica.
- Implementar roles empresariales. Base tecnica y UI inicial de roles/asignacion completadas; faltan refinamientos, validaciones avanzadas y administracion mas granular.
- Implementar configuracion tipada por empresa. Backend base, UI inicial y primer consumo real en caja/POS completados; faltan mas integraciones transversales y validaciones operativas finas.
- Implementar auditoria inicial. Backend base, consulta operativa y UI visual inicial completadas; faltan exportacion, filtros avanzados y ampliaciones posteriores.

## Siguiente corte recomendado

- Exponer UI platform/admin para catalogo de planes, bundles, suscripciones y cupones sobre el backend base ya disponible. Ya existe una primera UI tenant/admin de suscripcion por empresa con lifecycle manual, alta/edicion inicial de bundles, alta/edicion inicial de cupones y overrides por empresa; falta catalogo platform mas amplio y reglas comerciales mas profundas.
- `Plataforma > Planes` ya permite editar modulos incluidos, features por modulo y limites numericos de cada plan con un panel lateral, ademas de nombre/precio/periodo/estado; el bootstrapper del catalogo ya no revierte estas ediciones.
- La pantalla `Plataforma > Suscripciones` ya distingue visualmente una suscripcion vencida de una activa, permite activar un nuevo plan directo para una empresa vencida y expone un toggle de renovacion automatica por empresa; el comando programado diario `subscriptions:process-due` cierra o renueva segun ese flag.
- Conectar validaciones reales de plan/features/limites a flujos operativos concretos como multi-bodega, ventas congeladas, loyalty y promociones. Ya conectado en fidelizacion, promociones, combos, descuentos manuales, ventas congeladas, `max_companies`, `max_users`, `max_branches`, `max_warehouses`, `max_cash_registers`, `max_products` y `max_monthly_sales`; faltan otros limites y modulos como emision electronica real.
- Refinar UI y flujo de asignacion de `role_templates` y `company_roles` a usuarios empresariales.
- Extender el consumo de `company_settings` ya administrables a mas reglas operativas de fidelizacion y refinamientos adicionales de impresion.
- Exponer UI operativa de fidelizacion y redencion de puntos sobre el backend ya disponible. Consulta operativa, expiracion manual, ajustes manuales y redencion directa desde POS completadas; faltan refinamientos comerciales posteriores.
- Conectar compras, ventas, caja, credito y promociones a una primera UI operativa. Compras, ventas, caja, credito y promociones ya cuentan con primera iteracion operativa.
- Exponer UI operativa de proveedores, cuentas por pagar y aplicacion de saldo a favor. Completado en primera iteracion operativa.
- Evaluar exportacion o endpoint dedicado para `audit_logs` y tablero de cuentas por pagar.

## Fase 4

- Implementar categorias, marcas, unidades y productos.
- CRUD inicial de `categories`, `brands` y `units`. Completado.
- CRUD inicial de `products`. Completado.
- CRUD inicial de `product_presentations` y conversion a unidad base. Completado.
- CRUD inicial de `attributes`, `attribute_values` y `product_variants`. Completado.
- Implementar CRUD Livewire con archivado logico.

## Fase 5

- Implementar compras. Backend base y devoluciones de compra completados.
- Implementar compras. Backend base, devoluciones e UI operativa inicial completados; faltan edicion, conciliaciones avanzadas y automatizaciones posteriores.
- Implementar compras. Backend base, devoluciones, UI operativa inicial, visualizacion de ledger y edicion de borradores completados; faltan conciliaciones avanzadas y automatizaciones posteriores.
- Implementar kardex. Base y posting desde compras, devoluciones, ajustes, traslados y ventas completados.
- Implementar stock por bodega y costo promedio. Base, promedio ponderado, ajustes, traslados y ventas completados.
- Modelar proveedores formales. Backend base y UI operativa inicial completados; falta mantenimiento avanzado y archivado posterior.
- Implementar cuentas por pagar. Backend base, consulta operativa por compra, conciliacion por saldo a favor, consulta consolidada por proveedor, resumen agregado por proveedor, UI inicial, ledger visual por compra y referencia obligatoria al aplicar saldo a favor completados; faltan conciliaciones posteriores y automatizaciones opcionales.

## Fase 6

- Implementar POS.
- Implementar ventas congeladas. Backend base y primera UI operativa completados; falta integracion posterior con cobro final, reaperturas mas refinadas y limpieza automatica de expiradas.
- Implementar ventas. Backend base, ticket imprimible, numeracion documental interna por empresa configurable desde Settings, UI inicial de consulta, POS minimo de creacion, cobro inmediato inicial, edicion de borradores y preview comercial provisional completados; faltan refinamientos comerciales posteriores desde UI.
- Implementar pagos mixtos. Backend base y primera captura operativa desde POS completados; faltan conciliaciones posteriores, catalogo formal de medios de pago y mas puntos de entrada UI.
- Implementar apertura y cierre de caja. Backend base, primera UI operativa y distincion entre cierre cuadrado (`reconciled`) y cierre con diferencia (`closed`) completados; faltan conciliaciones posteriores, reportes y cierres asistidos.
- Implementar anulaciones y devoluciones. Backend base, primera UI operativa y captura obligatoria de motivo completados; faltan reglas de conciliacion posteriores y refinamientos comerciales.

## Fase 7

- Implementar credito y abonos. Backend base, primera UI operativa y validacion de abonos contra la caja/sucursal operativa de la venta completados; faltan vencimientos avanzados, conciliaciones posteriores y reportes dedicados.
- Implementar puntos. Backend de acumulacion, redencion y expiracion, mas UI operativa de consulta, expiracion manual, ajustes manuales y redencion directa desde POS, completados; faltan reglas comerciales posteriores.
- Implementar promociones y combos. Backend base y primera UI operativa de administracion completados, incluyendo edicion, archivado, duplicado y lectura de vigencias; faltan reglas avanzadas y refinamientos posteriores.

## Fase 8

- Implementar reportes. UI operativa ampliada, exportacion CSV, aging de cartera y desglose por medio de pago completados; faltan series temporales, comparativos y cortes financieros mas finos.
- Implementar importaciones Excel. Primera iteracion de importacion CSV de productos, ajustes de inventario, compras y proveedores completada con validacion parcial, enforcement de plan y auditoria; falta ampliar a Excel real y otros dominios.
- Implementar auditoria avanzada. Filtros adicionales y exportacion CSV inicial completados; faltan comparadores mas ricos, paginacion, alertas y automatizaciones posteriores.

## Fase 9

- Preparar facturacion electronica. Base inicial de configuracion tipada ya disponible para planes con modulo `electronic_billing`; la facturacion interna POS ya cuenta con numeracion secuencial propia, pero falta emision electronica real, numeracion tributaria asistida, validacion tributaria e integracion con proveedor.
- Preparar integracion Wompi.
- Preparar storage S3.
- Afinar despliegue Railway.
