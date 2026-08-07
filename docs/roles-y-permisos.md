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

## Plantillas empresariales sugeridas

- `owner`
- `company_admin`
- `cashier`
- `seller`
- `purchasing_manager`
- `inventory_manager`
- `accounting_assistant`

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
- `role_templates` define paquetes iniciales mantenidos por plataforma.
- `company_roles` permite personalizacion por tenant.
- `company_user` conserva `company_role` como compatibilidad semantica y agrega `role_template_id` y `company_role_id` para la autorizacion efectiva.
- La UI muestra nombres visibles en espanol, pero las claves tecnicas quedan en ingles.

## Notas de la fase actual

- El login ya opera con `username`; `email` se conserva para verificacion, recuperacion y contacto.
- El primer rol efectivo al crear empresa es `owner`, enlazado en `company_user` y respaldado por la plantilla `owner`.
- La capa de permisos ya esta materializada con tablas, bootstrapper inicial, middleware `company.permission` y policies para el catalogo.
- Las rutas `masters/*` exigen `masters.view`; las rutas `products/*` exigen `products.view`.
- Las acciones Livewire del catalogo validan `create`, `update`, `archive` y `restore` por policy, no solo por visibilidad de ruta.
- Mientras no se separen permisos mas finos, el modulo de catalogo extendido (`products`, `product_presentations`, `attributes`, `product_variants`) queda conceptualmente bajo `products.*`.
- El backend de promociones y combos ya reserva `promotions.manage` para administrar reglas comerciales por empresa.
- El modulo operativo de proveedores usa `suppliers.view` para consulta y `suppliers.manage` para crear, editar y activar o desactivar proveedores.
- El backend de cuentas por pagar ya reserva `payables.view` y `payables.manage` para consulta y gestion financiera de compras.
- La UI principal ya oculta el acceso a catalogos cuando el usuario no tiene permisos para ver maestras ni productos.
- La UI principal ya expone la seccion `Compras` solo cuando el usuario puede ver proveedores o cuentas por pagar.
- `Admin > Roles` filtra el checklist de permisos por el plan vigente de la empresa (`RolesPage::PLAN_GATED_MODULE_CODES`): solo se ofrecen para asignar los `module_code` cuyo modulo de plan correspondiente esta habilitado (via `CompanyPlanResolver::hasModule()`). `sales` se gatea contra el modulo de plan `pos` (no existe un modulo llamado `sales` en `PlanCatalog`); los `module_code` administrativos (`masters`, `suppliers`, `payables`, `settings`, `users`, `roles`, `subscriptions`) no tienen modulo de plan asociado y siempre se muestran. Si un rol ya tenia un permiso de un modulo que el plan deja de incluir, el permiso se conserva en base de datos; solo se oculta del formulario.
