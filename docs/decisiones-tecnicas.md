# Decisiones Tecnicas

## Versiones recomendadas al 2026-06-15

- Laravel 13.x: version mayor actual documentada oficialmente.
- PHP 8.4.x: preferida por madurez de ecosistema frente a 8.5 reciente.
- PostgreSQL 18 en local si se usa imagen oficial estable; PostgreSQL 17 o 18 en Railway segun disponibilidad confirmada del proyecto.
- Node.js 24 LTS para build frontend y Vite.

## Decisiones tomadas

### Monolito modular

- Decision: una sola aplicacion Laravel.
- Razon: reduce complejidad operativa y acelera MVP.

### Base de datos unica con `company_id`

- Decision: multi-tenancy por columna y contexto.
- Razon: evita el costo operativo de una base por tenant en esta etapa.

### Livewire sobre SPA

- Decision: Blade + Livewire + Alpine.
- Razon: cumple UX dinamica sin introducir React o Vue.

### Autenticacion con Breeze + Livewire

- Decision: usar Breeze como base de autenticacion y extenderlo con componentes Livewire.
- Razon: acelera el arranque del backoffice, mantiene coherencia con Blade y evita construir autenticacion custom desde cero.

### Login por `username`

- Decision: autenticar con `username` en lugar de email como credencial principal.
- Razon: es mas natural para operacion diaria en caja y reduce errores en entornos con alta rotacion de personal.

### Contexto tenant obligatorio tras autenticacion

- Decision: exigir empresa activa antes de entrar al dashboard y redirigir al selector cuando sea necesario.
- Razon: reduce riesgo de operar sin contexto tenant explicito y simplifica las consultas posteriores.

### Queue `database` en fases iniciales

- Decision: comenzar sin Redis obligatorio.
- Razon: menos infraestructura y suficiente para MVP.

### Docker Compose propio

- Decision: preferir Compose propio en lugar de acoplar todo a Sail.
- Razon: mayor control para puertos, nombres y compatibilidad con Railway.

### Vite en contenedor

- Decision: agregar servicio `vite` con `node:24-alpine`.
- Razon: el host actual tiene Node 18.19.0 y no quiero bloquear el flujo local por una version menor a la recomendada.

### Mitigacion de dependencias frontend vulnerables

- Decision: fijar `shell-quote` a `1.8.4` mediante `overrides` de npm, manteniendo `concurrently` en 9.x.
- Razon: `concurrently` 10 corrige la dependencia de forma nativa, pero exige Node `>=22`; la mitigacion por override elimina la vulnerabilidad sin romper el flujo `composer dev` basado en Node 18+.

### CRUD de maestras por recurso

- Decision: separar `categories`, `brands` y `units` en paginas Livewire independientes bajo `masters/*`.
- Razon: reduce complejidad inicial, permite pruebas tenant mas claras y evita un componente monolitico prematuro para catalogos base.

### Producto base antes de impuestos avanzados

- Decision: habilitar `products` con `tax_id` nullable como referencia libre, sin forzar aun una tabla de impuestos.
- Razon: el producto ya es necesario para compras, inventario y POS, pero el modelado tributario detallado puede introducirse despues sin bloquear el catalogo base.

### Conversion de presentaciones con `bcmath`

- Decision: resolver conversiones entre presentacion y unidad base con un servicio dedicado basado en `bcmath`.
- Razon: inventario y compras requieren cantidades deterministicas; usar `float` aqui aumentaria riesgo de errores acumulados en stock y costo.

### Notificaciones globales con `toast`

- Decision: centralizar las notificaciones UI en un `toast` global montado en el layout autenticado.
- Razon: evita repetir bloques estaticos por vista, unifica comportamiento entre acciones Livewire y redirects, y permite cambiar la duracion modificando solo `window.retailSaas.toastDuration`.

### Atributos y variantes con archivado por estado

- Decision: manejar `attributes`, `attribute_values` y `product_variants` con `status` (`active`, `inactive`, `archived`) en lugar de `soft delete`.
- Razon: el catalogo necesita reactivar registros con facilidad y conservar reglas de negocio visibles sin mezclar estados operativos con filas eliminadas logicamente.

### Duplicados de variantes resueltos en la capa de aplicacion

- Decision: validar en la capa Livewire que una variante no repita la misma combinacion de valores para un producto y que solo tenga un valor por atributo.
- Razon: la restriccion depende de una tabla pivote y de una regla compuesta de negocio; resolverlo solo con una constraint SQL seria menos directo en esta fase.

### Autorizacion por empresa activa con middleware y policies

- Decision: resolver permisos operativos contra la empresa activa mediante `company.permission` en rutas y policies en acciones Livewire.
- Razon: el middleware protege el acceso de entrada al modulo y las policies evitan que una llamada directa a metodos del componente salte la autorizacion.

### Bootstrapper idempotente para permisos y plantillas

- Decision: poblar `permissions` y `role_templates` con un bootstrapper idempotente reutilizable por `db:seed` y por el aprovisionamiento inicial de empresa.
- Razon: la aplicacion debe quedar funcional despues de migrar aun si no se ejecuta una semilla manual separada antes de crear la primera empresa.

### Configuracion tipada con catalogo centralizado

- Decision: resolver `company_settings` mediante un servicio dedicado y un catalogo central de claves validas con defaults.
- Razon: evita lecturas dispersas de claves arbitrarias, reduce errores tipograficos y deja una base estable para POS, caja, credito e inventario.

### Compras base antes de proveedores y kardex completos

- Decision: habilitar primero `purchases` y `purchase_items` con calculo de totales y `base_quantity`, dejando `supplier_id` nullable y el kardex para el siguiente corte.
- Razon: permite capturar el contrato principal entre catalogo y compras sin bloquear el avance por el dominio de terceros ni por la complejidad completa de inventario.

### Saldos de inventario con unicidad explicita sin variante

- Decision: modelar `inventory_balances` como tabla real y agregar una restriccion unica dedicada para el caso `product_variant_id = null`.
- Razon: en PostgreSQL una unique comun sobre columnas nullable permite multiples filas con `null`; la restriccion parcial evita duplicar el saldo base del producto cuando no usa variantes.

### Kardex de compras con costo por unidad base

- Decision: al postear compras confirmadas al kardex, convertir el costo de la linea a costo por unidad base antes de actualizar movimientos y promedio ponderado.
- Razon: una compra en cajas o paquetes no puede inflar artificialmente el costo promedio; inventario y saldo trabajan sobre `base_quantity`, asi que el costo debe quedar normalizado al mismo nivel.

### Devoluciones de compra como salida con costo promedio vigente

- Decision: revertir compras desde inventario con una accion dedicada, marcar la devolucion con `returned_from_inventory_at` y valorar la salida al costo promedio vigente del saldo.
- Razon: el proyecto ya usa promedio ponderado como politica base; usar el promedio actual en la salida mantiene coherencia de valoracion, evita recalculos ambiguos por lote y deja la operacion idempotente y segura contra stock negativo.

### Ajustes manuales como documentos propios de inventario

- Decision: modelar los ajustes en tablas propias (`inventory_adjustments` e `inventory_adjustment_items`) y postearlos automaticamente al kardex usando `posted_to_inventory_at`.
- Razon: compras y ajustes no representan el mismo hecho de negocio; separarlos mantiene trazabilidad clara, permite reglas distintas por tipo de ajuste y evita mezclar correcciones operativas con abastecimiento real.

### Traslados con costo heredado desde la bodega origen

- Decision: modelar los traslados en tablas propias (`inventory_transfers` e `inventory_transfer_items`) y valorar ambos movimientos con el costo promedio vigente de la bodega origen.
- Razon: un traslado interno no cambia el valor total de inventario de la empresa; solo redistribuye existencias entre bodegas. Tomar el costo del origen mantiene consistencia contable y permite recalcular correctamente el promedio del destino.

### Ventas base antes de pagos y caja

- Decision: habilitar primero `sales` y `sale_items` con calculo de totales, snapshots comerciales y posting de `sale_out`, dejando pagos, sesion de caja y anulaciones para el siguiente corte.
- Razon: la venta y la salida de inventario son el contrato central del POS; separar esa base de los medios de pago y caja reduce complejidad y permite validar el flujo critico de stock antes de introducir conciliacion financiera.

### Ventas congeladas como snapshot sin reserva de stock

- Decision: modelar `frozen_sales` como un documento separado con `payload_snapshot`, expiracion por configuracion y conversion explicita posterior a `sales`.
- Razon: en el POS real conviene guardar el carrito sin mover inventario ni mezclarlo todavia con pagos; separar ese estado evita reservas inconsistentes y permite retomar o descartar ventas de forma controlada.

### Pagos base con `payment_method_code` temporal

- Decision: abrir el dominio `payments` usando `payment_method_code` como string tecnico y asociarlo a `cash_sessions`, sin introducir aun una tabla formal de metodos de pago.
- Razon: el flujo operativo de cobro y cierre de caja necesita avanzar antes de modelar un catalogo completo; usar un codigo temporal permite registrar pagos mixtos y luego migrar a un catalogo dedicado sin bloquear el POS base.

