# AGENTS.md

## Objetivo

Construir un SaaS multiempresa para comercio minorista: tiendas, minimercados, supermercados pequenos, San Andresito, ropa, calzado y accesorios. El producto cubre compras, inventario, POS, caja, credito, fidelizacion, reportes, suscripciones y administracion de plataforma.

## Alcance

- Dominio multi-vertical: cada empresa tiene un tipo de negocio (`business_types`) que determina que vertical opera. Verticales soportados: negocio general (retail: tiendas, minimercados, supermercados pequenos, San Andresito, ropa, calzado y accesorios) y restaurante.
- El tipo de negocio lo define una unica vez el `platform_super_admin` al activar la empresa en Plataforma > Empresas (editable despues por el superadmin si hace falta corregirlo); el dueño de la empresa nunca lo elige.
- Modulos por vertical: se documentan y se implementan por fases a medida que se definen. Algunos modulos existentes (compras, credito, reportes, configuracion) se reutilizan sin cambios entre verticales; los especificos de restaurante se disenan aparte.
- Excluido del MVP: farmacia, mecanica, hospital, estaciones de servicio y otros verticales no listados arriba.
- Arquitectura inicial: monolito modular en Laravel con una sola base de datos PostgreSQL y aislamiento por `company_id`.

## Stack base

- PHP 8.4
- Laravel 13
- Blade
- Livewire
- Alpine.js
- Tailwind CSS
- PostgreSQL 18 en local si la imagen esta disponible; PostgreSQL 17 o 18 en Railway segun soporte confirmado del entorno final
- Vite
- Docker Compose
- Pest
- Laravel Pint
- PHPStan/Larastan

## Restricciones Docker

- Nunca tocar `docker-compose.yml` de otros proyectos.
- Nunca ejecutar `docker compose down` fuera de esta carpeta.
- Nunca ejecutar `docker system prune`, `docker volume prune` ni limpiezas globales.
- Nunca reutilizar contenedores, redes, volumenes o bases de datos ajenos.
- Todos los comandos Docker deben ejecutarse desde esta raiz del proyecto.

## Estado del entorno inspeccionado el 2026-06-15

- Directorio padre inspeccionado: `/home/jupazago/Documentos/saas`
- Resultado: carpeta vacia, segura para crear un proyecto exclusivo.
- Contenedores activos: ninguno.
- Contenedores detenidos detectados: proyectos `mantec` e `inmobiliaria-saas`.
- Redes existentes detectadas: `mantec_sail`, `inmobiliaria-saas_sail`.
- Volumenes existentes detectados: `mantec_*`, `inmobiliaria-saas_*`.
- Puertos ocupados o historicamente usados por otros proyectos: `80`, `5173`, `5432`, `5433`, `6379`, `6380`, `7700`, `8081`, `1025`, `1026`, `8025`, `8026`.

## Nombres reservados para este proyecto

- `COMPOSE_PROJECT_NAME=retail_saas`
- Servicios Compose: `app`, `web`, `postgres`, `mailpit`, `queue`, `vite`
- `NETWORK_NAME=retail_saas_network`
- `POSTGRES_VOLUME=retail_saas_postgres_data`
- `POSTGRES_DB=retail_saas_db`

## Puertos locales propuestos

- `APP_PORT=8088`
- `VITE_PORT=5174`
- `DB_PORT=5434`
- `MAILPIT_SMTP_PORT=1027`
- `MAILPIT_UI_PORT=8027`

## Arquitectura

- Monolito modular.
- Una sola base de datos PostgreSQL.
- Aislamiento tenant por `company_id`.
- `platform_super_admin` como unico actor que puede cruzar empresas.
- Contextos explicitos: `CurrentCompany`, `CurrentBranch`, `CurrentWarehouse`, `CurrentCashRegister`.
- Reglas de negocio en servicios, acciones, policies y enums. No en Blade.

## Reglas multi-tenant

- Toda consulta operativa debe filtrar por `company_id`.
- No usar `find($id)` en flujos tenant sin scope o policy.
- Validar pertenencia usuario-empresa antes de operar.
- Validar que branch, warehouse, cash register y documentos pertenezcan a la empresa activa.
- Crear pruebas de acceso cruzado para cada modulo critico.

## Convenciones

- Documentacion y comentarios de negocio en espanol.
- Nombres tecnicos internos en ingles.
- Modelos singulares, tablas plurales.
- FK tipo `{model}_id`.
- Estados mediante enums o value objects.
- Dinero y cantidades con `decimal`, nunca `float`.
- Soft delete solo en entidades donde aplique archivado.
- Indices por `company_id` y compuestos segun consultas reales.

## UX sin recarga

- Modulos principales pueden ser pantallas separadas.
- Dentro de cada modulo, CRUD y filtros con Livewire.
- Crear y editar comparten formulario.
- Modal para formularios pequenos y drawer para medianos o grandes.
- Paginacion, busqueda, filtros y ordenamiento del lado servidor.

## Reglas Livewire y Alpine

- Livewire es la opcion por defecto para interacciones del modulo.
- Alpine se usa para estado visual ligero.
- Evitar AJAX manual repetitivo con jQuery, Axios o `fetch` cuando Livewire cubra el caso.
- Validacion siempre en servidor.

## Archivado logico

- Regla general: `status` para activo/inactivo y `deleted_at` para archivado.
- No eliminar fisicamente registros comerciales normales.
- Ventas confirmadas, pagos, kardex, cierres y abonos se anulan o compensan; no se archivan como CRUD comun.

## Modulos y fases

- Fase 0: documentacion, Docker, decisiones base.
- Fase 1: autenticacion, usuarios, multiempresa, empresa activa.
- Fase 2: platform admin, planes, modulos, features, limites, bundles, cupones.
- Fase 3: roles, permisos, configuracion empresarial, auditoria base.
- Fase 4: maestras, productos, presentaciones, variantes.
- Fase 5: inventario, kardex, compras, cuentas por pagar.
- Fase 6: POS, ventas congeladas, pagos mixtos, caja.
- Fase 7: credito, puntos, promociones, combos.
- Fase 8: reportes, importaciones, auditoria avanzada.
- Fase 9: integraciones, storage externo, Railway, Wompi, facturacion electronica.

## Pruebas obligatorias

- Aislamiento tenant.
- Creacion automatica de branch, warehouse y cash register principal.
- Modulos, features, overrides y limites.
- CRUD Livewire sin recarga.
- Archivado y restauracion.
- Inventario, ventas, credito, pagos mixtos, devoluciones y anulaciones.
- Facturacion externa fallida sin borrar venta interna.

## Documentos que siempre se deben revisar antes de cambiar codigo

- `AGENTS.md`
- `docs/README.md`
- `docs/arquitectura-saas.md`
- `docs/modelo-datos.md`
- `docs/decisiones-tecnicas.md`
- Documentos especificos del modulo afectado

## Procedimiento antes de cada fase

1. Informar objetivo.
2. Informar archivos a crear o modificar.
3. Informar migraciones previstas.
4. Informar riesgos.
5. Informar pruebas.
6. Informar documentacion a actualizar.

## Procedimiento despues de cada fase

1. Informar que se creo.
2. Informar que se modifico.
3. Informar pruebas ejecutadas.
4. Informar resultado.
5. Informar deuda o pendientes.
6. Informar Markdown actualizados.
