# Flujo POS

## Flujo principal

1. Validar empresa activa.
2. Validar sucursal activa.
3. Validar caja activa.
4. Validar sesion de caja si la empresa la requiere.
5. Buscar producto por SKU, codigo de barras o nombre.
6. Elegir presentacion, variante, cantidad y precio autorizado.
7. Aplicar promocion automatica.
8. Aplicar descuento manual si el usuario tiene permiso.
9. Seleccionar cliente o consumidor final.
10. Congelar o continuar al cobro.
11. Registrar uno o varios pagos.
12. Confirmar venta dentro de una transaccion atomica.
13. Persistir venta y lineas.
14. Persistir pagos.
15. Registrar movimientos de inventario.
16. Registrar impacto de caja.
17. Emitir ticket.
18. Registrar auditoria.

## Campos por plan

### Basic

- Visible:
  - barra superior de acciones tipo terminal POS
  - `producto_lookup` como `input text`
  - lista sugerida de productos por nombre, SKU o codigo de barras
  - lineas de factura
  - cantidad
  - tarifa `V1/V2/V3` solo si el producto tiene multiples precios cargados
  - pagos
  - totales
- Oculto:
  - selector manual de sucursal
  - selector manual de bodega
  - selector manual de caja
  - selector manual de cliente
  - fecha de venta editable
- Regla:
  - `Enter` sobre `producto_lookup` agrega coincidencia exacta por codigo/SKU o, si no existe, la primera sugerencia por nombre.
  - La fecha de venta se toma automaticamente al confirmar.
  - Sucursal, bodega y caja se resuelven desde la configuracion activa de la empresa.
  - Si la empresa tiene habilitada la feature `pos.frozen_sales` y el usuario posee permiso `sales.freeze`, la barra superior expone la accion `Congelar`.
  - La composicion visual del `basic` debe sentirse como terminal de caja: cabecera tecnica, rejilla compacta de iconos, area central de lineas y franja inferior de captura/totales.
  - La tarifa se cambia desde la propia celda `V1/V2/V3` con un clic simple; si el producto solo tiene una tarifa, la celda queda fija.

### Pro y superiores

- Pueden habilitarse despues:
  - seleccion manual de cliente
  - cambio explicito de sucursal, bodega y caja
  - personalizaciones del flujo comercial y credito

## Comportamiento del buscador de producto

- El input principal acepta escaner de codigo de barras y teclado normal.
- Si el lector envia codigo y luego `Enter`, el producto se agrega sin clic adicional cuando hay coincidencia exacta.
- Si la busqueda es por nombre, el input despliega sugerencias y el usuario puede:
  - hacer clic sobre la opcion deseada
  - presionar `Enter` para tomar la primera sugerencia
- Si solo existe una tarifa, la celda de tarifa queda fija y no cambia con clic.

## Reglas clave

- Las ventas congeladas no descuentan ni reservan stock.
- Las lineas guardan snapshots de descripcion, precio, impuesto y costo.
- Las promociones se resuelven antes del calculo final de la linea; los combos se aplican antes que los descuentos por producto.
- Si `pos.allow_promotion_stacking` esta desactivado, una unidad consumida por combo no vuelve a recibir promocion por producto.
- El total debe coincidir exactamente con la suma de pagos.
- No debe existir recarga completa dentro del modulo.

## Estado actual

- El backend ya cubre la base de `sales` y `sale_items`.
- La confirmacion de venta ya genera `sale_out` en inventario y actualiza `cost_snapshot`.
- El backend ya cubre la base de `frozen_sales` con creacion, expiracion, cancelacion y conversion.
- El backend ya cubre la base de `payments` y `cash_sessions`.
- El backend ya cubre devoluciones parciales y totales de venta con reingreso a inventario.
- El backend ya cubre anulacion de ventas `draft` y `confirmed`, incluyendo reverso de pagos confirmados.
- El backend ya cubre `customers`, `credit_accounts` y `credit_movements` para ventas a credito y abonos.
- La venta a credito hoy exige cliente con credito habilitado y genera el cargo al confirmar.
- Los abonos se registran por venta a credito; no usan el flujo de `RegisterSalePayments`.
- El backend ya cubre `loyalty_accounts` y `loyalty_movements` para acumulacion y reverso base de puntos.
- La fidelizacion hoy acumula puntos al confirmar y los revierte proporcionalmente en devoluciones o anulaciones.
- El backend ya cubre `promotions`, `promotion_targets` y `promotion_combo_items`.
- La venta hoy puede aplicar promociones `product_discount` y `combo_price`, guardando `promotion_snapshot` por linea y resumen en `pricing_snapshot`.
- El backoffice ya cuenta con una primera pantalla `Promociones` para crear descuentos por producto y combos a precio fijo.
- El backoffice ya cuenta con una primera pantalla `Fidelizacion` para consultar cuentas, revisar movimientos y ejecutar expiracion manual.
- La pantalla `Fidelizacion` ya permite tambien ajustes manuales de puntos con nota operativa y reflejo inmediato en el ledger.
- El POS ya permite redimir puntos de fidelizacion en ventas confirmadas con cliente elegible, guardando la redencion en `pricing_snapshot.loyalty_redemption`.
- El POS ya muestra un preview comercial provisional reutilizando el motor real de promociones y la logica de redencion de puntos antes de confirmar.
- El POS actual ya usa una maqueta mas cercana a terminal comercial clasica, con toolbar de iconos, auditoria visible, lienzo de lineas y resumen inferior operativo.
- El backend ya registra `audit_logs` para venta creada, pagos registrados, devoluciones y anulaciones.
- El backoffice ya cuenta con una primera pantalla `Ventas` para consultar documentos y abrir su ticket imprimible.
- El backoffice ya cuenta con una primera pantalla `POS` para crear ventas `draft` o `confirmed`, registrar cobro inmediato en ventas POS confirmadas y abrir el ticket del ultimo documento creado.
- El POS `basic` ya opera con un unico `input text` de producto y oculta selectores manuales de sucursal, bodega, caja, cliente y fecha editable.
- La vista POS `basic` ya usa una composicion tipo terminal: banda superior de acciones rapidas (toolbar de 2 filas de 5 botones con badges amber en fila 1 y rojo en fila 2), lienzo central expandido para lineas (min-height 42rem) y franja inferior integrada con captura, indicadores, totales e info operativa.
- El cobro inmediato ya soporta uno o varios pagos y respeta si la empresa exige o no sesion de caja abierta.
- El backoffice ya cuenta con una primera pantalla `Congeladas` para guardar, retomar, convertir y cancelar ventas congeladas.
- La pantalla `Ventas` ya permite una primera operacion directa de devoluciones parciales y anulaciones.
- La pantalla `Ventas` ya permite "Modificar" una venta POS confirmada: recarga sus lineas (producto, cantidad, lista de precio) en el POS para que el cajero las ajuste; al confirmar, `App\Actions\Sales\ModifySale` anula el documento original y crea uno nuevo enlazado por `sales.replaces_sale_id`, en una sola operacion atomica.
- Aun falta la UI operativa completa de cierres comerciales, caja avanzada y refinamientos finales del POS.
