# Modelo de Datos

## Estado del documento

Version conceptual con ajuste incremental segun las migraciones ya creadas. Cuando una nota indica "implementacion inicial", refleja el estado real del codigo actual.

## Reglas globales

- Base de datos unica PostgreSQL.
- `company_id` en toda tabla operativa multiempresa.
- `decimal` para dinero y cantidades.
- `deleted_at` solo en entidades archivables.
- Auditoria por eventos o tablas dedicadas, no por edicion manual.

## Dominios

### Core SaaS

#### `users`

- Proposito: identidad global y autenticacion central.
- Campos clave: `id`, `username`, `email`, `password`, `status`, `last_login_at`, `created_at`, `updated_at`.
- Tipo sugerido: `bigint`, `varchar`, `timestamp`.
- Unique: `username`, `email` nullable unique parcial si se decide permitir empleados sin correo repetido.
- Soft delete: no.

#### `companies`

- Proposito: tenant principal.
- Campos clave: `id`, `owner_user_id`, `legal_name`, `display_name`, `tax_id`, `status`, `subscription_status`, `auto_renew`, `created_at`, `updated_at`.
- Soft delete: si.
- Nota de implementacion actual: `auto_renew` gobierna si el comando diario `subscriptions:process-due` renueva automaticamente la suscripcion directa de la empresa al vencer, o si solo la cierra dejando la reactivacion en manos de un `platform_super_admin`.

#### `company_user`

- Proposito: membresia usuario-empresa.
- Campos clave: `company_id`, `user_id`, `company_role`, `company_role_id`, `status`, `joined_at`.
- Unique: `company_id + user_id`.
- Nota de implementacion actual: `company_role` se mantiene como etiqueta semantica; el valor `'owner'` es especial y da acceso total sin depender de `company_role_id` (ver `CurrentCompanyPermissionResolver::has()`). Para el resto de usuarios, la autorizacion efectiva se resuelve por `company_role_id`. La columna `role_template_id` existio en una fase anterior y se elimino (migracion `2026_08_13_100100_drop_role_templates`).

#### `branches`

- Proposito: sedes fisicas de una empresa.
- Campos clave: `id`, `company_id`, `name`, `code`, `is_primary`, `status`.

#### `warehouses`

- Proposito: ubicacion logica de stock.
- Campos clave: `id`, `company_id`, `branch_id`, `name`, `code`, `is_primary`, `status`.

#### `cash_registers`

- Proposito: punto de caja por sucursal.
- Campos clave: `id`, `company_id`, `branch_id`, `name`, `code`, `is_primary`, `status`.

### Planes y monetizacion

#### `plans`

- Proposito: catalogo de planes.
- Campos clave: `id`, `code`, `name`, `status`, `billing_period`, `base_price`.

#### `modules`

- Proposito: modulos habilitables por plan.
- Campos clave: `id`, `code`, `name`, `status`.

#### `features`

- Proposito: capacidades mas finas que un modulo.
- Campos clave: `id`, `module_id`, `code`, `name`, `status`.

#### `plan_modules`

- Proposito: modulos incluidos por plan.
- Campos clave: `plan_id`, `module_id`, `enabled`.

#### `plan_features`

- Proposito: features incluidas por plan.
- Campos clave: `plan_id`, `feature_id`, `enabled`.

#### `plan_limits`

- Proposito: limites numericos por plan.
- Campos clave: `plan_id`, `limit_key`, `limit_value`.
- Nota de implementacion actual: `plans`, `plan_modules`, `plan_features` y `plan_limits` ya son editables desde `Plataforma > Planes`; `PlanCatalogBootstrapper::ensureDefaults()` usa `insertOrIgnore` sobre estas tablas (solo siembra filas ausentes) para no revertir ediciones manuales cuando corre en trafico normal (creacion de empresa, cambio de suscripcion).

#### `subscriptions`

- Proposito: suscripcion activa por empresa o bundle.
- Campos clave: `id`, `company_id` nullable, `bundle_id` nullable, `plan_id`, `status`, `starts_at`, `ends_at`, `trial_ends_at`.
- Nota de implementacion actual: el comando diario `subscriptions:process-due` cierra (`status = ended`) toda suscripcion directa (`bundle_id` null) con `status` en `active`/`trialing` cuyo `ends_at` ya paso, y crea una nueva suscripcion del mismo plan cuando `companies.auto_renew` esta activo.

