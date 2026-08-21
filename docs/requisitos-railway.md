# Requisitos para subir un proyecto a Railway sin inconvenientes

Checklist genérico + bitácora de errores reales, pensado para reusar en
CUALQUIER proyecto futuro (no solo Laravel/PHP) que se vaya a desplegar en
Railway. Cada vez que un deploy falle por algo nuevo, se agrega una entrada
en la seccion "Errores encontrados" de este mismo archivo, con sintoma,
causa real y el fix — no basta con anotar "se arreglo", hay que dejar
claro POR QUE fallaba para reconocerlo rapido la proxima vez.

## Checklist antes de subir

- [ ] El lockfile de dependencias (`composer.lock`, `package-lock.json`,
      `poetry.lock`, etc.) esta sincronizado con su manifest (`composer.json`,
      `package.json`...). Railway instala EXCLUSIVAMENTE lo que dice el
      lockfile, sin importar lo que haya en tu `vendor/`/`node_modules/`
      local — si el lock esta desactualizado, el build en la nube falla
      aunque local funcione perfecto.
- [ ] La version del lenguaje/runtime declarada en el manifest coincide con
      lo que REALMENTE necesitan las dependencias ya bloqueadas en el
      lockfile (no solo con lo que corre en tu Docker local).
- [ ] El build corre limpio en un ambiente fresco (sin cache, sin
      `vendor`/`node_modules` locales) — probar con
      `composer install`/`npm ci` desde cero antes de subir, no solo
      `composer update`/`npm install`.
- [ ] La app escucha en el puerto que Railway inyecta via `$PORT` (nunca un
      puerto fijo hardcodeado).
- [ ] La URL publica/base de la app es configurable via variable de entorno
      (no hardcodeada a `localhost`).
- [ ] Logs van a stdout/stderr — Railway no lee archivos de log locales.
- [ ] Si el proyecto tiene varios procesos (web, worker de colas,
      scheduler...), cada uno es un SERVICIO SEPARADO en Railway. Railway
      **no** ejecuta `docker-compose.yml` directamente ni comparte
      volumenes/filesystem entre servicios.
- [ ] Storage de archivos subidos por usuarios (logos, adjuntos, etc.) no
      depende de un volumen local — usar algo S3-compatible o el volumen
      persistente que ofrece Railway, explicitamente configurado.
- [ ] La base de datos usa las variables que Railway inyecta (`DATABASE_URL`
      o las `PG*`/`MYSQL*` que correspondan), no nombres de host de
      docker-compose local (`postgres`, `mysql`, etc.).

## Como elegir el metodo de subida (pantalla "New Project")

- **GitHub Repository**: la opcion correcta casi siempre. Railway detecta
  el lenguaje o el `Dockerfile` del repo y construye desde el codigo
  fuente.
- **Docker Image**: solo sirve si ya tienes una imagen YA PUBLICADA en un
  registry (Docker Hub, GHCR...). No es para "quiero construir mi
  Dockerfile" — eso es "GitHub Repository".
- Railway no corre `docker-compose.yml`. Si el proyecto local usa varios
  contenedores compartiendo un volumen (ej. `php-fpm` sin nginx embebido +
  un contenedor nginx aparte leyendo el mismo codigo), esa arquitectura no
  se traslada tal cual — hace falta una imagen que sirva HTTP por si sola
  (nginx+php-fpm en el mismo contenedor, o un servidor embebido tipo
  FrankenPHP/Octane).

## Errores encontrados (bitacora)

### 2026-08-20 — Railpack construyo con PHP 8.3, pero el lock exigia 8.4+
**Proyecto:** retail_saas (Laravel).
**Sintoma:** `composer install` fallaba con
`Your lock file does not contain a compatible set of packages`, listando
paquetes `symfony/*` que requieren `php >=8.4.1`.
**Causa real:** Railpack (el build system de Railway) LEE el campo
`"php"` de `composer.json` para elegir la version del runtime — no
detecta la version que realmente necesitan las dependencias ya bloqueadas
en `composer.lock`. `composer.json` decia `^8.3` pero `composer.lock` ya
tenia paquetes que solo corren en PHP 8.4+ (el Dockerfile local ya usaba
`php:8.4-fpm-bookworm`, asi que el lock se genero ahi).
**Fix:** igualar el constraint de `composer.json` a la version real que
pide el lock (`^8.4`). Alternativa sin tocar `composer.json`: variable de
entorno `RAILPACK_PHP_VERSION=8.4` en el proyecto de Railway.
**Leccion general:** el manifest de dependencias debe reflejar
honestamente lo que el LOCK realmente requiere, no quedar desactualizado
mientras el lock evoluciona. `composer validate --strict` lo detecta al
instante.

### 2026-08-20 — composer.lock incompleto (paquetes en composer.json que nunca se bloquearon)
**Proyecto:** retail_saas (Laravel).
**Sintoma:** no llego a mostrarse en este build especifico (el error de
PHP paso primero), pero se detecto al revisar: `endroid/qr-code` y sus
dependencias (`bacon/bacon-qr-code`, `dasprid/enum`) estaban declaradas en
`composer.json` (el codigo ya generaba QRs con ellas) pero NUNCA quedaron
escritas en `composer.lock`.
**Causa real:** probablemente se instalaron localmente sin correr
`composer update` y comitear el lock actualizado — el codigo funcionaba
en local porque `vendor/` si las tenia fisicamente, pero un `composer
install` limpio (como el que hace Railway) no las habria instalado nunca.
**Fix:** `composer update --lock` — resuelve y bloquea versiones SIN
tocar `vendor/` ni actualizar paquetes ya bloqueados (confirmado con
`git diff composer.lock`: solo agrego los 3 paquetes faltantes, cero
versiones existentes cambiaron).
**Leccion general:** `composer install`/`npm ci` en CI usan
EXCLUSIVAMENTE lo que esta en el lockfile. Correr
`composer validate --strict` (o el equivalente del ecosistema) localmente
antes de cada push relevante detecta esto de inmediato, sin esperar a que
falle en Railway.

