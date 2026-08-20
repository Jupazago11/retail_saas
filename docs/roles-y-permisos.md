# Roles y Permisos

## Principios

- La autorizacion depende de permisos tecnicos, no del nombre visible del rol.
- `platform_super_admin` administra catalogos, planes, permisos y auditoria global.
- El owner y administradores empresariales solo delegan permisos dentro de su alcance.
- El acceso autenticado no implica contexto operativo completo: cada usuario debe tener una empresa activa seleccionada para usar el backoffice.

## Roles globales iniciales

- `platform_super_admin`
- `support_admin`
- `billing_admin`

## Permisos tecnicos iniciales

- `masters.view`
- `masters.create`
- `masters.update`
- `masters.archive`
- `masters.restore`
- `products.view`
- `products.create`
- `products.update`
- `products.archive`
- `products.restore`
- `inventory.view`
- `inventory.adjust`
- `inventory.transfer`
- `purchases.view`
- `purchases.create`
- `suppliers.view`
- `suppliers.manage`
- `payables.view`
- `payables.manage`
- `customers.view`
- `customers.manage`
- `sales.view`
- `sales.create`
- `sales.freeze`
- `sales.cancel`
- `sales.return`
- `sales.apply_discount`
- `sales.select_alternative_price`
- `cash.open`
- `cash.close`
- `cash.view_difference`
- `credit.view`
- `credit.manage`
- `loyalty.manage`
- `promotions.manage`
- `reports.view`
- `reports.view_costs`
- `settings.manage`
- `users.manage`
- `roles.manage`
- `subscriptions.view`

## Reglas de modelado

- `permissions` es un catalogo global.
- `company_roles` es el unico mecanismo de rol: cada empresa arma sus propios roles personalizados desde cero, sin catalogo global de plantillas compartido entre empresas (el sistema de `role_templates` que existio en una fase anterior se elimino — ver migracion `2026_08_13_100100_drop_role_templates`; los usuarios que tenian una plantilla asignada se migraron a un `company_role` equivalente en su empresa via `2026_08_13_100000_backfill_company_roles_from_role_templates`).
- `company_user` conserva `company_role` como compatibilidad semantica (el valor `'owner'` es especial: da acceso total sin depender de ningun `company_role_id`, ver `CurrentCompanyPermissionResolver::has()`) y agrega `company_role_id` para la autorizacion efectiva de todos los demas usuarios.
- La UI muestra nombres visibles en espanol, pero las claves tecnicas quedan en ingles.

## Notas de la fase actual

- El login ya opera con `username`; `email` se conserva para verificacion, recuperacion y contacto.
- El primer rol efectivo al crear empresa es `owner` (`company_user.company_role = 'owner'`); no necesita ningun `company_role_id` porque su acceso total esta resuelto directamente por esa columna.
- La capa de permisos ya esta materializada con tablas, bootstrapper inicial, middleware `company.permission` y policies para el catalogo.
- Las rutas `masters/*` exigen `masters.view`; las rutas `products/*` exigen `products.view`.
- Las acciones Livewire del catalogo validan `create`, `update`, `archive` y `restore` por policy, no solo por visibilidad de ruta.
- Mientras no se separen permisos mas finos, el modulo de catalogo extendido (`products`, `product_presentations`, `attributes`, `product_variants`) queda conceptualmente bajo `products.*`.
- El backend de promociones y combos ya reserva `promotions.manage` para administrar reglas comerciales por empresa.
- El modulo operativo de proveedores usa `suppliers.view` para consulta y `suppliers.manage` para crear, editar y activar o desactivar proveedores.
- El backend de cuentas por pagar ya reserva `payables.view` y `payables.manage` para consulta y gestion financiera de compras.
- El maestro general de clientes (`Clientes`, fuera de Credito/Fidelizacion) usa `customers.view` para consulta y `customers.manage` para crear, editar y activar o desactivar clientes; el cupo de credito y los puntos de fidelizacion se siguen administrando desde `credit.manage`/`loyalty.manage`, no desde este modulo.
- La UI principal ya oculta el acceso a catalogos cuando el usuario no tiene permisos para ver maestras ni productos.
- La UI principal ya expone la seccion `Compras` solo cuando el usuario puede ver proveedores o cuentas por pagar.
- La UI principal ya expone la seccion `Clientes` solo cuando el usuario tiene `customers.view`.
- `Admin > Roles` filtra el checklist de permisos por el plan vigente de la empresa (`RolesPage::PLAN_GATED_MODULE_CODES`): solo se ofrecen para asignar los `module_code` cuyo modulo de plan correspondiente esta habilitado (via `CompanyPlanResolver::hasModule()`). `sales` se gatea contra el modulo de plan `pos` (no existe un modulo llamado `sales` en `PlanCatalog`); los `module_code` administrativos (`masters`, `suppliers`, `payables`, `customers`, `settings`, `users`, `roles`, `subscriptions`) no tienen modulo de plan asociado y siempre se muestran. Si un rol ya tenia un permiso de un modulo que el plan deja de incluir, el permiso se conserva en base de datos; solo se oculta del formulario.