#### `subscription_bundles`

- Proposito: contrato multiempresa.
- Campos clave: `id`, `owner_user_id`, `name`, `status`, `max_companies`, `discount_type`, `discount_value`.

#### `subscription_bundle_companies`

- Proposito: asignacion de empresas dentro del bundle.
- Campos clave: `bundle_id`, `company_id`, `plan_id`.

#### `company_module_overrides`

- Proposito: override puntual de modulo por empresa.
- Campos clave: `company_id`, `module_id`, `enabled`, `starts_at`, `ends_at`.

#### `company_feature_overrides`

- Proposito: override puntual de feature por empresa.
- Campos clave: `company_id`, `feature_id`, `enabled`, `starts_at`, `ends_at`.

#### `company_limit_overrides`

- Proposito: override puntual de limite por empresa.
- Campos clave: `company_id`, `limit_key`, `limit_value`, `starts_at`, `ends_at`.

#### `coupons`

- Proposito: descuentos promocionales sobre suscripciones.
- Campos clave: `id`, `code`, `discount_type`, `discount_value`, `starts_at`, `expires_at`, `total_uses_limit`, `per_user_limit`, `per_company_limit`, `status`.

#### `coupon_plans`

- Proposito: alcance de cupon por plan.
- Campos clave: `coupon_id`, `plan_id`.

#### `coupon_bundles`

- Proposito: alcance de cupon por bundle.
- Campos clave: `coupon_id`, `bundle_id`.

#### `coupon_redemptions`

- Proposito: snapshot de uso del cupon.
- Campos clave: `coupon_id`, `subscription_id`, `company_id`, `user_id`, `applied_amount`, `applied_snapshot`.

### Roles y permisos

#### `permissions`

- Proposito: permisos tecnicos.
- Campos clave: `id`, `code`, `name`, `module_code`, `status`.
- Unique: `code`.

#### `company_roles`

- Proposito: unico mecanismo de rol; cada empresa arma sus propios roles desde cero, sin catalogo global de plantillas compartido entre empresas.
- Campos clave: `id`, `company_id`, `code`, `display_name`, `status`.
- Unique: `company_id + code`.

#### `company_role_permissions`

- Proposito: permisos efectivos de un rol empresarial.
- Campos clave: `company_role_id`, `permission_id`.
- Unique: `company_role_id + permission_id`.

### Maestras y productos

#### `categories`

- Proposito: clasificacion de productos.
- Campos clave: `id`, `company_id`, `name`, `code`, `status`, `deleted_at`.

#### `brands`

- Proposito: marcas de producto.
- Campos clave: `id`, `company_id`, `name`, `status`, `deleted_at`.

#### `units`

- Proposito: unidades base y de presentacion.
- Campos clave: `id`, `company_id`, `code`, `name`, `precision_scale`, `status`.

#### `products`

- Proposito: producto base.
- Campos clave: `id`, `company_id`, `category_id`, `brand_id`, `base_unit_id`, `tax_id`, `name`, `sku`, `barcode`, `description`, `cost`, `price_1`, `price_2`, `price_3`, `flexible_price`, `margin_1`, `margin_2`, `margin_3`, `tracks_inventory`, `minimum_stock`, `status`, `deleted_at`.
- Nota de implementacion inicial: `tax_id` queda como referencia nullable sin FK mientras no exista catalogo fiscal propio.
- `flexible_price` (boolean, default false): perecederos/granel con precio que cambia a diario (papa, yuca, frijol...). Cuando esta activo, `saveProduct()` (`ProductsPage`) fuerza `price_1/2/3` a 0/null y `tracks_inventory` a false en el servidor (no solo en la vista) — el precio se define en cada venta desde el POS (ver `flujo-pos.md`), y no tiene sentido llevar stock en kilos que nunca se pesan exactamente.

#### `product_presentations`

- Proposito: empaques con conversion a unidad base.
- Campos clave: `id`, `company_id`, `product_id`, `unit_id`, `name`, `barcode`, `conversion_factor`, `price_1`, `price_2`, `price_3`, `status`.
- Nota de implementacion inicial: el proyecto ya usa archivado logico para presentaciones mediante `deleted_at`.

