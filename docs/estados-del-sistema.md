# Estados del Sistema

## Convencion

Usar enums o value objects. No dispersar strings arbitrarios.

## Estados base

### CompanyStatus

- `active`
- `inactive`
- `suspended`
- `archived`

### UserStatus

- `active`
- `inactive`
- `blocked`

### ProductStatus

- `active`
- `inactive`
- `archived`

### PurchaseStatus

- `draft`
- `confirmed`
- `partially_paid`
- `paid`
- `cancelled`
- `returned`

### SaleStatus

- `draft`
- `confirmed`
- `cancelled`
- `partially_returned`
- `returned`

### FrozenSaleStatus

- `open`
- `expired`
- `converted`
- `cancelled`

### PaymentStatus

- `pending`
- `confirmed`
- `rejected`
- `reversed`

### CreditAccountStatus

- `active`
- `blocked`
- `closed`

### CreditMovementType

- `sale_charge`
- `payment`
- `sale_return_adjustment`
- `sale_cancellation_adjustment`

### LoyaltyAccountStatus

- `active`
- `blocked`
- `closed`

### PromotionStatus

- `active`
- `inactive`
- `archived`

### LoyaltyMovementType

- `earn`
- `sale_return_reversal`
- `sale_cancellation_reversal`

### CashSessionStatus

- `open`
- `closed`
- `reconciled`

### InventoryMovementType

- `opening_balance`
- `purchase_in`
- `purchase_return_out`
- `sale_out`
- `sale_return_in`
- `adjustment_in`
- `adjustment_out`
- `transfer_in`
- `transfer_out`

### InventoryAdjustmentType

- `increase`
- `decrease`

### SubscriptionStatus

- `pending`
- `trialing`
- `active`
- `ended`

Nota de implementacion actual: no existe un enum `SubscriptionStatus`; el campo `subscriptions.status` es un string libre validado ad hoc por cada Action (`ChangeCompanySubscription`, `EndCompanySubscription`, `RenewCompanySubscription`, `ProvisionCompanySubscription`). Los valores `past_due`, `suspended`, `cancelled` y `expired` de una version previa de este documento no se implementaron; se retiran de aqui porque no reflejaban el codigo real.

"Vencida" no es un valor de `status`: es un estado derivado cuando `status` sigue en `active`/`trialing` pero `ends_at` ya paso. Se resuelve con `Subscription::isPastDue()` para la UI y con el scope `Subscription::scopeActiveAt()` para el calculo de plan efectivo (`CompanyPlanResolver`), que ya ignora una suscripcion vencida aunque su `status` no se haya actualizado todavia.

### ElectronicBillingStatus

- `pending`
- `submitted`
- `accepted`
- `rejected`
- `retrying`

## Transiciones clave

- Una venta confirmada no vuelve a `draft`.
- Una venta `confirmed` puede pasar a `partially_returned`, `returned` o `cancelled`.
- Una venta `partially_returned` puede terminar en `returned`, pero ya no debe anularse.
- Una venta cancelada se compensa; no se elimina.
- "Modificar" una venta POS confirmada no es un estado nuevo: es la composicion, en una sola transaccion, de la transicion `confirmed → cancelled` de la venta original mas una transicion normal `→ confirmed` de una venta nueva, enlazada por `replaces_sale_id`.
- Un pago `confirmed` puede pasar a `reversed` cuando la venta asociada se anula.
- Una venta a credito confirmada genera un `sale_charge` en cartera al momento de confirmar.
- Un abono confirmado reduce el saldo de la venta y de la cuenta de credito asociada.
- Una venta confirmada con cliente fidelizado puede generar un `earn` en puntos.
- Una devolucion o anulacion de esa venta revierte puntos mediante movimientos de fidelizacion, no tocando pagos.
- Una venta congelada pasa a `converted` cuando se confirma la venta real.
- Una suscripcion vencida deja el tenant en modo lectura, no borra datos.
- El comando programado diario `subscriptions:process-due` recorre las suscripciones directas con `ends_at` ya vencido: si la empresa tiene `auto_renew` activo, cierra la suscripcion vencida (`status = ended`) y crea una nueva del mismo plan a partir de ese momento; si no, solo la cierra y queda pendiente de activacion manual desde Plataforma.
