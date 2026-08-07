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

- `frozen_sales_enabled`
- `frozen_sales_expiration_minutes`
- `allow_alternative_prices`
- `allow_manual_discounts`
- `allow_promotion_stacking`
- `allow_negative_stock`
- `requires_open_cash_session`
- `require_customer_for_credit_sale`
- `sale_document_prefix`
- `sale_document_starting_sequence`

### `inventory`

- `inventory_enabled`
- `minimum_stock_alerts_enabled`
- `default_cost_method`

### `cash`

- `opening_required`
- `default_opening_amount`
- `allow_close_with_difference`

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
- El backend ya consume `pos.frozen_sales_enabled` y `pos.frozen_sales_expiration_minutes` al crear ventas congeladas.
- El backend ya consume `pos.requires_open_cash_session`, `cash.opening_required`, `cash.default_opening_amount` y `cash.allow_close_with_difference` en el flujo de caja y pagos.
- El backend ya consume `credit.credit_enabled`, `credit.default_term_days`, `credit.block_new_credit_if_overdue` y `pos.require_customer_for_credit_sale` en ventas a credito y abonos.
- El backend ya consume `loyalty.loyalty_enabled`, `loyalty.points_rule_type` y `loyalty.points_rate` para acumulacion, redencion y reverso de puntos.
- El backend ya consume `loyalty.points_expiration_days` para expirar puntos por FIFO; si el valor es `0` o menor, la expiracion automatica queda deshabilitada.
- El backend ya consume `pos.allow_promotion_stacking` para decidir si una unidad usada por combo puede volver a recibir promocion por producto.
- El backend ya consume `printing.ticket_format`, `printing.show_logo` y `printing.show_saas_branding` en la primera vista imprimible de ticket de venta.
- El backend ya consume `pos.allow_manual_discounts` para bloquear descuentos manuales cuando la empresa no los permite.
- El backend ya consume `pos.allow_negative_stock` para permitir o bloquear salidas `sale_out` sin saldo disponible.
- El backend ya consume `pos.sale_document_prefix` y `pos.sale_document_starting_sequence` para emitir la numeracion interna de ventas POS sin depender de facturacion electronica.
- El grupo `electronic_billing` ya existe a nivel de configuracion tipada, pero por ahora funciona como base operativa y de integracion futura, no como emision electronica completa.