#### `attributes`

- Proposito: ejes de variantes.
- Campos clave: `id`, `company_id`, `name`, `code`, `status`.
- Unique: `company_id + name`, `company_id + code`.
- Nota de implementacion inicial: usa `status` para activacion, desactivacion y archivado; no usa `deleted_at`.

#### `attribute_values`

- Proposito: valores de cada atributo.
- Campos clave: `id`, `attribute_id`, `value`, `status`.
- Unique: `attribute_id + value`.
- Nota de implementacion inicial: cada valor depende del atributo padre y no replica `company_id`; el alcance tenant se resuelve a traves de `attribute_id`.

#### `product_variants`

- Proposito: combinaciones vendibles de un producto.
- Campos clave: `id`, `company_id`, `product_id`, `sku`, `barcode`, `price_override`, `status`.
- Unique: `company_id + sku`, `company_id + barcode`.
- Nota de implementacion inicial: la unicidad de la combinacion de valores por producto se valida hoy en la capa de aplicacion.

#### `variant_attribute_values`

- Proposito: union entre variante y valores de atributos.
- Campos clave: `id`, `product_variant_id`, `attribute_value_id`, `created_at`, `updated_at`.
- Unique: `product_variant_id + attribute_value_id`.

### Inventario y compras

#### `purchase_orders` o `purchases`

- Proposito: compra recibida o documentada.
- Campos clave: `id`, `company_id`, `branch_id`, `warehouse_id`, `supplier_id`, `supplier_name`, `invoice_number`, `purchase_type`, `status`, `purchased_at`, `due_at`, `subtotal`, `tax_total`, `total`.
- Nota de implementacion actual: `supplier_id` ya puede enlazarse opcionalmente a `suppliers`, mientras `supplier_name` se conserva como snapshot historico legible de la compra.
- Nota de implementacion actual: `posted_to_inventory_at` marca si la compra ya fue aplicada a inventario, `returned_from_inventory_at` marca si esa compra ya fue revertida en stock y la base financiera ya usa `amount_paid`, `balance_due` y `paid_at` para cuentas por pagar por compra.

#### `purchase_items`

- Proposito: lineas de compra.
- Campos clave: `id`, `purchase_id`, `product_id`, `product_presentation_id`, `product_variant_id`, `quantity`, `base_quantity`, `unit_cost`, `tax_rate`, `line_subtotal`, `tax_amount`, `line_total`.
- Nota de implementacion inicial: `base_quantity` ya se calcula con `product_presentations` cuando la linea compra una presentacion en lugar de la unidad base.

#### `payable_movements`

- Proposito: ledger financiero de cuentas por pagar por compra.
- Campos clave: `id`, `company_id`, `supplier_id`, `purchase_id`, `movement_type`, `amount`, `balance_after`, `supplier_credit_after`, `reference`, `occurred_at`.
- Nota de implementacion actual: el backend ya registra `purchase_charge`, `payment`, `purchase_return_adjustment`, `supplier_credit_generated` y `supplier_credit_applied` para compras confirmadas, pagos a proveedor, devoluciones y aplicacion de saldo a favor.

#### `inventory_movements`

- Proposito: kardex canonico.
- Campos clave: `id`, `company_id`, `warehouse_id`, `product_id`, `product_variant_id`, `movement_type`, `reference_type`, `reference_id`, `quantity_in`, `quantity_out`, `unit_cost`, `balance_quantity`, `balance_cost`, `occurred_at`.
- Nota de implementacion actual: las compras confirmadas ya generan movimientos `purchase_in`, las devoluciones de compra generan `purchase_return_out`, las ventas confirmadas generan `sale_out` y las devoluciones de venta generan `sale_return_in`, referenciados por la linea operativa correspondiente.

#### `inventory_balances`

- Proposito: saldo actual por producto y bodega.
- Campos clave: `company_id`, `warehouse_id`, `product_id`, `product_variant_id`, `quantity_on_hand`, `average_cost`.
- Nota: es una vista materializable o tabla de saldo derivada; nunca fuente unica de verdad.
- Nota de implementacion actual: el proyecto usa una tabla real de saldos, protege unicidad por empresa, bodega, producto y variante, inicializa `average_cost` por promedio ponderado al postear compras y lo preserva en salidas de devolucion hasta que el saldo llegue a cero.

