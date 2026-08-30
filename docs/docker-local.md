# Docker Local

## Estado inspeccionado

- No hay contenedores activos.
- Existen contenedores detenidos de otros proyectos.
- Existen redes y volumenes ajenos que no deben tocarse.

## Convenciones locales

- Proyecto Compose: `retail_saas`
- Red: `retail_saas_network`
- Volumen PostgreSQL: `retail_saas_postgres_data`
- Base local: `retail_saas_db`

## Puertos reservados

- Web: `8088`
- Vite: `5174`
- PostgreSQL forward: `5434`
- Mailpit SMTP: `1027`
- Mailpit UI: `8027`

## Servicios previstos

- `app`: PHP-FPM o contenedor Laravel.
- `web`: Nginx.
- `postgres`: PostgreSQL.
- `mailpit`: correo local.
- `queue`: worker separado.
- `vite`: Node 24 para assets y evitar depender del Node 18 local.

## Reglas

- No ejecutar comandos Docker fuera de esta carpeta.
- No usar puertos ocupados por proyectos detenidos si pueden reactivarse luego.
- No fijar `container_name` salvo necesidad documentada.

## Comandos validados

- Levantar base del stack: `docker compose up -d --build postgres mailpit app web`
- Compilar assets sin depender del Node local: `docker compose run --rm --entrypoint /bin/sh vite -lc "npm install && npm run build"`
- Desarrollo frontend con hot reload: `docker compose up vite`

## Nota operativa

- El host actual conserva Node 18.x y Vite 8 requiere Node 20.19+ o 22.12+, por eso el build debe correr dentro del servicio `vite` con Node 24.
- `docker compose run vite ...` sin override de `entrypoint` no es la forma correcta en este proyecto porque la imagen base de Node puede interpretar el comando de forma inesperada.

## Permisos de `storage/` y `bootstrap/cache` (bind mount vs PHP-FPM)

- `docker-compose.yml` monta el repo completo (`./:/var/www/html`) sin `user:` en el servicio `app`, asi que los archivos quedan con el UID del usuario del host (el mismo que hace `docker compose up`). PHP-FPM adentro del contenedor corre como `www-data` (uid 33) por configuracion por defecto de la imagen `php:8.4-fpm`, que no coincide con el UID del host.
- Consecuencia: si `storage/framework/{views,cache,sessions}`, `storage/logs` o `bootstrap/cache` quedan con permisos `775` (el `chmod` tipico de un checkout normal), `www-data` — al ser ni el owner ni el grupo — no tiene permiso de escritura ahi. Mientras la cache de vistas/config ya este tibia (generada antes, por ejemplo via `docker compose exec app php artisan ...`, que corre como root y por eso siempre puede escribir) todo funciona porque PHP-FPM solo necesita leer. El problema aparece en el momento en que algo fuerza una recompilacion en caliente: `php artisan view:clear`/`optimize:clear` corridos por `docker compose exec` (root) borran el cache, y el primer request real que llega por Nginx (servido por `www-data`) revienta con `ErrorException: tempnam(): file created in the system's temporary directory` al intentar compilar y escribir el `.php` compilado — rompe **toda** la app (cualquier pagina, no solo la que se estaba editando), no solo componentes Volt/Livewire.
- Correccion aplicada (2026-08-29): `docker compose exec app chmod -R o+rwX storage bootstrap/cache`. Al ser bind mount, el cambio queda en el filesystem del host y sobrevive a reinicios/reconstrucciones del contenedor (no es un volumen nombrado que se pueda perder con `docker compose down -v`). Es una relajacion de permisos aceptable solo porque este stack corre en la maquina local del desarrollador, no en produccion (ver `deploy-railway.md` para el entorno real, que no deberia heredar este permiso).
- **El `chmod` de arriba NO fue suficiente** (descubierto el mismo dia, segunda vuelta): el permiso de "otros" (`o+w`) deja a `www-data` ESCRIBIR contenido en un archivo compilado que ya existia con otro dueño (root, de una compilacion anterior hecha via `docker compose exec` sin `--user`), pero Blade tambien hace `touch($rutaCompilada, filemtime($blade))` para igualar la fecha de modificacion con la del `.blade.php` fuente — y fijar una fecha EXACTA (no "ahora") con `touch()`/`utime()` exige ser el DUEÑO del archivo, no solo tener permiso de escritura. Con archivos viejos todavia con `owner=root` (uid 0), eso revienta con `ErrorException: touch(): Utime failed: Operation not permitted` apenas se edita un `.blade.php` cuyo compilado previo quedo con ese dueño — mismo sintoma que el `tempnam()` de antes (500 en el momento de recompilar), pero por una causa distinta y mas dificil de tapar solo con permisos.
- Correccion completa: `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`, ademas del `chmod` de arriba. Con esto cualquier archivo existente pasa a ser propiedad de `www-data`, y cualquier archivo nuevo que cree tambien queda asi automaticamente — soluciona el `touch()` de raiz en vez de solo permitir la escritura de contenido.
- **Causa real de fondo, encontrada en una tercera vuelta el mismo dia:** `docker compose exec app php artisan test ...` (sin `--user`, o sea como root) TAMBIEN recompila vistas Blade — cualquier prueba que renderice un componente Livewire (`Livewire::test(...)`) compila las mismas plantillas que usa el sitio real, en la MISMA carpeta `storage/framework/views`, porque `phpunit.xml`/`tests/TestCase.php` aislaban la base de datos y la sesion pero no `VIEW_COMPILED_PATH`. Cada corrida de pruebas como root volvia a dejar unos cuantos archivos con `owner=root`, deshaciendo el `chown` de arriba para exactamente las vistas que las pruebas tocaron — por eso el error volvia a aparecer despues de "arreglarlo" y verificar con `php artisan test`.
- **Correccion definitiva (2026-08-29, tercera vuelta):** `tests/TestCase.php::createApplication()` ahora fija `VIEW_COMPILED_PATH` a `storage/framework/testing/views` (carpeta separada, ya versionada como directorio de trabajo de pruebas) antes de arrancar la aplicacion de prueba — mismo mecanismo ya usado ahi para forzar SQLite en memoria. Con esto, `php artisan test` nunca vuelve a escribir en `storage/framework/views` sin importar que usuario del SO lo ejecute; esa carpeta se compila unicamente por trafico real (o por `docker compose exec --user www-data app php artisan view:cache`).
- Regla operativa vigente: `chown -R www-data:www-data storage bootstrap/cache` sigue siendo necesario UNA VEZ para reparar los archivos que ya hayan quedado con `owner=root` antes de este fix (o si alguien corre `view:cache`/`optimize` sin `--user www-data` en el futuro), pero `php artisan test` ya no puede volver a causar esto. Si la app responde `500` con `tempnam()` o con `touch(): Utime failed`, seguir sospechando primero de un comando reciente corrido como root que haya escrito en `storage/framework/views` o `bootstrap/cache` (no de un bug de codigo), y repetir el `chown` de arriba. Si esto vuelve a pasar en el PC de escritorio (ver `entornos-de-trabajo.md`), aplicar el mismo `chown` ahi tambien — no viaja solo entre maquinas porque es un permiso/dueño de filesystem, no algo versionado en git (el fix en `tests/TestCase.php` si viaja, porque es codigo).