### 2026-08-20 — "Please provide a valid cache path" en `php artisan config:cache`
**Proyecto:** retail_saas (Laravel). Ocurrio DESPUES de arreglar los dos
errores anteriores — el build ya llegaba mucho mas lejos (composer, npm
build, `mkdir -p storage/framework/...` corrian bien) y truena justo en
`config:cache`.
**Sintoma:** `InvalidArgumentException: Please provide a valid cache path.`
en `Illuminate\View\Compilers\Compiler`, disparado al arrancar
`BladeIconsServiceProvider` durante el boot de `config:cache`.
**Causa real:** el proyecto no tenia `config/view.php` publicado, asi que
usaba el default del framework:
`'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views')))`.
`realpath()` sobre una ruta devuelve `false` SILENCIOSAMENTE si en ese
instante exacto no puede resolverla (permisos, timing del contenedor de
build, etc.) — y `false` como cache path hace que el compilador de Blade
truene apenas arranca cualquier service provider que use vistas (en este
caso `blade-ui-kit/blade-icons`). No es que la carpeta "no exista en git"
(de hecho si esta trackeada via los `.gitignore` placeholder que trae
Laravel de fabrica) — es la llamada a `realpath()` la que es fragil en
un ambiente de build efimero.
**Fix:** publicar `config/view.php` en el proyecto y quitar el
`realpath()`: `'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views'))`.
`storage_path()` siempre devuelve un string valido, exista o no la
carpeta todavia, asi que el compilador nunca recibe `false`.
**Leccion general:** cualquier config que envuelva una ruta en
`realpath()` (o equivalente) es un punto fragil en un ambiente de build
que no es identico a tu Docker local — preferir la ruta plana
(`storage_path()`/`base_path()`/etc.) sin normalizar, salvo que de verdad
necesites resolver symlinks.

### 2026-08-20 — Start Command vs pre-deploy step: el 502 no era de puerto
**Proyecto:** retail_saas (Laravel).
**Sintoma:** "Application failed to respond" (502) aunque el build pasaba
limpio y las migraciones/seeders corrian bien segun los logs.
**Causa real:** el comando `php artisan migrate:fresh --force && php
artisan db:seed ...` quedo puesto en **"Custom Start Command"** en vez de
en **"Add pre-deploy step"** (son dos campos distintos en Settings →
Deploy). El Start Command es el proceso que Railway espera que se quede
VIVO para atender peticiones HTTP — un comando de una sola pasada (migra,
siembra, termina) hace que el contenedor se quede sin proceso principal en
cuanto acaba, y Railway nunca tiene nada escuchando en el puerto.
**Fix:** vaciar "Custom Start Command" (para que Railpack use el suyo, el
que de verdad levanta FrankenPHP) y mover el comando de
migrate/seed a "Add pre-deploy step" — ese SI se espera que termine antes
de arrancar el servidor.
**Leccion general:** en cualquier PaaS con estos dos conceptos separados
(start command persistente vs. pre-deploy/release step de una sola
pasada), un comando que TERMINA nunca va en el campo del proceso
principal, sin importar cuan bien le vaya al correr.

### 2026-08-20 — Sitio cargaba sin CSS/JS (assets en http:// en un sitio https://)
**Proyecto:** retail_saas (Laravel).
**Sintoma:** la pagina cargaba (ya sin 502) pero se veia sin ningun
estilo — HTML plano, fuente serif por defecto del navegador.
**Causa real:** Railway (como Heroku/Render/Fly) termina el TLS en su
borde y reenvia la peticion al contenedor por HTTP plano, con headers
`X-Forwarded-*`. El proyecto no tenia `trustProxies()` configurado en
`bootstrap/app.php`, asi que Laravel veia la conexion interna como
"http" y generaba TODAS las URLs (assets de Vite, rutas, redirects) con
`http://` aunque el sitio publico es `https://`. El navegador bloquea
esos recursos como "contenido mixto" — el CSS/JS existian y respondian
bien, simplemente nunca se cargaban.
**Fix:** agregar en `bootstrap/app.php`, dentro de `withMiddleware()`:
```php
$middleware->trustProxies(
    at: '*',
    headers: Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO,
);
```
`at: '*'` (confiar en cualquier proxy) es seguro aqui porque el unico que
le puede hablar al contenedor es el borde de Railway — no hay acceso
publico directo que lo bypassee.
**Leccion general:** cualquier framework detras de un PaaS que termina
TLS en su borde (Railway, Heroku, Render, Fly...) necesita confiar
explicitamente en los headers `X-Forwarded-*` del proxy, o va a generar
URLs con el esquema equivocado (http en vez de https) sin ningun error
visible — el sintoma es "la pagina carga pero se ve rota/sin assets", no
un error claro en logs.

## Comandos utiles de diagnostico

- `composer validate --no-check-all --strict` — confirma que
  `composer.json` y `composer.lock` estan sincronizados (PHP).
- `npm ci` (en vez de `npm install`) — reproduce localmente lo mismo que
  hace un build limpio en la nube (Node).
- Leer los logs de build de Railway linea por linea: el paso exacto que
  falla (ej. `composer install --optimize-autoloader`) dice mucho mas que
  el mensaje de error final.