#### `inventory_adjustments`

- Proposito: cabecera de ajustes manuales de inventario por bodega.
- Campos clave: `id`, `company_id`, `branch_id`, `warehouse_id`, `adjustment_type`, `reason`, `notes`, `adjusted_at`, `posted_to_inventory_at`.
- Nota de implementacion actual: el proyecto ya soporta ajustes `increase` y `decrease`, posteados de forma idempotente al crear el documento.

#### `inventory_adjustment_items`

- Proposito: lineas de cada ajuste manual.
- Campos clave: `id`, `inventory_adjustment_id`, `product_id`, `product_variant_id`, `quantity`, `unit_cost`.
- Nota de implementacion actual: en entradas el `unit_cost` alimenta el promedio ponderado; en salidas el kardex usa el costo promedio vigente del saldo.

#### `inventory_transfers`

- Proposito: cabecera de traslados internos entre bodegas de la misma empresa.
- Campos clave: `id`, `company_id`, `source_warehouse_id`, `destination_warehouse_id`, `reason`, `notes`, `transferred_at`, `posted_to_inventory_at`.
- Nota de implementacion actual: el proyecto ya soporta traslado entre bodegas distintas, posteado automaticamente de forma idempotente.

#### `inventory_transfer_items`

- Proposito: lineas de cada traslado interno.
- Campos clave: `id`, `inventory_transfer_id`, `product_id`, `product_variant_id`, `quantity`.
- Nota de implementacion actual: el costo no se captura manualmente; el movimiento lleva el costo promedio vigente de la bodega origen y recalcula el promedio de la bodega destino.

### Ventas, POS y caja

#### `sales`

- Proposito: venta confirmada o en ciclo posterior.
- Campos clave: `id`, `company_id`, `branch_id`, `warehouse_id`, `cash_register_id`, `customer_id`, `credit_account_id`, `user_id`, `sale_type`, `status`, `pricing_snapshot`, `subtotal`, `discount_total`, `tax_total`, `grand_total`, `sold_at`, `credit_due_at`, `posted_to_inventory_at`, `cancelled_at`, `returned_at`, `replaces_sale_id`.
- Nota de implementacion actual: el backend ya soporta ventas `draft`, `confirmed`, `cancelled`, `partially_returned` y `returned`; usa `posted_to_inventory_at` para la salida de stock, `credit_account_id` y `credit_due_at` para ventas a credito, `cancelled_at` para anulacion, `returned_at` para devolucion total o parcial y `pricing_snapshot` para resumir promociones aplicadas cuando existan.
- `replaces_sale_id` (nullable, autorreferencia a `sales`, mismo patron que `frozen_sales.converted_sale_id`) enlaza una venta nueva con la venta POS confirmada que reemplaza cuando el usuario usa "Modificar" en `Ventas`: `App\Actions\Sales\ModifySale` anula la venta original (`CancelSale`) y crea la venta nueva (`CreatePosSale`) en una sola transaccion, dejando este campo como rastro de que una reemplaza a la otra.

#### `sale_items`

- Proposito: lineas de venta con snapshot historico.
- Campos clave: `id`, `sale_id`, `product_id`, `product_presentation_id`, `product_variant_id`, `description_snapshot`, `promotion_snapshot`, `quantity`, `base_quantity`, `returned_quantity`, `returned_base_quantity`, `unit_price`, `discount_amount`, `tax_amount`, `line_total`, `cost_snapshot`.
- Nota de implementacion actual: `base_quantity` se calcula desde `product_presentations`, `description_snapshot` se congela al crear la venta, `promotion_snapshot` registra promociones o combos aplicados por linea, `cost_snapshot` se completa al confirmar la salida de inventario y las cantidades devueltas se acumulan por linea para controlar devoluciones parciales.

#### `frozen_sales`

- Proposito: carrito guardado sin impacto en inventario.
- Campos clave: `id`, `company_id`, `branch_id`, `warehouse_id`, `cash_register_id`, `customer_id`, `created_by`, `converted_sale_id`, `label`, `status`, `expires_at`, `payload_snapshot`.
- Nota de implementacion actual: el backend ya soporta creacion, cancelacion y conversion de `frozen_sales`; `payload_snapshot` congela lineas y totales, y `converted_sale_id` enlaza la venta real resultante.

