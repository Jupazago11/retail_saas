# Checklist Maestro

Estado consolidado del proyecto frente al objetivo SaaS retail definido al inicio.

Indicadores:

- `✅` Listo.
- `🟡` Parcial solido.
- `🟠` Inconcluso o con riesgo operativo.
- `❌` Faltante.

## 1. Base SaaS y multiempresa

- `✅` Autenticacion, usuario por `username` y seleccion de empresa activa.
- `✅` Nucleo tenant con `companies`, `branches`, `warehouses` y `cash_registers`.
- `✅` Suscripcion inicial `basic` al crear empresa.
- `✅` Resolver efectivo de plan por suscripcion directa, bundle y overrides.
- `✅` Deteccion de vencimiento, activacion manual de nuevo plan y renovacion automatica por empresa en `Plataforma > Suscripciones`.
- `✅` Edicion completa de planes (modulos, features y limites) desde `Plataforma > Planes`, con catalogo de codigo como seed no destructivo.
- `🟡` UI platform/admin global para gobierno total de planes, bundles, cupones y suscripciones.

## 2. Catalogo y estructura operativa

- `✅` Categorias, marcas, unidades, productos, presentaciones, atributos, valores y variantes.
- `✅` CRUD base y archivado logico inicial en los modulos principales.
- `✅` Estructura operativa con sucursales, bodegas y cajas.
- `🟡` Refinamientos de UX, validaciones mas profundas y administracion mas granular.

## 3. Compras, proveedores e inventario

- `✅` Compras `draft/confirmed` con lineas, totales y posting a inventario.
- `✅` Devoluciones de compra con impacto en kardex.
- `✅` Ajustes de inventario y traslados entre bodegas.
- `✅` Proveedores formales enlazados a personas.
- `✅` Cuentas por pagar, pagos, saldo a favor y aplicacion posterior.
- `✅` Ledger visual por compra y consulta consolidada por proveedor.
- `🟡` Conciliaciones avanzadas, automatizaciones posteriores y posibles reaperturas controladas. CxP ya exige referencia al aplicar saldo a favor.

## 4. Ventas, POS y caja

- `✅` Ventas `draft/confirmed` con ticket, snapshot comercial y salida de inventario.
- `✅` Numeracion documental interna secuencial configurable desde Settings.
- `✅` POS minimo operativo con cobro inmediato y multiples pagos.
- `✅` Borradores editables desde POS sin perder numero documental.
- `✅` Ventas congeladas con crear, retomar, convertir y cancelar.
- `✅` Apertura y cierre de caja con primera UI operativa.
- `✅` Devoluciones y anulaciones de venta desde UI, con motivo obligatorio y trazabilidad en notas.
- `🟡` Refinamientos comerciales del POS, conciliaciones posteriores y catalogo formal de medios de pago. Caja ya distingue cierres cuadrados de cierres con diferencia.

## 5. Credito, clientes y fidelizacion

- `✅` Clientes y cuentas de credito enlazadas a ventas.
- `✅` Ventas a credito y abonos desde UI.
- `✅` Bloqueo por cartera vencida gobernado por settings.
- `✅` Cuentas de puntos, acumulacion, redencion, restauracion y expiracion FIFO.
- `✅` Ajustes manuales de puntos y ledger visible.
- `🟡` Reglas comerciales mas profundas para credito y fidelizacion. Los abonos ya validan coherencia de sucursal/caja contra la venta.

## 6. Promociones y motor comercial

- `✅` Promociones por producto y combos a precio fijo.
- `✅` Snapshots por linea y resumen en `pricing_snapshot`.
- `✅` UI operativa para crear, editar y archivar promociones.
- `✅` Preview comercial provisional en POS.
- `🟡` Reglas avanzadas de promociones, compatibilidades y refinamientos posteriores.

## 7. Reportes, auditoria e importaciones

- `✅` Reportes base con resumen comercial y exportacion CSV.
- `✅` Auditoria con eventos criticos, snapshots `before/after`, busqueda, filtro por IP y exportacion CSV.
- `✅` Importaciones CSV para productos, inventario, compras, proveedores y clientes/credito.
- `🟡` Reportes especializados ya incluyen aging de cartera y desglose por medio de pago; faltan series temporales, paginacion avanzada y automatizaciones.

## 8. Gobernanza por plan y limites

- `✅` Enforcement real sobre promociones, combos, fidelizacion, redencion, descuentos manuales y ventas congeladas.
- `✅` Enforcement real sobre `max_companies`, `max_users`, `max_branches`, `max_warehouses`, `max_cash_registers`, `max_products` y `max_monthly_sales`.
- `✅` Guard preparado para `max_electronic_documents`.
- `🟡` Mayor cobertura de limites y mejor UI de administracion comercial global.

## 9. Facturacion y pagos externos

- `✅` Facturacion interna POS normal con numeracion propia.
- `🟠` Facturacion electronica real.
- `🟠` Numeracion tributaria asistida y validacion tributaria.
- `🟠` Integracion con proveedor de facturacion electronica.
- `🟠` Integracion Wompi u otro gateway externo.

## 10. UI transversal

- `✅` `toast` global animado, centralizado y configurable por una sola variable.
- `✅` Primera capa operativa en compras, ventas, POS, caja, credito, fidelizacion, promociones, reportes y admin.
- `🟡` Pulido visual final, consistencia transversal y refinamientos de experiencia.

## Resumen ejecutivo

- Base operativa retail interna: `✅`
- Backoffice transaccional principal: `✅`
- Flujos comerciales con primera UI real: `✅`
- Refinamientos y endurecimiento de modulos existentes: `🟡`
- Integraciones externas de alto impacto: `🟠`

## Amarillos recomendados

Estos son los amarillos mas rentables para cerrar antes de meterse en integraciones externas:

1. Endurecer conciliaciones y reglas posteriores en compras, ventas, caja y credito.
2. Refinar reglas comerciales de promociones, fidelizacion y cartera.
3. Mejorar reportes especializados y consultas historicas.
4. Fortalecer la UI platform/admin global para planes, bundles, cupones y suscripciones.
5. Hacer el pulido visual final cuando el backend ya no siga moviendose.
