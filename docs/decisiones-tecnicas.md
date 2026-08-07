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