#### `payments`

- Proposito: pagos asociados a venta, credito o caja.
- Campos clave: `id`, `company_id`, `sale_id` nullable, `credit_account_id` nullable, `cash_session_id` nullable, `payment_method_code`, `status`, `amount`, `reference`, `paid_at`, `received_by`.
- Nota de implementacion actual: el backend ya soporta pagos de venta con uno o varios metodos y tambien abonos sobre ventas a credito; en esta fase `payment_method_code` se maneja como string tecnico temporal mientras no exista un catalogo formal de medios de pago, y una anulacion de venta puede revertir pagos `confirmed` a `reversed`.

#### `cash_sessions`

- Proposito: apertura y cierre de caja.
- Campos clave: `id`, `company_id`, `company_sequence`, `branch_id`, `cash_register_id`, `opened_by`, `closed_by`, `status`, `opening_amount`, `closing_expected_amount`, `closing_counted_amount`, `difference_amount`, `opened_at`, `closed_at`.
- Nota de implementacion actual: el backend ya soporta apertura de caja, cierre con validacion de diferencia y calculo del efectivo esperado a partir de pagos `cash`.
- `company_sequence` (unico por `company_id`, calculado en `OpenCashSession::handle()` como `max(company_sequence) + 1` para la empresa) es el numero que ve el usuario ("Sesion #N"); `id` es la PK global compartida entre todas las empresas y nunca se muestra en UI, para evitar que la primera sesion de una empresa nueva se vea con un numero grande solo porque otras empresas ya crearon sesiones antes.

### Clientes, credito y fidelizacion

#### `people`

- Proposito: identidad comercial global de personas.
- Campos clave: `id`, `document_type`, `document_number`, `first_name`, `last_name`, `phone`, `email`.
- Nota de implementacion actual: el backend ya soporta alta de personas comerciales como base de clientes y proveedores.
- `Person::full_name` es un accessor (`trim(first_name.' '.last_name)`) — unica fuente de verdad para mostrar el nombre de una persona; todo el codigo (POS, Credito) debe usarlo en vez de concatenar `first_name`/`last_name` a mano.
- `document_number` es nullable y solo se llena cuando el dato realmente parece un documento. `PosPage::resolveOrCreateCustomer()` (alta rapida de cliente desde el campo unico del POS) solo guarda el texto escrito como `document_number` si son puros digitos (`preg_match('/^\d+$/', ...)`); si es un nombre o apodo, se guarda unicamente en `first_name` y `document_number` queda `null`, para no contaminar el campo de documento con datos que no lo son.

#### `customers`

- Proposito: relacion comercial por empresa.
- Campos clave: `id`, `company_id`, `person_id`, `status`, `credit_enabled`, `loyalty_enabled`.
- Nota de implementacion actual: el backend ya soporta clientes por empresa con banderas para credito y fidelizacion.

#### `suppliers`

- Proposito: relacion comercial de proveedor por empresa.
- Campos clave: `id`, `company_id`, `person_id`, `status`, `credit_balance`, `payment_term_days`, `notes`.
- Nota de implementacion actual: el backend ya soporta proveedores por empresa reutilizando `people`; una compra puede enlazarse al proveedor, heredar `due_at` desde su plazo y acumular saldo a favor cuando se devuelve una compra ya pagada.

#### `credit_accounts`

- Proposito: cartera por cliente y empresa.
- Campos clave: `id`, `company_id`, `customer_id`, `credit_limit`, `available_credit`, `balance_due`, `status`.
- Nota de implementacion actual: el backend ya soporta una cuenta por cliente y empresa con control de cupo disponible y saldo pendiente.

#### `credit_installments` o `credit_movements`

- Proposito: movimientos de cartera.
- Campos clave: `id`, `credit_account_id`, `sale_id`, `movement_type`, `amount`, `balance_after`, `occurred_at`.
- Nota de implementacion actual: el backend ya registra cargos por venta, abonos y ajustes por devolucion o anulacion contra la misma venta a credito.

#### `loyalty_accounts`

- Proposito: puntos por cliente y empresa.
- Campos clave: `id`, `company_id`, `customer_id`, `points_balance`, `status`.
- Nota de implementacion actual: el backend ya soporta una cuenta de fidelizacion por cliente y empresa cuando el cliente tiene fidelizacion habilitada.

