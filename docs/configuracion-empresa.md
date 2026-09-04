# Configuracion por Empresa

## Enfoque

No usar una tabla de cientos de columnas ni un unico JSON opaco. La configuracion se almacenara como claves tipadas agrupadas por dominio.

## Grupos y claves iniciales

### `general`

- `legal_name`
- `display_name`
- `tax_id`
- `phone`
- `address`
- `logo_path`
- `primary_color`

### `pos`

- `allow_alternative_prices`
- `allow_manual_discounts`
- `allow_promotion_stacking`
- `allow_negative_stock`
- `require_customer_for_credit_sale`

Ventas congeladas ya no es una clave de esta empresa: es 100% la feature de plan `pos.frozen_sales` (`CompanyPlanResolver`), decidida por el superadmin al configurar el plan, no algo que la empresa prenda o apague. El numero de documento tampoco es configurable por la empresa: `SaleDocumentNumberGenerator` genera `document_number` solo numerico (sin prefijo de letras ni separador), continuando siempre automaticamente desde el ultimo `document_sequence` registrado. Se probo un prefijo de 3 letras derivado del nombre comercial y se descarto — un separador como "-" es justo lo que un lector laser puede traducir mal segun el layout de teclado del equipo.

`requires_open_cash_session` ya no existe: cobrar una venta (o registrar un abono a credito) nunca exige una sesion de caja abierta. Caja quedo desligada de la venta — es una herramienta puramente manual e independiente (se anota a mano cuando abre, sus retiros/gastos, y el conteo final al cerrar). Si al cobrar hay una sesion de caja abierta, el pago se puede asociar a ella para el cuadre de "efectivo esperado"; si no hay ninguna abierta, la venta y el pago se registran igual, sin `cash_session_id` (columna ya nullable en `payments`), y ese efectivo simplemente no aparece en ningun cuadre de caja porque nada lo vincula a una sesion.

### `inventory`

- `inventory_enabled`
- `minimum_stock_alerts_enabled`
- `default_cost_method`
- `tracking_mode` (solo vertical restaurante: `simple`/`recipe`, ver "Recetas y costeo por insumo" en `modelo-datos.md`)

### `cash`

- `opening_required`
- `default_opening_amount`

`allow_close_with_difference` ya no existe: el cierre de una sesion de caja siempre se permite aunque el conteo no coincida con el efectivo esperado. La diferencia (`difference_amount`) queda registrada en la sesion (estado `closed` en vez de `reconciled`) para auditoria, pero nunca bloquea el cierre.

### `printing`

- `ticket_format`
- `show_logo`
- `show_saas_branding`

### `credit`

- `credit_enabled`
- `default_term_days`
- `block_new_credit_if_overdue`

### `loyalty`

- `loyalty_enabled`
- `points_rule_type`
- `points_rate`
- `points_expiration_days`

### `electronic_billing`

- `enabled`
- `provider`
- `environment`
- `resolution_number`
- `prefix`
- `technical_key`
- `software_id`
- `software_pin`
- `sequence_current`
- `sequence_max`

## Regla de acceso

- La configuracion efectiva se resuelve por servicio, no leyendo claves sueltas desde Blade.
- Cambios sensibles deben auditarse.

## Estado actual de implementacion

- La tabla `company_settings` ya existe.
- El backend expone el servicio `App\Services\Settings\CompanySettings`.
- Las claves validas se centralizan en `App\Support\Settings\CompanySettingCatalog`.
- El servicio ya resuelve valores por defecto del catalogo cuando una clave aun no se ha persistido.
- Los tipos soportados actualmente son `string`, `integer`, `decimal`, `boolean` y `json`.
- El backend ya consume `pos.frozen_sales_enabled` al crear ventas congeladas. La expiracion es una regla fija de 24 horas (`CreateFrozenSale::EXPIRATION_MINUTES`), ya no es configurable por empresa.
- El backend ya consume `cash.opening_required` en el flujo de caja; `cash.default_opening_amount` es el valor sugerido al abrir. La sesion de caja abierta ya no es requisito para cobrar una venta ni para registrar un abono a credito.
- El backend ya consume `credit.credit_enabled`, `credit.default_term_days`, `credit.block_new_credit_if_overdue` y `pos.require_customer_for_credit_sale` en ventas a credito y abonos.
- El backend ya consume `loyalty.loyalty_enabled`, `loyalty.points_rule_type` y `loyalty.points_rate` para acumulacion, redencion y reverso de puntos.
- El backend ya consume `loyalty.points_expiration_days` para expirar puntos por FIFO; si el valor es `0` o menor, la expiracion automatica queda deshabilitada.
- El backend ya consume `pos.allow_promotion_stacking` para decidir si una unidad usada por combo puede volver a recibir promocion por producto.
- El backend ya consume `printing.ticket_format`, `printing.show_logo` y `printing.show_saas_branding` en la primera vista imprimible de ticket de venta.
- El backend ya consume `pos.allow_manual_discounts` para bloquear descuentos manuales cuando la empresa no los permite.
- El backend ya consume `pos.allow_negative_stock` para permitir o bloquear salidas `sale_out` sin saldo disponible.
- El backend ya consume `pos.sale_document_prefix` y `pos.sale_document_starting_sequence` para emitir la numeracion interna de ventas POS sin depender de facturacion electronica.
- `inventory.tracking_mode` ya se pregunta una vez por empresa restaurante (modal `App\Livewire\Company\TrackingModeGate`, no la pagina de Configuracion general), pero todavia no alimenta ninguna logica: `PostSaleToInventory`/`ReturnSaleToInventory` deciden por producto segun `products.is_recipe`, no leen esta clave. Y `products.is_recipe` en si mismo todavia no es editable desde `ProductsPage` (el formulario de crear/editar producto) — falta esa UI para que la respuesta de este modal tenga un efecto practico completo.
- El grupo `electronic_billing` ya existe a nivel de configuracion tipada, pero por ahora funciona como base operativa y de integracion futura, no como emision electronica completa.
