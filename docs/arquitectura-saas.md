# Arquitectura SaaS

## Resumen

El sistema sera un monolito modular en Laravel con Livewire para interaccion rica y PostgreSQL como base de datos unica. El aislamiento tenant se resuelve mediante `company_id`, contexto activo y validaciones de autorizacion en todos los flujos operativos.

## Objetivos de arquitectura

- Separar dominio retail de verticales no objetivo.
- Mantener una sola base de codigo y una sola base de datos.
- Permitir varias empresas por usuario y varios usuarios por empresa.
- Soportar crecimiento funcional sin introducir microservicios temprano.
- Mantener compatibilidad local con Docker y despliegue productivo en Railway.

## Capas

### Presentacion

- Blade para layout y navegacion principal.
- Livewire para tablas, formularios, filtros, modales y drawers.
- Alpine para estado visual ligero.
- Tailwind para UI.

### Aplicacion

- `Actions/` para casos de uso concretos.
- `Services/` para reglas de dominio reutilizables.
- `Queries/` para consultas complejas que requieran composicion explicita.
- `Requests/` para validacion HTTP clasica cuando aplique.

### Dominio

- `Models/` con relaciones y scopes contenidos.
- `Enums/` para estados del sistema.
- `Policies/` para autorizacion por recurso.
- Eventos y jobs cuando el flujo pueda ser asincrono.

### Infraestructura

- PostgreSQL.
- Queue `database` en primeras fases.
- Mailpit en local.
- Almacenamiento compatible con S3 para activos persistentes en produccion.

## Multi-tenancy

### Contextos requeridos

- `CurrentCompany`
- `CurrentBranch`
- `CurrentWarehouse`
- `CurrentCashRegister`

### Reglas

- Toda entidad operativa pertenece a una `company`.
- `platform_super_admin` puede cruzar empresas.
- Usuarios empresariales solo operan dentro de empresas vinculadas.
- La empresa activa se cambia de forma explicita y auditable.
- La autorizacion operativa se resuelve contra la empresa activa mediante middleware y policies.
- El owner de la empresa conserva acceso total; los demas usuarios dependen de permisos tecnicos asociados a su rol empresarial (`company_roles`, personalizado por tenant — no existe un catalogo global de plantillas).

## Modulos esperados

- Platform
- Company
- Masters
- Products
- Purchases
- Inventory
- Pos
- Sales
- Cash
- Credit
- Loyalty
- Reports
- Settings

## Estructura propuesta

```text
app/
|-- Actions/
|-- Enums/
|-- Events/
|-- Exceptions/
|-- Http/
|   |-- Controllers/
|   |-- Middleware/
|   `-- Requests/
|-- Jobs/
|-- Livewire/
|   |-- Platform/
|   |-- Company/
|   |-- Masters/
|   |-- Products/
|   |-- Purchases/
|   |-- Inventory/
|   |-- Pos/
|   |-- Sales/
|   |-- Cash/
|   |-- Credit/
|   |-- Loyalty/
|   |-- Reports/
|   `-- Settings/
|-- Models/
|-- Notifications/
|-- Policies/
|-- Queries/
|-- Services/
|   |-- Tenancy/
|   |-- Plans/
|   |-- Products/
|   |-- Purchases/
|   |-- Inventory/
|   |-- Sales/
|   |-- Cash/
|   |-- Credit/
|   |-- Loyalty/
|   |-- Billing/
|   `-- Auditing/
`-- Support/
```

## Riesgos iniciales

- Complejidad alta del dominio si se intenta construir todo a la vez.
- Riesgo de fugas tenant si no se centralizan scopes y policies.
- Riesgo de sobreingenieria si se modelan integraciones futuras antes del nucleo.
- Riesgo de colision Docker si se reciclan puertos o nombres de otros proyectos.

## Principios de implementacion

- Diseñar antes de migrar.
- Priorizar dominios nucleares sobre integraciones futuras.
- Mantener reglas de negocio fuera de componentes visuales.
- Escribir pruebas tenant y transaccionales desde fases tempranas.