#### `loyalty_movements`

- Proposito: acumulacion, redencion o reverso.
- Campos clave: `id`, `company_id`, `loyalty_account_id`, `sale_id`, `movement_type`, `points`, `cash_equivalent`, `balance_after`, `occurred_at`.
- Nota de implementacion actual: el backend ya registra `earn`, `redeem`, restauraciones de redencion por devolucion o anulacion, reversos de puntos ganados y expiraciones FIFO; `cash_equivalent` conserva el valor monetario aplicado cuando el movimiento nace de una venta.

#### `promotions`

- Proposito: cabecera de promociones comerciales por empresa.
- Campos clave: `id`, `company_id`, `name`, `promotion_type`, `status`, `discount_type`, `discount_value`, `priority`, `starts_at`, `ends_at`.
- Nota de implementacion actual: el backend ya soporta promociones `product_discount` y `combo_price`, activas por rango de fechas, ordenadas por `priority`.

#### `promotion_targets`

- Proposito: alcance de una promocion por producto, categoria o variante.
- Campos clave: `id`, `promotion_id`, `target_type`, `target_id`, `min_quantity`.
- Nota de implementacion actual: el backend ya soporta `target_type` `product`, `category` y `variant` para promociones de descuento por producto.

#### `promotion_combo_items`

- Proposito: composicion requerida de un combo a precio fijo.
- Campos clave: `id`, `promotion_id`, `product_id`, `product_variant_id`, `required_quantity`.
- Nota de implementacion actual: el backend ya soporta combos por producto o variante, distribuyendo el descuento proporcionalmente entre las lineas consumidas.

### Configuracion y auditoria

#### `company_settings`

- Proposito: configuracion tipada por empresa.
- Campos clave: `id`, `company_id`, `group_key`, `setting_key`, `value_type`, `value_string`, `value_integer`, `value_decimal`, `value_boolean`, `value_json`.
- Unique: `company_id + group_key + setting_key`.
- Nota de implementacion inicial: el backend ya resuelve defaults desde un catalogo centralizado antes de que exista un valor persistido para la empresa.
- Nota de implementacion actual: `pos.allow_promotion_stacking` ya afecta el motor de promociones al decidir si una unidad consumida por combo puede volver a recibir promociones por producto.

#### `audit_logs`

- Proposito: trazabilidad de cambios relevantes.
- Campos clave: `id`, `company_id` nullable, `actor_user_id`, `action`, `auditable_type`, `auditable_id`, `before_snapshot`, `after_snapshot`, `ip_address`, `created_at`.
- Nota de implementacion actual: el backend ya registra eventos criticos como creacion de compras, ajustes, traslados, ventas, pagos, apertura/cierre de caja, devoluciones, anulaciones, abonos de credito y promociones.

## Relaciones criticas

- `companies` 1:N `branches`, `warehouses`, `cash_registers`, `company_roles`, `products`, `sales`, `promotions`.
- `companies` 1:N `purchases`, `suppliers` y `payable_movements`.
- `users` N:M `companies` via `company_user`.
- `plans` N:M `modules` y `features`.
- `products` 1:N `product_presentations` y `product_variants`.
- `sales` 1:N `sale_items` y 1:N `payments`.
- `purchases` 1:N `purchase_items` y 1:N `payable_movements`.
- `people` 1:N `customers` y 1:N `suppliers`.
- `customers` 1:1 `credit_accounts`.
- `customers` 1:1 `loyalty_accounts`.
- `credit_accounts` 1:N `credit_movements` y 1:N `payments`.
- `loyalty_accounts` 1:N `loyalty_movements`.
- `frozen_sales` puede convertirse 1:1 en `sales`.
- `sales` puede autorreferenciarse 1:1 via `replaces_sale_id` cuando una venta reemplaza a otra ("Modificar").
- `warehouses` 1:N `inventory_movements`.
- `promotions` 1:N `promotion_targets` y 1:N `promotion_combo_items`.

## Migraciones por prioridad

1. Core SaaS y tenancy.
2. Planes, permisos y configuracion.
3. Maestras y productos.
4. Inventario y compras.
5. POS, ventas y caja.
6. Credito, fidelizacion y promociones.
7. Importaciones, reportes e integraciones.