### Caja inicial con una sola sesion abierta por caja

- Decision: permitir una unica `cash_session` abierta por `cash_register`, calcular el efectivo esperado desde apertura mas pagos `cash`, y validar diferencia segun configuracion de empresa.
- Razon: esta regla cubre el control minimo real de caja para el MVP y deja una base estable para aperturas, cierres y conciliaciones futuras.

### Devoluciones de venta por linea y anulacion con reverso financiero

- Decision: modelar las devoluciones de venta sobre `sale_items` acumulando `returned_quantity` y `returned_base_quantity`, reingresando inventario con `sale_return_in` al `cost_snapshot` original de la linea.
- Razon: la devolucion parcial requiere control fino por linea para impedir sobredevoluciones y mantener trazabilidad exacta entre la salida original y el reingreso.
- Decision: anular ventas confirmadas reponiendo solo el saldo pendiente no devuelto, marcando la venta con `cancelled_at` y revirtiendo pagos `confirmed` a `reversed`.
- Razon: evita borrar historial operativo, mantiene consistencia entre inventario y pagos y deja la compensacion explicita para auditoria posterior.

### Modificacion de venta confirmada como anulacion + venta nueva encadenadas

- Decision: implementar "Modificar" (corregir una venta POS ya confirmada, ej. un producto que falto o se empaco de mas) como composicion de las dos Actions ya existentes — `CancelSale` sobre la venta original y `CreatePosSale` para la venta corregida — en `App\Actions\Sales\ModifySale`, envueltas juntas en un solo `DB::transaction()` externo, en vez de mutar `sale_items` de la venta ya confirmada.
- Razon: mantiene la regla ya vigente de que una venta confirmada solo se anula o se compensa, nunca se edita en sitio; y como `CreatePosSale`/`CancelSale` ya abren su propio `DB::transaction()` internamente, Laravel promueve la anidada a `SAVEPOINT` de forma transparente (funciona igual en Postgres y en SQLite, el motor de la suite de tests), asi que envolverlas en una transaccion externa da atomicidad completa sin tocar ninguna de las dos clases: si la venta nueva falla, la anulacion de la vieja tambien se revierte.
- Decision: dentro de `ModifySale::handle()`, anular la venta original **antes** de crear la venta nueva.
- Razon: `PostSaleToInventory` rechaza una venta si el stock actual no alcanza, y `CompanyOperationalLimitGuard` cuenta ventas `confirmed` del mes contra `max_monthly_sales`; si la venta nueva se creara antes de anular la vieja, una correccion legitima (pedir la misma cantidad o mas de un producto que la venta original ya habia descontado) fallaria por "stock insuficiente" porque ese stock todavia no se ha devuelto, y una empresa justo en su limite mensual podria quedar bloqueada sin motivo real. Anulando primero, el stock y el conteo mensual ya estan liberados cuando se valida la venta nueva.
- Decision: "Modificar" solo exige el permiso `sales.create` (no se creo un permiso `sales.modify` nuevo, ni se exige tambien `sales.cancel`), tratando la anulacion de la venta original como un efecto automatico de confirmar la venta nueva.
- Razon: `cashier` y `seller` tienen `sales.create` pero no `sales.cancel` en `PermissionCatalog::roleTemplates()`; el pedido original explicitamente queria que el cajero pudiera hacer esta correccion por si mismo. El mismo patron de "cambiar el estado de un registro anterior sin permiso aparte, como efecto colateral de la accion que si se autorizo" ya existe en `PosPage::resumeFrozenSale()`/`freezeCurrentSale()` para ventas congeladas.
- Decision: "Modificar" queda restringido a `sale_type = pos`; ventas a credito quedan fuera de alcance de esta fase.
- Razon: `PosPage::saveSale()` hoy hardcodea `sale_type = pos` al construir cualquier venta nueva desde el POS — el POS no puede crear ventas de credito. Permitir "Modificar" sobre una venta de credito perderia silenciosamente su relacion de credito al recrearla.
- Decision: agregar `sales.replaces_sale_id` (autorreferencia nullable a `sales`, mismo patron que `frozen_sales.converted_sale_id`) para trazabilidad, y re-verificar `status === confirmed` con `lockForUpdate()` al inicio de `ModifySale::handle()` antes de anular/crear.
- Razon: `CancelSale::handle()` es idempotente si la venta ya esta `cancelled` (retorna sin error, pensado para que un doble click en "Anular" sea seguro) — sin esta verificacion extra, un doble submit de "Modificar" encontraria la venta ya anulada por el primer intento y crearia una segunda venta de reemplazo.

### Credito por cliente con ledger separado por venta

- Decision: modelar `people`, `customers`, `credit_accounts` y `credit_movements`, enlazando una venta a credito a una cuenta concreta mediante `credit_account_id`.
- Razon: el credito necesita un dominio propio para controlar cupo, saldo pendiente y trazabilidad financiera sin contaminar el flujo de pagos inmediatos del POS.
- Decision: registrar la venta a credito como `sale_type = credit`, generar el cargo al confirmar la venta y manejar los abonos con una accion separada de `RegisterSalePayments`.
- Razon: separar cobro inmediato de abonos evita mezclar contratos distintos de negocio y deja claro que una venta a credito no se liquida en el mismo flujo que una venta de contado.
- Decision: en este corte base, bloquear devoluciones o anulaciones de ventas a credito cuando ya existan abonos confirmados, y usar ajustes negativos de cartera cuando la venta se devuelve o anula sin abonos previos.
- Razon: evita introducir conciliaciones ambiguas sobre pagos ya aplicados mientras todavia no existe un modulo completo de notas credito, saldos a favor o reallocacion automatica de abonos.

### Nombre de persona centralizado y alta rapida de cliente sin contaminar el documento

