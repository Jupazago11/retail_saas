# Reglas de Inventario

## Principios

- Costo base del MVP: promedio ponderado.
- Stock por bodega.
- Kardex como fuente de verdad de movimientos.
- Las compras confirmadas, sus devoluciones, los ajustes manuales, los traslados y las ventas confirmadas ya generan `inventory_movements` y actualizan `inventory_balances`.

## Reglas

1. Venta confirmada descuenta stock.
2. Venta congelada no toca stock.
3. Venta a credito descuenta stock.
4. Devolucion reintegra stock.
5. Anulacion genera movimientos compensatorios.
6. Producto sin control de inventario no genera kardex.
7. Ajustes y traslados siempre dejan trazabilidad.
8. No modificar stock directamente en `products`.
9. Usar transacciones y locking en operaciones criticas.
10. Stock negativo deshabilitado por defecto.

## Presentaciones

- El stock se almacena en unidad base.
- Una compra o venta en presentacion convierte por `conversion_factor`.
- Nunca usar `float` para factores ni cantidades.
- La conversion actual del proyecto se implementa con `bcmath` para mantener precision decimal controlada.
- La base de compras persiste `base_quantity`; el posting a inventario usa esa cantidad base para kardex y saldos.

## Costo

- El costo promedio actual se actualiza con promedio ponderado en cada entrada de compra confirmada.
- El `unit_cost` del kardex se guarda en costo por unidad base, no por presentacion.
- La devolucion de compra sale al costo promedio vigente del saldo para no distorsionar la valoracion por promedio ponderado.
- Si una devolucion deja el saldo en cero, `average_cost` vuelve a `0.0000`.
- Los ajustes de entrada actualizan `average_cost` con promedio ponderado y los ajustes de salida salen al costo promedio vigente.
- Los traslados mueven el costo promedio vigente de la bodega origen y recalculan el promedio de la bodega destino.
- Las ventas confirmadas salen al costo promedio vigente del saldo y guardan ese valor en `cost_snapshot`.
- Si una venta consume el saldo completo de una combinacion producto-bodega-variante, `average_cost` vuelve a `0.0000`.
- La devolucion de venta reingresa inventario usando `cost_snapshot` de la linea original para no revaluar retroactivamente la salida historica.
- El reingreso por devolucion recalcula `average_cost` por promedio ponderado con ese costo de retorno.

## Devoluciones de compra

- Solo se permite devolver una compra que ya haya sido aplicada a inventario.
- La devolucion es idempotente mediante `returned_from_inventory_at`.
- Si el stock disponible ya no alcanza para revertir la compra, la operacion se rechaza.

## Ajustes manuales

- El ajuste `increase` requiere `unit_cost` mayor a cero.
- El ajuste `decrease` no permite dejar stock negativo.
- El ajuste usa `posted_to_inventory_at` para evitar reposteo duplicado.
- La salida por ajuste conserva el `average_cost` mientras quede stock; si el saldo llega a cero, el costo promedio vuelve a cero.

## Traslados

- El traslado requiere bodegas origen y destino distintas.
- El traslado no permite dejar stock negativo en la bodega origen.
- El traslado usa `posted_to_inventory_at` para evitar reposteo duplicado.
- Cada linea genera dos movimientos: `transfer_out` en origen y `transfer_in` en destino.

## Ventas

- La venta `confirmed` descuenta inventario; la `draft` no toca stock.
- La venta usa `posted_to_inventory_at` para evitar reposteo duplicado.
- Si el stock no alcanza al confirmar, la venta completa se rechaza dentro de la misma transaccion.
- La salida de inventario usa `base_quantity`, incluso cuando la linea se vende por presentacion.
- La devolucion de venta solo se permite sobre ventas `confirmed` o `partially_returned`.
- Cada linea controla `returned_quantity` y `returned_base_quantity` para impedir devolver mas de lo vendido.
- La venta pasa a `partially_returned` o `returned` segun el saldo pendiente por linea.
- La anulacion de una venta confirmada repone el saldo pendiente de todas sus lineas y luego cambia la venta a `cancelled`.
- La anulacion de una venta confirmada revierte pagos `confirmed` a `reversed`; la venta no se elimina.
- En ventas a credito, el stock se mueve igual que en contado; lo financiero se resuelve aparte en `credit_movements`.
- En fidelizacion, la venta puede generar puntos, pero eso no altera el kardex ni los saldos fisicos; solo crea movimientos en `loyalty_movements`.
- En este corte base no se permite anular ni devolver una venta a credito si ya tiene abonos confirmados.

## Ventas congeladas

- La venta congelada no descuenta ni reserva stock.
- La venta congelada expira siempre a las 24 horas (regla fija, no configurable por empresa).
- La conversion de una venta congelada a venta real reutiliza el snapshot guardado y solo ahi intenta la salida de inventario.

## Variantes

- El saldo se resuelve por `product_variant_id` cuando aplique.
- Si el producto no usa variantes, `product_variant_id` puede ser null.
- Los saldos sin variante ya quedan protegidos contra duplicados por una restriccion unica dedicada.
