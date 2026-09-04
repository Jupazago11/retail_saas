# Matriz de Planes, Modulos y Limites

## Enfoque

Los nombres `Basic`, `Pro` y `Premium` son solo empaques comerciales. La aplicacion debe resolver acceso efectivo por modulos, features, limites y overrides.

## Estado actual de implementacion

Las tablas de este documento son el **seed inicial** (`app/Support/Plans/PlanCatalog.php`), usado solo para poblar filas que todavia no existen. Una vez que una empresa o el superadmin interactua con el catalogo, la base de datos manda: los valores reales y vigentes de modulos, features y limites por plan se administran desde `Plataforma > Planes`, y `PlanCatalogBootstrapper::ensureDefaults()` ya no los sobreescribe.

Desde que `plans`/`modules` tienen `business_type_id` (ver `docs/decisiones-tecnicas.md`, "Planes independientes por vertical de negocio"), este documento describe el vertical **`general`** (retail). El vertical `restaurant` tiene su propio catalogo — ver "Vertical restaurante" mas abajo.

`reports.profitability` ya esta conectada a un consumidor real: gobierna si `Reportes` muestra la tarjeta "Margen bruto" (ademas del permiso `reports.view_costs` que ya existia). Los modulos `credit`, `loyalty` y `promotions` tambien gobiernan que tarjetas y tablas de `Reportes` ve cada empresa.

## Modulos propuestos

| Modulo | Basic | Pro | Premium |
| --- | --- | --- | --- |
| products | Si | Si | Si |
| inventory | Si | Si | Si |
| purchases | Si | Si | Si |
| pos | Si | Si | Si |
| cash | Si | Si | Si |
| credit | No | Si | Si |
| loyalty | No | Si | Si |
| promotions | No | Si | Si |
| reports | Basico | Medio | Avanzado |
| imports | No | Si | Si |
| electronic_billing | No | Opcional | Si |

## Features iniciales

| Feature | Basic | Pro | Premium |
| --- | --- | --- | --- |
| products.multiple_prices | No | Si | Si |
| products.variants | No | Si | Si |
| products.presentations | Si | Si | Si |
| products.weighable | No | Si | Si |
| inventory.negative_stock | No | Opcional | Opcional |
| inventory.multiple_warehouses | No | Si | Si |
| pos.frozen_sales | No | Si | Si |
| pos.mixed_payments | Si | Si | Si |
| pos.manual_discounts | No | Si | Si |
| pos.combos | No | No | Si |
| cash.opening_closing | Si | Si | Si |
| credit.enabled | No | Si | Si |
| loyalty.enabled | No | Si | Si |
| reports.profitability | No | Si | Si |
| imports.excel | No | Si | Si |

## Inputs operativos por plan

### POS Basic

| Input / control | Basic | Nota |
| --- | --- | --- |
| `producto_lookup` texto | Si | Busca por codigo de barras, SKU o nombre |
| Sugerencias de producto | Si | Click manual o `Enter` sobre la primera opcion |
| Cantidad | Si | Ajuste en linea |
| Tarifa `V1/V2/V3` | Si | Solo cambia si el producto tiene multiples precios |
| Pagos | Si | Cobro inmediato |
| Cliente | No | Se reserva para plan superior o activacion posterior |
| Sucursal visible | No | Se toma del contexto/configuracion activa |
| Bodega visible | No | Se toma del contexto/configuracion activa |
| Caja visible | No | Se toma del contexto/configuracion activa |
| Fecha editable | No | Se asigna automaticamente al facturar |

### POS Pro / Premium

| Input / control | Pro | Premium |
| --- | --- | --- |
| Cliente visible | Si | Si |
| Sucursal visible | Si | Si |
| Bodega visible | Si | Si |
| Caja visible | Si | Si |
| Fecha editable | Opcional | Opcional |

## Limites iniciales sugeridos

| Limite | Basic | Pro | Premium |
| --- | --- | --- | --- |
| max_users | 3 | 10 | 50 |
| max_companies | 1 | 1 | 3 |
| max_branches | 1 | 3 | 10 |
| max_warehouses | 1 | 5 | 20 |
| max_cash_registers | 1 | 5 | 20 |
| max_products | 1500 | 10000 | 50000 |
| max_monthly_sales | 3000 | 20000 | 100000 |
| max_electronic_documents | 0 | 1000 | 10000 |

## Bundle multiempresa

- Un bundle agrupa varias empresas bajo un mismo propietario.
- Cada empresa del bundle puede tener un plan distinto.
- El bundle puede aportar descuento, modulos extra y limites custom.
- Los overrides por empresa se aplican despues del bundle.

## Vertical restaurante

Mismo mecanismo de `plans`/`modules`/`plan_limits`, filas separadas con `business_type_id = restaurant`. Reutiliza los modulos compartidos (`products`, `purchases`, `cash`, `credit`, `loyalty`, `promotions`, `reports`, `imports`, `electronic_billing`) y agrega dos modulos exclusivos: `dining` (mesas y comandas, reemplaza a `pos`) y `kitchen` (cocina/KDS). Los limites iniciales son identicos a los del vertical `general` (mismo numero por tier) — ver `PlanCatalog::plans()` para el detalle exacto y ajustarlos desde `Plataforma > Planes` cuando haya informacion real de mercado.

| Modulo | Basic | Pro | Premium |
| --- | --- | --- | --- |
| products | Si | Si | Si |
| inventory | No | Si | Si |
| purchases | No | Si | Si |
| dining | Si | Si | Si |
| kitchen | No | Si | Si |
| cash | Si | Si | Si |
| credit | No | Si | Si |
| loyalty | No | Si | Si |
| promotions | No | Si | Si |
| reports | Si | Si | Si |
| imports | No | Si | Si |
| electronic_billing | No | No | Si |

`dining` (mesas y comandas) y `kitchen` (cocina/KDS) ya tienen implementacion operativa — ver `docs/decisiones-tecnicas.md`, seccion "Mesas y Comandas, Cocina, e inventario por receta (vertical restaurante)".

## Formula de acceso efectivo

```text
active subscription
+ plan modules
+ plan features
+ plan limits
+ bundle adjustments
+ company overrides
+ company settings
+ user permissions
```