- Decision: agregar `Person::full_name` como accessor unico (`trim(first_name.' '.last_name)`) en vez de que cada pantalla concatene `first_name`/`last_name` a mano.
- Razon: `PosPage.php` y `pos-page.blade.php` ya llamaban a `$person->full_name` desde antes, pero el accessor nunca existio — Eloquent devuelve `null` para un atributo inexistente, asi que el respaldo `?? 'Cliente #'.$id` se disparaba siempre, sin importar si la persona tenia nombre. El modulo de Credito no tenia este bug porque armaba el nombre a mano (duplicado en 2 sitios), que ahora tambien se simplifico para usar el accessor.
- Decision: en `PosPage::resolveOrCreateCustomer()` (alta rapida de cliente desde el campo unico de "cliente" del POS), solo se guarda el texto escrito como `document_number` si son puros digitos; si es un nombre o apodo, se guarda unicamente en `first_name` y `document_number` queda `null`. La busqueda de una persona existente sigue la misma regla (por `document_number` si es numerico, por `first_name` con `document_number` nulo si no) para no crear un cliente duplicado cada vez que se repite el mismo apodo.
- Razon: antes, cualquier texto escrito (incluyendo un apodo como "Ñato") se guardaba a la vez como nombre Y como numero de documento, contaminando ese campo con datos que no son un documento real.
- Decision: agregar un buscador "Agregar cliente a credito" dentro de `/credit` (`CreditAccountsPage::customersWithoutCreditOptions()` + `App\Actions\Customers\EnableCustomerCredit`), en vez de una pagina general de "Clientes".
- Razon: `CreditAccountsPage::accounts()` solo consulta `credit_accounts` — un cliente que nunca tuvo credito (como cualquiera creado desde el alta rapida del POS) era invisible ahi sin importar que se buscara, y no existia ningun flujo para habilitarle credito por primera vez. Se opto por resolverlo dentro del modulo de Credito (acotado, reusa `CreateCustomer`'s logica de creacion de `CreditAccount`) en vez de construir un modulo nuevo de "Clientes" que no fue pedido.
- Nota de implementacion: el buscador de "Agregar cliente a credito" reusa el mismo patron de catalogo-JS-en-Alpine que `posCustomerSearch()` del POS (`@js($catalog)` + filtro `.toLowerCase().includes(q)` en ambos lados, corrigiendo tambien ahi la asimetria de mayusculas que impedia encontrar "Ñato" al buscar por "Ñ"). El `<script>` que define la funcion Alpine debe quedar **fuera y despues** del `<div>` raiz del componente Livewire, nunca antes: un `<script>` colocado antes del primer `<div>` del archivo hace que Livewire inyecte sus atributos (`wire:id`, `wire:snapshot`) sobre el `<script>` en vez de sobre el div real, rompiendo silenciosamente todos los `wire:click` de la pagina sin ningun error visible en consola.

### Fidelizacion con ledger separado y acumulacion automatica

- Decision: modelar `loyalty_accounts` y `loyalty_movements` como un ledger propio por cliente y empresa, separado de pagos y de cartera.
- Razon: los puntos tienen reglas de negocio distintas a dinero y credito; separarlos evita mezclar saldos heterogeneos y deja trazabilidad clara de acumulaciones y reversos.
- Decision: en esta fase acumular puntos al confirmar la venta y revertirlos proporcionalmente en devoluciones o totalmente en anulaciones, usando `loyalty.points_rule_type` y `loyalty.points_rate`.
- Razon: resuelve el contrato minimo real de fidelizacion sin introducir aun redencion, expiraciones automáticas ni promociones complejas.

### Redencion y expiracion FIFO de fidelizacion

- Decision: integrar la redencion de puntos como descuento distribuido sobre las lineas de la venta antes de persistir el documento.
- Razon: esto hace que impuestos, totales y snapshot comercial queden consistentes desde el origen, en lugar de compensarlos despues con ajustes externos.
- Decision: usar la misma `points_rate` de la regla `per_currency` para convertir puntos a descuento monetario en esta fase.
- Razon: evita abrir todavia un segundo catalogo de equivalencias mientras la plataforma solo soporta una regla base de acumulacion.
- Decision: al devolver o anular una venta, restaurar primero los puntos redimidos y luego revertir los puntos ganados por esa misma operacion.
- Razon: ese orden reduce falsos bloqueos por saldo insuficiente y deja el ledger mas coherente cuando una venta tuvo consumo y acumulacion en el mismo flujo.
- Decision: expirar puntos disponibles por FIFO a partir de `loyalty.points_expiration_days`, permitiendo desactivar la expiracion con valores `<= 0`.
- Razon: el consumo por lotes antiguos mantiene una regla deterministica y auditable sin introducir columnas de vencimiento por lote en la primera iteracion.

### Motor de promociones desacoplado del calculo base de venta

- Decision: resolver promociones y combos en un servicio dedicado (`PromotionEngine`) antes de pasar las lineas al `SaleCalculator`.
- Razon: separar el motor promocional del calculo base evita contaminar la logica general de ventas, permite combinar estrategias distintas por tipo de promocion y deja espacio para reglas futuras sin reescribir el contrato central de `sales`.
- Decision: aplicar primero combos a precio fijo y luego promociones por producto, ordenando por `priority` y respetando `pos.allow_promotion_stacking`.
- Razon: el combo consume unidades concretas de la venta; resolverlo primero evita duplicar beneficios sobre las mismas cantidades cuando la empresa deshabilita stacking y deja una regla deterministica de evaluacion.
- Decision: congelar un `promotion_snapshot` por linea y un resumen en `sales.pricing_snapshot`.
- Razon: la promocion efectiva debe quedar auditable aunque luego cambien las reglas, fechas o descuentos configurados en el catalogo comercial.

### Auditoria inicial por acciones explicitas

- Decision: registrar auditoria desde las acciones de aplicacion con un servicio dedicado (`AuditLogger`) y snapshots planos de atributos.
- Razon: el sistema ya opera sobre transacciones de negocio claras; auditar desde esas acciones evita observers opacos, reduce ruido y mantiene control explicito sobre que eventos merecen trazabilidad.
- Decision: guardar `before_snapshot` y `after_snapshot` solo cuando una accion realmente cambie el estado del registro.
- Razon: esto evita duplicar entradas por llamadas idempotentes y mantiene el ledger util para revision operativa.

### Cuentas por pagar por compra antes de conciliaciones completas

- Decision: abrir cuentas por pagar como un ledger por compra (`payable_movements`) y conservar `supplier_name` como snapshot historico, aun cuando la compra ya pueda enlazarse a un proveedor formal.
- Razon: la compra ya existe como documento financiero suficiente para controlar saldo pendiente, pagos y devoluciones; el nombre congelado evita depender de cambios posteriores en el maestro del proveedor.
- Decision: calcular el saldo vivo en la propia compra mediante `amount_paid`, `balance_due` y `paid_at`, alimentados por movimientos `purchase_charge`, `payment` y `purchase_return_adjustment`.
- Razon: esto deja consultas rapidas por compra y mantiene la trazabilidad detallada en un ledger separado.
- Decision: bloquear la devolucion de una compra si ya tiene pagos registrados, hasta que exista manejo formal de notas credito o saldos a favor con proveedor.
- Razon: revertir inventario despues de pagos parciales introduce conciliaciones que este corte base todavia no resuelve con seguridad.

### Proveedores sobre `people` con relacion por empresa

- Decision: modelar `suppliers` como una relacion comercial tenant-scoped sobre la tabla compartida `people`, en lugar de crear una identidad separada para proveedores.
- Razon: clientes y proveedores comparten datos base de contacto y documento; reutilizar `people` evita duplicacion prematura y mantiene coherencia con el diseño ya usado para `customers`.
- Decision: permitir que `purchases.supplier_id` sea nullable, pero apuntando por FK a `suppliers` cuando exista maestro formal, sin eliminar `supplier_name`.
- Razon: esto permite una transicion gradual desde compras con proveedor libre hacia compras plenamente referenciadas, preservando legibilidad historica y compatibilidad con datos operativos antiguos.
- Decision: cuando el proveedor tenga `payment_term_days` y la compra no reciba `due_at`, derivar automaticamente el vencimiento desde `purchased_at` o `now()`.
- Razon: centraliza una regla operativa comun en backend y evita repetir calculos manuales o divergentes cuando entre la UI de compras.

### Conciliacion base de saldo a favor para proveedores

- Decision: permitir devolver compras ya pagadas solo cuando exista proveedor formal enlazado, convirtiendo el monto pagado en saldo a favor del proveedor en lugar de bloquear la operacion.
- Razon: esto resuelve el caso real de nota credito o saldo a favor sin borrar historial de pagos ni romper la trazabilidad del documento original.
- Decision: mantener el saldo vivo de la compra en `balance_due = 0` al devolverla y mover el excedente pagado al `credit_balance` del proveedor mediante movimientos dedicados del ledger.
- Razon: la deuda por esa compra deja de existir; el compromiso pendiente pasa a ser una compensacion futura con el proveedor, no una cuenta por pagar abierta del documento devuelto.
- Decision: aplicar el saldo a favor del proveedor sobre compras pendientes mediante un movimiento explicito `supplier_credit_applied`, en lugar de consumirlo implicitamente al crear la siguiente compra.
- Razon: deja mas control operativo, facilita auditoria y evita interferir con validaciones ya existentes de compras con pago inicial.

### UI operativa separada para proveedores y cuentas por pagar

- Decision: abrir dos pantallas Livewire separadas, `purchases.suppliers` y `purchases.payables`, con navegacion propia dentro del modulo de compras.
- Razon: el maestro comercial del proveedor y la conciliacion financiera tienen permisos, filtros y ritmos operativos distintos; separarlos evita una pantalla monolitica y facilita evolucion posterior hacia compras completas.
- Decision: introducir permisos `suppliers.view` y `suppliers.manage` en lugar de reutilizar `payables.manage` para todo el dominio del proveedor.
- Razon: compras necesita poder administrar proveedores sin heredar automaticamente privilegios contables completos sobre conciliacion y cartera proveedor.

### Primera UI operativa de compras sobre acciones existentes

- Decision: montar `purchases.index` como una pantalla Livewire que reutiliza las acciones existentes de crear compra, registrar pago y devolver compra.
- Razon: esto evita duplicar reglas del dominio en la UI y mantiene compras, inventario y cuentas por pagar alineados sobre el mismo contrato transaccional.
- Decision: permitir registrar pagos desde la pantalla de compras solo con `payables.manage`, aunque el documento sea visible para roles con `purchases.view`.
- Razon: la compra y la conciliacion financiera no son el mismo permiso operativo; separar ambos reduce privilegios innecesarios para jefes de compra que solo deben documentar abastecimiento.

### Ledger visual embebido por compra

- Decision: exponer el ledger financiero como expansion dentro de `purchases.index` y `purchases.payables`, en lugar de abrir una pantalla nueva en esta iteracion.
- Razon: el usuario necesita trazabilidad inmediata del documento mientras registra pagos, devoluciones o aplica saldo a favor; mantenerlo embebido reduce cambios de contexto y acelera validacion operativa.

### Edicion controlada solo para compras en borrador

- Decision: permitir la edicion unicamente sobre compras `draft` sin posting ni movimientos financieros previos, mediante una accion dedicada `UpdateDraftPurchase`.
- Razon: editar documentos ya aplicados a inventario o con ledger financiero obligaria a recalculos y reversiones mas delicadas; restringir la edicion al estado borrador mantiene integridad operacional sin bloquear el avance del modulo.

### Ledger consolidado por proveedor como query backend separada

- Decision: exponer la consulta de movimientos financieros del proveedor como una accion backend dedicada (`ListSupplierPayableMovements`) en lugar de inferirla desde la UI de compras o cuentas por pagar.
- Razon: el ledger por compra ya resuelve el documento puntual, pero reportes, auditoria y pantallas futuras necesitan recorrer el historial completo del proveedor con filtros consistentes por compra, tipo y fecha.

### Resumen agregado por proveedor sobre cuentas por pagar

- Decision: complementar el ledger detallado con una accion de resumen (`ListSupplierPayablesSummary`) que entregue por proveedor saldo abierto, compras pendientes, vencimientos, credito disponible y ultimo movimiento.
- Razon: muchas pantallas operativas no necesitan leer cada movimiento del ledger; un agregado reutilizable reduce calculos repetidos y deja lista la base para dashboards, aging y reportes financieros ligeros.
- Decision: incluir en el agregado `current_balance_total`, `overdue_balance_total` y `net_balance_exposure`, ademas de un filtro `has_credit_only`.
- Razon: estas metricas permiten distinguir deuda corriente, deuda vencida y compensaciones por saldo a favor sin reconstruir manualmente el estado neto del proveedor en cada consumidor.
- Decision: desglosar la deuda vencida en buckets de aging `0-30`, `31-60`, `61-90` y `91+` dias.
- Razon: el listado operativo y los futuros reportes financieros necesitan detectar rapidamente concentraciones de mora sin recorrer compra por compra ni recalcular antiguedades fuera del backend.

### Consumo operativo del agregado en `PayablesPage`

- Decision: consumir `ListSupplierPayablesSummary` directamente en `PayablesPage` para mostrar tarjetas globales de cartera y un bloque de proveedores filtrados, sin abrir un modulo separado todavia.
- Razon: esto acelera la validacion operativa del agregado backend y permite navegar saldo abierto, saldo vencido, credito y aging desde la misma pantalla donde ya se aplican saldos a favor.
- Decision: sincronizar `hasCreditOnly` y `agingBucket` entre el agregado por proveedor y la consulta detallada de compras abiertas.
- Razon: si ambos paneles responden a la misma logica de filtro, el usuario puede pasar del tablero resumido al documento puntual sin cambiar de contexto ni reinterpretar resultados.
- Decision: implementar la logica de aging tambien en `ListPurchasePayables`, no solo en el agregado `ListSupplierPayablesSummary`.
- Razon: el filtro visual de la pagina debe impactar el detalle documental real; dejar el aging solo en el resumen produciria una lectura inconsistente entre tablero y listado.

### Auditoria visual como modulo interno inicial

- Decision: exponer la auditoria en una pagina Livewire interna (`Admin\\AuditLogsPage`) protegida por `settings.manage`, en lugar de esperar a construir primero un modulo completo de configuracion y roles.
- Razon: la plataforma ya genera eventos sensibles; habilitar lectura visual temprana acelera validacion operativa, debugging funcional y trazabilidad sin depender todavia de reportes avanzados o exportaciones.

### Roles y asignaciones sobre la estructura actual de autorizacion

- Decision: abrir una primera UI administrativa (`Admin\\RolesPage`) directamente sobre `company_roles`, `permissions`, `role_templates` y el pivot `company_user`, sin introducir una capa adicional de servicio antes de validar el flujo.
- Razon: la estructura de autorizacion ya existe y resuelve permisos en produccion; montar la UI sobre esas mismas tablas reduce duplicacion, acelera la entrega y permite detectar temprano vacios reales del modelo.

### Configuracion administrativa inicial sobre `company_settings` y `companies`

- Decision: abrir una primera UI administrativa (`Admin\\SettingsPage`) que renderiza dinamicamente el catalogo tipado de `company_settings`, pero sincroniza `legal_name`, `display_name` y `tax_id` con la tabla `companies`.
- Razon: la empresa ya tiene campos nucleares fuera del ledger de configuracion; tratarlos como capa separada evita inconsistencias, mantiene integridad de columnas obligatorias y permite reutilizar el catalogo sin duplicar formularios por modulo.

### Primer consumo operativo de settings en caja y POS

- Decision: hacer que `cash.opening_required`, `pos.allow_manual_discounts` y `pos.allow_negative_stock` impacten directamente las acciones de abrir caja, crear ventas y postear `sale_out` a inventario.
- Razon: mantener settings solo en UI o catalogo no valida el diseño; conectarlos a reglas transaccionales reales permite probar el modelo de configuracion con impacto operativo inmediato y descubrir vacios antes de extenderlo a mas modulos.

### Bloqueo de nuevos creditos por cartera vencida

- Decision: hacer que `credit.block_new_credit_if_overdue` consulte el ledger real de credito por `credit_due_at` y saldo pendiente antes de autorizar una nueva venta a credito.
- Razon: la mora no debe inferirse solo por estado del documento; usar el ledger y el vencimiento real evita falsos positivos en ventas ya compensadas por abonos, devoluciones o anulaciones.

### Primer ticket imprimible como HTML autenticado

- Decision: materializar la primera salida de impresion como una ruta HTML autenticada de venta (`sales/{sale}/ticket`) alimentada por un builder reusable, en lugar de esperar aun a PDF o integraciones de impresoras.
- Razon: esto permite que `printing.ticket_format`, `printing.show_logo` y `printing.show_saas_branding` tengan un consumidor real de bajo costo operativo y deja una base directa para el futuro POS sin cerrar todavia el formato definitivo de impresion.

### Primera UI de ventas como consulta operativa antes del POS completo

- Decision: abrir una pagina Livewire de solo consulta (`Sales\\SalesPage`) para listar ventas, aplicar filtros operativos y enlazar al ticket, antes de construir el POS transaccional completo.
- Razon: el dominio de ventas ya existe en backend y el ticket ya es consumible; una UI liviana de lectura da visibilidad inmediata al negocio sin mezclar aun captura de carrito, cobro, congelado y devoluciones en una sola entrega.

### POS minimo como formulario transaccional separado de la consulta

- Decision: abrir una pagina Livewire inicial (`Sales\\PosPage`) enfocada en crear ventas `draft` o `confirmed`, separada del listado historico de ventas.
- Razon: la captura de lineas y la consulta documental tienen ritmos operativos distintos; separarlas evita una pantalla monolitica temprana y permite iterar despues cobro, congelado y devoluciones sin reescribir la vista de consulta.

### Cobro inmediato embebido dentro del POS inicial

- Decision: cerrar la confirmacion de ventas POS desde una accion orquestadora (`CreatePosSale`) que crea la venta y registra sus pagos inmediatos dentro de la misma transaccion, en lugar de dejar ambos pasos separados en UI.
- Razon: esto evita ventas POS confirmadas sin cobro cuando el flujo esperado es contado, mantiene consistente el total contra la suma exacta de pagos y deja el ticket listo al cerrar la operacion minima.
- Decision: validar en la UI que la `cash_session` abierta pertenezca a la misma empresa, sucursal y caja seleccionadas, pero permitir `cash_session_id = null` cuando la empresa desactive `pos.requires_open_cash_session`.
- Razon: la restriccion tenant y operativa protege contra asociar pagos a sesiones ajenas o fuera del contexto de caja, mientras la configuracion por empresa conserva flexibilidad para negocios que no abren caja formal en todos los puntos.

### Caja como modulo operativo separado del POS

- Decision: abrir una primera pagina Livewire de caja (`Cash\\CashSessionsPage`) separada del POS, enfocada en aperturas, cierres y consulta reciente de sesiones.
- Razon: aunque el POS consume la sesion abierta, la operacion de caja tiene permisos y tiempos distintos; separarla evita acoplar apertura/cierre a la pantalla de ventas y deja un punto de entrada claro para cajeros y supervisores.
- Decision: permitir acceso a la pagina con cualquiera de los permisos `cash.open`, `cash.close` o `cash.view_difference`, y mantener cada mutacion protegida por su permiso especifico.
- Razon: el dominio de caja no tiene todavia un `cash.view` dedicado; este acceso compuesto permite que perfiles parciales entren a consultar o cerrar sin sobredimensionar privilegios ni modificar todo el catalogo de permisos en este corte.

### Ventas congeladas como pagina operativa separada del POS final

- Decision: exponer `Sales\\FrozenSalesPage` como modulo propio para congelar, retomar y convertir carritos, en lugar de incrustar ese flujo dentro del POS inicial.
- Razon: el congelado tiene un ciclo distinto al cobro inmediato; mantenerlo separado evita sobrecargar el formulario POS y permite trabajar snapshot, expiracion y conversion con menor acoplamiento.

### Mutaciones de venta sobre el listado historico ya existente

- Decision: montar la primera UI de devoluciones y anulaciones directamente sobre `Sales\\SalesPage`, reutilizando el contexto documental ya visible.
- Razon: las mutaciones necesitan revisar el documento antes de actuar; operar sobre el mismo listado reduce navegacion innecesaria y evita crear una segunda pantalla de consulta casi duplicada.

### Credito como modulo financiero separado del modulo de ventas

- Decision: abrir `Credit\\CreditAccountsPage` para cartera y abonos, separada del modulo de ventas aunque consuma `sales` a credito y `credit_movements`.
- Razon: la cartera requiere leer saldo por cliente y por venta, no solo documentos comerciales; separarla mantiene el permiso `credit.*` aislado del flujo diario de ventas y deja una base mas limpia para reportes y conciliacion posterior.

### Consulta de productos por codigo de barras via OpenFoodFacts

- Decision: integrar `OpenFoodFactsService` como servicio de solo lectura contra la API publica v3 de OpenFoodFacts para autocompletar el nombre del producto al escanear un codigo de barras en el formulario de productos.
- Razon: reduce la carga de escritura manual para el operador y aprovecha una base de datos publica de millones de productos sin requerir autenticacion ni costo. La consulta es opcional y no bloquea el guardado si falla o si el producto no existe.
- Decision: preferir `product_name_es` sobre `product_name` y concatenar `quantity` cuando exista (ej. `Miel de Abejas – 350 g`). Disparar la busqueda con Enter para compatibilidad con lectores fisicos de codigo de barras.
- Razon: los lectores terminan con un caracter Enter que de otro modo enviaria el formulario; interceptar ese evento con `wire:keydown.enter.prevent` resuelve ambos problemas en una sola directiva.

### Formato de ticket termico 58mm

- Decision: agregar `thermal_58mm` como opcion valida en `printing.ticket_format` junto a `thermal_80mm` y `letter_a4`.
- Razon: el formato de 58mm es ampliamente usado en impresoras de escritorio economicas y POS de pequeno formato en Colombia; excluirlo obligaria a los usuarios a usar el ancho de 80mm con espacios en blanco excesivos.

### Codigo de categoria generado automaticamente en creacion rapida

- Decision: al crear una categoria desde el formulario rapido de productos (`saveQuickCategory`), generar el campo `code` automaticamente como slug en mayusculas del nombre, verificando unicidad por empresa con un sufijo numerico si hay colision.
- Razon: el campo `code` es NOT NULL y UNIQUE por `company_id` en la tabla `categories`; exigirlo en la UI de creacion rapida interrumpiria el flujo del operador que solo quiere registrar el nombre en ese momento.

### Recalculo directo de credito disponible

- Decision: al editar el cupo (`credit_limit`) de un cliente, recalcular `available_credit` como `credit_limit - balance_due` en lugar de sumar/restar la diferencia sobre el valor previo almacenado.
- Razon: el enfoque de delta hereda errores acumulados de cualquier inconsistencia previa en `available_credit`; la formula directa garantiza coherencia absoluta entre cupo, deuda y disponible en cada edicion.

### Correccion de `SubscriptionStatus` documentado contra el codigo real

- Decision: corregir `docs/estados-del-sistema.md` para listar los valores reales de `subscriptions.status` (`pending`, `trialing`, `active`, `ended`) en lugar del set previo (`trialing`, `active`, `past_due`, `suspended`, `cancelled`, `expired`), que nunca se implemento.
- Razon: la pantalla de Plataforma > Suscripciones mostraba "activa" para suscripciones con `ends_at` ya vencido porque leia el `status` crudo sin comparar con `now()`; al investigar se detecto que la documentacion describia estados que el codigo nunca uso, lo que ocultaba que "vencida" siempre fue un estado derivado (por fecha), no una columna. Se corrige el documento en lugar de introducir un enum nuevo, para no forzar una migracion de estados no solicitada sobre codigo que ya funciona correctamente via `Subscription::scopeActiveAt()`.

### Vencimiento y renovacion automatica de suscripciones directas

- Decision: agregar `companies.auto_renew` (boolean) y un comando programado diario `subscriptions:process-due` que usa el scope `Subscription::scopeDueForExpiration()` para encontrar suscripciones directas vencidas y, segun el flag de la empresa, cerrarlas (`EndCompanySubscription`) o renovarlas al mismo plan (`ChangeCompanySubscription`) calculando el siguiente vencimiento desde `plan.billing_period`.
- Razon: el motor de plan efectivo (`CompanyPlanResolver`) ya ignoraba correctamente una suscripcion vencida sin importar su `status`, pero nada actualizaba la columna ni ofrecia una accion clara al superadmin; reutilizar las Actions existentes evita duplicar logica de auditoria y de cierre de suscripciones solapadas ya probada en el flujo manual de `Admin\\SubscriptionPage`.
- Decision: extender `ChangeCompanySubscription::handle()` para aceptar un `ends_at` opcional en el arreglo de atributos, en lugar de crear una accion paralela.
- Razon: hasta ahora toda suscripcion creada por esta accion quedaba indefinida (`ends_at = null`); la renovacion automatica y la nueva pantalla "Activar nuevo plan" necesitan fijar una fecha de vencimiento concreta, y ningun llamador existente pasaba esa clave, asi que el cambio es retrocompatible.
- Decision: mostrar el estado "vencida" en la pantalla de Plataforma calculandolo en cada render (`Subscription::isPastDue()`), sin esperar a que corra el comando programado.
- Razon: el superadmin necesita ver el estado real al instante; depender solo del job diario dejaria la UI desactualizada durante horas.

### Bootstrapper de catalogo de planes no destructivo

- Decision: cambiar `PlanCatalogBootstrapper::ensureDefaults()` de `upsert(..., $updateColumns)` a `insertOrIgnore(...)` para `plans`, `plan_modules`, `plan_features` y `plan_limits` (se deja `Module`/`Feature` con `upsert` normal, ya que su nombre/estado sigue siendo una taxonomia fija de codigo).
- Razon: este bootstrapper no es un seed de una sola vez, se ejecuta en trafico normal (`ProvisionCompanySubscription` al crear cualquier empresa, `ChangeCompanySubscription` al cambiar el plan de cualquier empresa). Con `upsert` forzaba de vuelta los valores hardcodeados de `PlanCatalog.php` cada vez, revirtiendo silenciosamente cualquier edicion manual. Se verifico en el codigo fuente de Laravel que `upsert($values, $uniqueBy, [])` no es "insertar o ignorar" (hace un `insert()` plano que rompe con duplicate-key); el metodo correcto es `insertOrIgnore()`, que en Postgres compila a `on conflict do nothing`.
- Decision: `PlanCatalog.php` pasa a ser solo el seed inicial. Una vez que existe una fila para un plan, modulo, feature o limite, la base de datos manda; el catalogo en codigo solo aporta valores para combinaciones que todavia no existen (plan nuevo, o catalogo ampliado con un modulo/feature/limite que un plan existente aun no tiene fila).

### Edicion completa de planes desde Plataforma (modulos, features, limites)

- Decision: exponer en `Plataforma > Planes` la edicion de que modulos y features tiene cada plan y el valor de cada limite, ademas de los campos ya editables (nombre, precio, periodo, estado), mediante una nueva Action `App\Actions\Plans\UpdatePlan` que hace `sync()` completo de `plan_modules`/`plan_features` contra el universo total de modulos/features (siempre deja una fila explicita `enabled: true/false` por combinacion, nunca ambigueda de fila ausente) y `updateOrCreate` por cada limite de `App\Support\Plans\PlanLimitCatalog` (nuevo catalogo centralizado de los 8 limites conocidos, mismo espiritu que `CompanySettingCatalog`).
- Decision: al desmarcar un modulo en la UI, sus features tambien se desmarcan automaticamente (`PlansPage::toggleModule()`), para evitar el estado inconsistente de una feature habilitada sin su modulo.
- Decision: esta edicion queda sin auditoria por ahora. `AuditLogger` exige un `Company` no nulo en cada metodo, y editar un plan es una accion de plataforma sin empresa dueña; extender esa firma es un cambio aparte no solicitado. El modal anterior (solo nombre/precio/periodo/estado) tampoco auditaba, asi que no es una regresion, pero queda como deuda pendiente.
- Decision: implementar esta edicion como el primer "drawer" (panel lateral) del proyecto en lugar del modal chico centrado que usa el resto de la plataforma, siguiendo la regla ya documentada en `AGENTS.md` ("modal para formularios pequeños y drawer para medianos o grandes"). El formulario (datos basicos + ~11 modulos + ~15 features agrupadas + 8 limites) ya no cabe comodo en el modal chico `max-w-md` usado en el resto de pantallas.

### Exclusion de `editLimits` en el formateo automatico de dinero

- Decision: agregar `editlimits` a la lista de exclusion tanto en `App\Http\Middleware\NormalizeMoneyInput` como en el detector de inputs de dinero de `resources/js/app.js` (`excludedMoneyFieldPattern`).
- Razon: el formateador global de dinero (JS que inserta puntos de miles mientras se escribe, mas el middleware que los limpia al enviar) detecta campos "de dinero" por coincidencia de substring en el nombre (`amount, price, cost, ..., limit, cash, ...`). Los nuevos inputs de limites de plan (`editLimits.max_products`, `editLimits.max_cash_registers`, etc.) son contadores enteros, no dinero, pero su nombre contiene "limit" y "cash", asi que quedaban atrapados por el detector: el campo se convertia de `type="number"` a texto formateado con puntos de miles en vivo, y en un caso verificado con pruebas en navegador esto corrompio el valor guardado (dos numeros terminaron concatenados). Se verifico el arreglo re-probando en navegador real tras el cambio.

### Reportes gobernados por plan y graficas SVG inline

- Decision: `Reportes` ya oculta tarjetas y tablas segun el modulo del plan efectivo (`CompanyPlanResolver::hasModule()`), no solo por permiso de usuario: Cartera/Cartera vencida/Docs vencidos/Aging requieren `credit`, Puntos vigentes requiere `loyalty`, Promociones requiere `promotions`. El servicio deja de ejecutar esas consultas cuando el modulo esta apagado, no solo de mostrarlas.
- Decision: Margen bruto ahora tambien requiere la feature `reports.profitability` del plan (ya existia en `PlanCatalog.php` pero nunca se habia conectado a nada), ademas del permiso `reports.view_costs` que ya tenia.
- Decision: las graficas nuevas (ventas por dia, ventas por sucursal, medios de pago, aging de cartera) se implementaron como SVG generado por Blade en el propio request de Livewire, sin libreria JS de graficas. Los filtros de fecha/sucursal ya eran `wire:model.live`, asi que las graficas heredan el comportamiento AJAX sin trabajo adicional.
- Decision: la paleta de colores de las graficas es la paleta de referencia de la skill `dataviz` del proyecto (ya validada contra separacion CVD), no una paleta ad-hoc con los tonos amber/sky/emerald ya usados en el resto de la UI — un intento con esos tonos fallo el validador.

### Nombre de marca como variable centralizada, no texto fijo

- Decision: reemplazar todas las apariciones visibles del nombre de marca ("Retail SaaS") en titulos, cabeceras, sidebar, footer del ticket y placeholder de configuracion por `\App\Models\PlatformSetting::appName()`, en lugar de texto literal repetido en cada vista.
- Decision: `PlatformSetting::appName()` resuelve en cascada: `platform_settings.app_name` (fila `key='app_name'`, editable en caliente desde `Plataforma > Configuracion > Aplicacion` sin redeploy) y, si esta vacia, cae a `config('app.name')` (variable `APP_NAME` de `.env`, unica que si requiere reconstruir cache de config).
- Razon: el proyecto va a cambiar de nombre comercial pronto y ya existia la mitad de esta infraestructura (el campo "Nombre de la plataforma" en `PlatformSettingsPage` ya guardaba `app_name` en `PlatformSetting`, pero ninguna vista lo leia; los titulos y cabeceras tenian "Retail SaaS" escrito a mano en 8 archivos distintos). Centralizar en un unico metodo estatico deja **un solo lugar para renombrar la marca**: cambiar el campo en `Plataforma > Configuracion` (preferido, sin tocar codigo ni reiniciar contenedores) o, como respaldo antes de tener una empresa plataforma operando, `APP_NAME` en `.env`.
- Decision: se dejaron sin tocar los identificadores tecnicos internos que contienen "retail_saas"/"retailSaas" pero nunca se muestran a un usuario: la clave de `localStorage` `retail_saas_login_debug_history` (`login.blade.php`), el namespace JS `window.retailSaas` y la clave `retail-saas-pos-debug` (`app.js`), y el `User-Agent` HTTP `RetailSaaS/1.0` que `OpenFoodFactsService` envia a la API externa. Son nombres tecnicos internos (ver convencion de `AGENTS.md`), no marca visible; renombrarlos no aporta nada al objetivo de poder cambiar el nombre comercial desde un solo lugar y agregaria riesgo/ruido innecesario (ej. invalidar debug-history ya guardado en el navegador de usuarios reales).
- Pendiente: el nombre comercial final todavia no esta definido (se estan evaluando alternativas a "Retail SaaS"); mientras tanto `APP_NAME` en `.env` sigue en `"Retail SaaS"` y `platform_settings.app_name` esta vacio, asi que la app sigue mostrando "Retail SaaS" por el fallback. Cuando se elija el nombre definitivo, basta con guardarlo en `Plataforma > Configuracion > Aplicacion` (o cambiar `APP_NAME` en `.env` si se prefiere fijarlo como default de codigo) para que se refleje en toda la aplicacion sin tocar ninguna vista.

### Header del dashboard auto-ocultable y grilla de modulos adaptativa

- Decision: en `layouts/app.blade.php`, el header colapsa por defecto a una franja delgada de 12px y se despliega al pasar el mouse (o al tocarla/hacer click, para pantallas tactiles), usando `x-data="{ headerOpen: false }"` con `@mouseenter`/`@mouseleave` en el contenedor y `:class` con `max-h-0`/`max-h-24` + `opacity` para la animacion. Se implemento primero solo para el "modo launcher" (`/dashboard`), pero el usuario pidio extenderlo a todas las paginas autenticadas porque el sistema se usara en monitores chicos y el ahorro de espacio aplica igual en cualquier modulo operativo. Se elimino la bifurcacion `@if($launcherMode) ... @else ...` que duplicaba el markup del header (uno auto-ocultable, otro estatico) y quedo un unico bloque de header compartido por todas las paginas; `$launcherMode` se conserva solo para decidir el fondo (`radial-gradient` en el dashboard vs `bg-stone-100` en el resto), no para el comportamiento del header.
- Decision: la grilla de "Modulos operativos" (`dashboard.blade.php`) agrega el breakpoint intermedio `lg:grid-cols-3` (antes saltaba de `sm:grid-cols-2` directo a `xl:grid-cols-4`, dejando el rango 1024–1279px pegado en 2 columnas) y reduce la altura minima de cada tarjeta (`min-h-[220px]` fijo → `min-h-[160px] sm:min-h-[180px] xl:min-h-[200px]`), para que una resolucion tipica de laptop no deje una fila final con una sola tarjeta huerfana ni la corte el borde del viewport.
- Bug real encontrado y corregido en el camino (no se veia con solo leer el codigo, solo probando en navegador real vía Playwright): la ruta `/dashboard` (`routes/web.php`) es una `Closure` que retorna `view('dashboard', ...)` directamente, **no** un componente Livewire. Livewire v3 solo auto-inyecta su script (que en este proyecto empaqueta tambien Alpine.js — `resources/js/app.js` nunca importa `alpinejs` como paquete propio, depende enteramente del bundle de Livewire) cuando al menos un componente Livewire se renderiza durante el request. Como el dashboard no renderiza ninguno, Alpine nunca se inicializaba ahi: no solo mi header nuevo quedaba inerte, el `x-data="{ sidebarOpen, sidebarCollapsed }"` ya existente en la raiz de `app.blade.php` tampoco funcionaba nunca en esa pagina especifica (sin efecto visible antes porque el modo launcher no renderiza la barra lateral que lo consume). Se agrego `@livewireStyles` en el `<head>` y `@livewireScripts` antes de `</body>` de `layouts/app.blade.php` para forzar la inyeccion en toda pagina que use este layout, sin importar si trae un componente Livewire. Verificado que no rompe ni duplica nada en paginas que ya tenian componentes Livewire (ej. `/products`): 0 errores de consola tras el cambio.
- Pruebas:
  - Verificacion manual en Chromium real (Playwright, corrido en un contenedor `mcr.microsoft.com/playwright` conectado con `--network host` para resolver los assets de Vite dev en `localhost:5174` igual que un navegador real): estado inicial colapsado (header con `height: 1px`), hover sobre la franja lo expande (`height: 81px`), al alejar el mouse vuelve a colapsar; confirmado leyendo `Alpine.$data()` del `x-data` (`headerOpen: false` en reposo). Grilla de modulos verificada en 1366×768, 1280×720 y 1100×700 (breakpoint `lg`): a 1100px pasa de 4+1 (una tarjeta huerfana) a 3+2 tarjetas por fila. Navegacion real desde el dashboard a un modulo Livewire (`Catalogos` → `/products`) sin errores de consola tras agregar `@livewireStyles`/`@livewireScripts`.

### Rediseno visual: superficies neutras con acento por color, sin relleno pastel plano

- Decision: sustituir el estilo de "tarjeta pastel completa" (fondo, borde y texto en el mismo tono saturado claro, ej. `bg-[#dff7ea] text-[#15523c] border-[#97ddba]`) por superficies neutras (`bg-white`/`border-stone-200`) donde el color de cada modulo/estado queda solo como acento puntual: el icono, una barra superior fina de 4px, o una pastilla de estado. Aplica primero a las tarjetas de "Modulos operativos" del dashboard (`dashboard.blade.php`, `$moduleColorTokens`) y despues a todas las pastillas de estado del resto de la aplicacion.
- Decision: crear `resources/views/components/status-badge.blade.php` (`<x-status-badge :color="...">`) como unica fuente de verdad del estilo de pastilla de estado: `bg-{color}-50 text-{color}-700 ring-1 ring-inset ring-{color}-600/20` en vez del anterior `bg-{color}-100 text-{color}-700` de relleno solido. El mapa de colores esta escrito como array PHP con clases Tailwind completas y literales (no interpoladas via `"bg-{$color}-50"`) porque el scanner de contenido de Tailwind solo detecta clases que aparecen como texto literal en el archivo; interpolar el nombre del color en runtime haria que esas clases nunca se generen en el CSS compilado.
- Decision: migrar las ~35 pastillas de estado repartidas en 24 vistas (Ventas, Compras, Productos, Maestras, Caja, Credito, Fidelizacion, Promociones, Plataforma, Admin) a `<x-status-badge>` cuando la pastilla es un `<span>` estatico, y suavizar las clases in-place (`bg-X-100` → `bg-X-50` + `ring-1 ring-inset ring-X-600/20`) cuando es un `<button>` interactivo con `wire:click` (ej. toggle activo/inactivo de productos y proveedores) que no encaja en un componente de solo lectura.
- Decision: no tocar los usos de `bg-{color}-100` que son estados `hover:` (ej. boton "Cerrar sesion" en rosa, hover del toggle activo/inactivo) ni el componente `responsive-nav-link.blade.php` (scaffolding de Breeze sin ninguna referencia `<x-responsive-nav-link>` en el proyecto, código muerto fuera de alcance).
- Decision: asignar un color Tailwind fijo por modulo del dashboard (`amber`=Catalogos, `blue`=Compras, `emerald`=Ventas, `orange`=Inventario, `violet`=Caja, `fuchsia`=Credito, `rose`=Promociones, `teal`=Fidelizacion, `indigo`=Reportes, `stone`=Admin), reutilizando el mismo lenguaje de color en toda la app en vez de paletas hex ad-hoc por pantalla.
- Verificacion: `php artisan view:cache` sin errores (compila las ~85 vistas Blade del proyecto de una sola vez, detecta cualquier `@if`/`@endif`/componente mal cerrado). Verificacion visual en Chromium real (Playwright, contenedor `mcr.microsoft.com/playwright` en red `host`) en dashboard, Productos, Compras, Cuentas por pagar, Ventas, Credito y Caja como `demo.premium`: 0 errores de consola, pastillas renderizando con el nuevo estilo suave en los 7 colores usados.
- Pendiente: no se pudo verificar visualmente `Plataforma`/`Admin > Estructura` en esta sesion por falta de un usuario `is_platform_admin` sembrado localmente; el cambio ahi es el mismo find-and-replace mecanico ya verificado en el resto de la app y paso `view:cache` sin errores, pero queda pendiente una revision visual la proxima vez que haya sesion con superadmin.

### Correos de registro: aviso al dueno de plataforma y bienvenida con contrasena en texto plano

- Decision: al completar `register()` en `resources/views/livewire/pages/auth/register.blade.php` (registro publico, antes de crear la `Company`), se envian dos correos: `App\Mail\NewAccountRegisteredMail` al correo configurado en `PlatformSetting::ownerNotificationEmail()` (aviso de que alguien se registro), y `App\Mail\WelcomeUserMail` al correo del usuario nuevo (bienvenida con su `username` y contrasena).
- Decision: el correo de bienvenida **incluye la contrasena en texto plano**. Se le explico al usuario el riesgo real (queda sin cifrar en la bandeja de entrada de forma permanente, expuesta ante cualquier acceso indebido al correo) frente a dos alternativas mas seguras (contrasena temporal + cambio obligatorio, o enlace para definir contrasena) y **eligio expresamente enviarla igual**, priorizando que los tenderos (publico no tecnico, alta probabilidad de olvidar su clave) puedan recuperarla facilmente. Esto es una excepcion deliberada al precedente ya establecido en el proyecto: `Plataforma > Usuarios` (reseteo de contrasena, ver seccion "Restablecer contraseña de usuario...") nunca envia la contrasena por correo, solo la muestra una vez en un modal de la UI. Si en el futuro se quiere reforzar seguridad sin perder la conveniencia, la opcion de contrasena temporal + cambio obligatorio en el primer login queda como la mejora natural.
- Decision: `WelcomeUserMail` (contiene la contrasena) **no implementa `ShouldQueue`** y se envia de forma sincrona con `Mail::send()`, a proposito, para no dejar la contrasena en texto plano parqueada en la tabla `jobs` mientras espera a que el worker la procese. `NewAccountRegisteredMail` (sin datos sensibles) si se encola normalmente con `Mail::queue()`, aprovechando el contenedor `queue` ya existente.
- Decision: ambos envios estan envueltos en `try/catch` con `Log::error()` — un fallo de SMTP (host caido, credenciales invalidas, etc.) nunca debe impedir que el registro se complete; el usuario ya quedo creado y logueado independientemente de si el correo llego o no.
- Decision: el destinatario del aviso de nueva cuenta es un nuevo ajuste de plataforma, `PlatformSetting::ownerNotificationEmail()` (clave `owner_notification_email`, default `jupazago11@gmail.com`), editable desde `Plataforma > Configuracion > Aplicacion`. Se creo como campo separado de `contact_email` (ya existente) porque ese campo es el "correo de soporte" que se muestra a los clientes en la pagina de espera de pago — un proposito distinto (contacto publico) al de este nuevo campo (alerta interna al dueno de la plataforma). Mismo patron que `app_name`: una sola variable editable desde la UI, sin tocar codigo, para cuando el usuario cree "uno independiente" mas adelante.
- Decision: las plantillas de correo (`resources/views/emails/{welcome-user,new-account-admin}.blade.php`) usan HTML basado en tablas con estilos inline (no la convencion de variables CSS de `printing/sales/ticket.blade.php`), porque los clientes de correo (Outlook de escritorio en particular) no soportan `<style>` con variables CSS ni layouts flex/grid de forma confiable; tablas + inline es el estandar de compatibilidad para email transaccional. Reutilizan la misma paleta ambar/stone y `PlatformSetting::appName()` para mantener coherencia de marca con el resto de la aplicacion.
- Pruebas: verificado extremo a extremo contra Mailpit real (registro real via Playwright, `GET /api/v1/messages` de la API de Mailpit confirma 2 correos recibidos, capturas de pantalla del HTML renderizado de ambos vía `/view/{id}.html`). Confirmado que el correo de aviso al admin fue procesado por el contenedor `queue` sin intervencion manual. Sin errores en logs de `app` ni `queue` tras el envio.

### Codigo de acceso fijo para frenar registros no deseados, y correo de aviso sincrono (no en cola)

- Bug real encontrado y corregido: tras un `php artisan migrate:fresh` (usado para limpiar la base local de datos de prueba), el contenedor `queue` se cayo por completo. `Illuminate\Queue\Worker::stopIfNecessary()` consulta periodicamente una clave de cache (`illuminate:queue:restart`) contra la tabla `cache`; como esa tabla se borra y se recrea durante el `fresh`, el worker de larga duracion choco justo en ese instante contra "relation cache does not exist", lanzo una excepcion no capturada, y el proceso PHP en primer plano del contenedor `queue` termino — sin politica de reinicio, el contenedor quedo apagado indefinidamente. El correo `NewAccountRegisteredMail` (aviso al dueno de la plataforma) se encolaba con `Mail::queue()`, asi que quedo atrapado en la tabla `jobs` sin nadie que lo procesara: el registro se completo con normalidad, pero el aviso nunca salio.
- Decision: agregar `restart: unless-stopped` al servicio `queue` en `docker-compose.yml`, para que Docker lo reinicie solo si su proceso muere por cualquier causa (este tipo de crash puntual, un deploy, un OOM, etc.).
- Decision: `NewAccountRegisteredMail` deja de implementar `ShouldQueue` y pasa a enviarse de forma sincrona (`Mail::send()`) igual que `WelcomeUserMail`, en el mismo `register()`. Razon: el proposito completo de este correo es que el dueno de la plataforma se entere de cada registro; que su entrega dependa de un proceso worker separado que puede morir silenciosamente (como se acaba de comprobar en vivo) contradice ese objetivo. El costo (un round-trip SMTP extra bloqueando el request de registro, ~1-2s) es aceptable porque el registro es una accion infrecuente, no un flujo de alto trafico.
- Decision: agregar un campo `accessCode` obligatorio al formulario publico de registro (`register.blade.php`), comparado contra una constante fija en el propio componente (`private const ACCESS_CODE = '1998'`), **sin** persistirlo en base de datos ni en `PlatformSetting`. Razon (pedido explicito del usuario): frenar registros no deseados de gente que llegue al formulario publico sin haber sido invitada, sin necesidad de una tabla de codigos ni panel de administracion — un codigo compartido de boca en boca alcanza para el proposito. Al validarse en el metodo `register()` (backend, via Livewire), el codigo nunca se envia al navegador en ningun momento (no aparece en el HTML, JS, ni en la respuesta AJAX), asi que no es descubrible inspeccionando la pagina; solo lo puede usar quien ya lo conoce de antemano.
- Decision: el campo se renderiza como `type="password"` (igual que los campos de contrasena) para que no se vea en pantalla mientras se escribe, con mensajes de error personalizados ("El código de acceso no es correcto") en vez del texto generico de la regla `in:` de Laravel.
- Pruebas: verificado en vivo contra el stack completo (registro real con codigo incorrecto → error visible, campos preservados, ningun usuario creado en BD; registro real con codigo correcto `1998` → usuario creado en BD, sin excepciones en `storage/logs/laravel.log` durante el envio de ambos correos). Confirmado que el contenedor `queue` se recupero solo tras el crash (con la nueva politica `restart: unless-stopped`) y proceso el correo de aviso que habia quedado atrapado.

### Sistema de diseno estilo shadcn/ui: azul de marca, radios y componentes consistentes

- Decision: el usuario pidio replicar el look de un sistema de diseno tipo shadcn/ui (React + Tailwind + Radix, usado como referencia en otro proyecto) sobre este stack (Blade + Tailwind + Livewire): azul `#2563eb`/`#1d4ed8` como color de marca en vez del ambar/stone calido previo, radios `8px` en botones/inputs (`rounded-lg`) y `12px` en cards (`rounded-xl`), superficies neutras grises en vez de calidas, tablas con filas alternadas, y gradiente azul->morado reservado solo para el CTA del hero publico y los headers de los correos transaccionales.
- Decision de alcance (confirmada con el usuario antes de empezar): el spec original pedia un sidebar de navegacion persistente, pero la app **no tiene uno hoy** — la navegacion real es el dashboard como "launcher" (grid de tarjetas de modulos) y el archivo `livewire/layout/navigation.blade.php` con markup de sidebar nunca se incluye en ningun layout (confirmado por grep, cero referencias). Construir un sidebar nuevo habria sido un cambio de arquitectura de navegacion, no un restyle. El usuario eligio mantener el dashboard-launcher tal como esta; no se agrego sidebar nuevo a la app operativa.
- Hallazgo real durante la implementacion: `layouts/platform.blade.php` (el area de Plataforma/superadmin) **si tiene** un sidebar real y usado (`<aside class="w-56 ...">`, distinto del archivo muerto de arriba). Se restyleo ese sidebar existente al patron del spec (fondo `#fafafa`, item activo con `bg-blue-50 text-blue-700`, item inactivo en gris) porque es navegacion real, no scaffolding sin conectar.
- Decision: los componentes compartidos casi no se usan en la app operativa — `x-primary-button`, `x-secondary-button`, `x-danger-button`, `x-text-input`, `x-input-label` y `x-modal` (todos herencia de Laravel Breeze) solo aparecen en las 9 pantallas de autenticacion/perfil; el resto de la aplicacion (Ventas, Compras, Caja, Productos, Plataforma, etc.) tiene sus clases Tailwind escritas a mano por archivo. Se actualizaron los componentes compartidos (beneficia las 9 pantallas de auth) y, ademas, se aplico un barrido mecanico de las clases inline repetidas en el resto de vistas, en vez de asumir que centralizar los componentes alcanzaba para cubrir toda la app.
- Metodo del barrido: reemplazos literales de clases Tailwind via `sed` sobre todo `resources/views/**/*.blade.php` (seguro porque son sustituciones de texto exacto, no logica): `rounded-3xl`→`rounded-xl`, `rounded-2xl`→`rounded-lg`, `ring/border/bg/text-stone-*`→sus equivalentes `gray-*`, `bg-stone-900`+`hover:bg-stone-700`/`hover:bg-amber-700` (boton solido oscuro)→`bg-blue-600`+`hover:bg-blue-700`, `focus:border-amber-500 focus:ring-amber-500`→azul, y `text-amber-600`/`700` (usado como acento de marca en labels "eyebrow") → azul.
- Bug real encontrado y corregido en el camino: el primer barrido (`text-amber-700`→`text-blue-700` global) rompio por accidente el color semantico "amber" de `<x-status-badge>` y de varios estados de negocio que usan amber como color de advertencia/pendiente (ej. "Dev. parcial" en ventas, indicador de cliente sin credito habilitado en el POS) — quedaron con fondo ambar pero texto azul, una mezcla inconsistente. Se identificaron y revirtieron todos los casos verificando cada archivo con `grep` antes de dar el barrido por terminado (`bg-amber-*` + `text-blue-*` en la misma linea = mezcla sospechosa). Leccion: un color que aparece tanto como "acento de marca" (debe migrar) y como "significado semantico de negocio" (debe quedarse) en el mismo codebase no se puede migrar con un solo `sed` ciego; hace falta revisar cada coincidencia.
- Decision: se dejo fuera de este barrido, a proposito, la seccion de cobro del POS (`livewire/sales/pos-page.blade.php`, botones `Confirmar y cobrar`/selector de precio/teclado numerico) — ya usaba una estetica deliberadamente distinta tipo "registradora fisica" (grises `#5c5c5c`/`#f7f7f7`, boton de pago con gradiente ambar/dorado) de una sesion anterior, no relacionada con la marca general de la app. Convertir el boton de pago mas importante de toda la aplicacion a azul solo por consistencia estricta con el spec se considero mas riesgoso que valioso; queda como excepcion documentada, no como omision silenciosa.
- Decision: la pagina publica de marketing (`welcome.blade.php`) solo recibio el CTA principal con el gradiente azul->morado del spec; el resto del hero oscuro (badge "Multiempresa", tarjetas de estado) se dejo con sus acentos ambar originales, por ser de menor prioridad frente a la app operativa real. Queda pendiente si el usuario quiere extender el rediseño ahi tambien.
- Decision: las plantillas de correo (`emails/welcome-user.blade.php`, `emails/new-account-admin.blade.php`) migraron su header solido oscuro al gradiente `linear-gradient(135deg, #2563eb, #7c3aed)` (uno de los 2 usos de gradiente explicitamente permitidos por el spec), radio de card de 24px a 12px, y la caja de credenciales de bienvenida paso de un tratamiento ambar a los tokens "info azul claro" del spec (`#eff6ff` bg, `#bfdbfe` borde), ya que el spec no define un color "warning" propio y presentar la contraseña como informacion (no como advertencia) es mas preciso.
- Verificacion: `php artisan view:cache` compilo las ~85 vistas Blade sin errores tras el barrido masivo (unico chequeo viable para detectar sintaxis rota a esa escala). Verificacion visual en Chromium real (Playwright) en: landing publica, login, dashboard, Productos (tabla con striping), Compras (pestañas activas en azul, badges de estado), Credito, y Plataforma > Empresas (sidebar claro con azul) — promoviendo temporalmente un usuario demo a `is_platform_admin` solo para la captura y revirtiendolo al terminar. Cero errores de consola en todas las pantallas.

### Fallas de validacion en el POS deben mostrarse siempre, nunca fallar en silencio

- Bug real encontrado: `PosPage::saveSale()` llamaba `$this->validate([...])` sin capturar la `ValidationException`, y `pos-page.blade.php` no tiene ninguna directiva `@error` en absoluto. Livewire maneja esa excepcion internamente (recompila el componente con un `$errors` bag), pero como ningun elemento del formulario lee ese bag, el usuario no veia nada — ni toast, ni mensaje, ni cambio visual — cuando la venta no pasaba validacion. Reportado como "el boton Confirmar y cobrar no hace nada". Caso concreto reproducido: `cashSessionId` es requerido cuando la venta es de contado y `pos.requires_open_cash_session` esta activo (default), pero si la empresa no tiene ninguna `cash_session` en estado `open`, el campo queda vacio.
- Decision: envolver el `$this->validate(...)` de `saveSale()` en `try/catch (\Illuminate\Validation\ValidationException $e)`, mostrando `collect($e->errors())->flatten()->first()` como toast. Esto no es un parche puntual solo para el caso de caja: cualquier fallo de validacion futuro en ese metodo (branch, warehouse, items, payments, etc.) ahora se muestra igual, en vez de repetir el mismo patron de fallo silencioso.
- Decision: mensaje personalizado para el caso mas comun y menos autoexplicativo (`cashSessionId.required` → "Debes abrir una sesión de caja antes de cobrar.") en vez del texto generico de Laravel ("El campo cash session id es obligatorio.", que ademas ni siquiera menciona que hay que *abrir* una sesion).
- Nota tecnica para futuras reglas de validacion en este proyecto: `Illuminate\Validation\Rule::requiredIf($condition)` dispara la regla de mensaje **`required`** cuando la condicion es verdadera, no `required_if` — un mensaje personalizado bajo la clave `campo.required_if` nunca se aplica y falla en silencio (el propio bug que motivo esta nota: el primer intento de mensaje personalizado uso la clave equivocada, y solo se detecto probando en navegador real).
- Riesgo pendiente, no resuelto en este cambio: `pos-page.blade.php` sigue sin ninguna directiva `@error` para campos especificos (branch, warehouse, items, cashSessionId no tiene ningun input visible propio). El try/catch generico cubre el sintoma (el usuario ya ve *algun* mensaje), pero la causa estructural — ningun campo del formulario muestra su propio error inline — sigue sin resolverse. Si aparecen mas reportes de "no hace nada" en el POS, revisar primero si el error es real pero queda mudo por falta de `@error` en el campo especifico.

## Puertos y nombres aprobados para el proyecto

- `8088`: web local
- `5174`: Vite
- `5434`: forward de PostgreSQL
- `1027`: SMTP Mailpit
- `8027`: UI Mailpit

## Riesgos tecnicos

- Fugas tenant si se omiten scopes o policies.
- Dependencia accidental del Node del host si no se documenta el uso del contenedor `vite`.
- Reglas de inventario complejas si no se centralizan transacciones.
- Riesgo de divergencia entre planes, features y permisos si se resuelve con condicionales ad hoc.
- Railway no permite depender de nombres internos de Docker local.

## Preguntas no bloqueantes aun

- Se usara factura electronica primero con Factus o Facturia.
- El ticket termico se renderizara como HTML imprimible o PDF dedicado.
- La importacion Excel requerira modo parcial por filas validas desde la primera entrega o despues del MVP base.
