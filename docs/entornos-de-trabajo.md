# Entornos de Trabajo (multi-dispositivo)

El usuario trabaja este proyecto desde dos maquinas distintas, cada una con su propio WSL/Docker local. Esta guia existe para que cualquier sesion de IA sepa en cual esta parada y no repita pasos de "primera vez" innecesarios.

## Dispositivos conocidos

| Dispositivo | Hostname WSL | Ruta del repo | Notas |
|---|---|---|---|
| Laptop (actual, mas usado) | `DESKTOP-7UR4OO1` | `/home/jupazago/repos/retail_saas` | Ubuntu 26.04 LTS, Docker 29.x, ~4 cores / ~5GB RAM asignados a WSL2. |
| PC de escritorio (mas potente) | _pendiente de completar la primera vez que se use aca_ | _pendiente_ | _pendiente (specs, ruta real del repo, etc.)_ |

**Como saber en cual estamos ahora mismo:** correr `hostname` en la terminal WSL y comparar con la tabla. La primera vez que se trabaje desde el PC de escritorio, completar esa fila (hostname, ruta, specs) en el mismo commit que cualquier otro cambio.

## Levantar el proyecto (dispositivo ya configurado)

```bash
cd /home/jupazago/repos/retail_saas   # o la ruta que corresponda segun la tabla de arriba
docker compose up -d --build
docker compose ps
```

App: `http://localhost:8088` · Mailpit: `http://localhost:8027` (puertos de la tabla en `docker-local.md`).

En el laptop existe el alias `rs` (`~/.bash_aliases`) que hace `cd` al repo y abre Claude Code en bypass — no asumir que existe igual en un dispositivo nuevo, se configura aparte si se quiere.

## Primera vez en un dispositivo nuevo

1. **Prerequisitos:** WSL2 con una distro Ubuntu, Docker (Desktop o Engine) con integracion WSL activada, git.
2. **Traer el repo:** clonar o copiar la carpeta completa del proyecto.
3. **Copiar el `.env`:** el usuario lo duplica de forma segura desde el laptop. Copiar y pegar el archivo tal cual **alcanza en la mayoria de los casos** (`.env` esta en `.gitignore`, nunca viaja por git, así que no hay nada que sincronizar por otro lado) — pero antes de levantar los contenedores, revisar dos cosas puntuales:
   - **Puertos ocupados:** si el PC de escritorio ya corre otros proyectos Docker, `8088`, `5174`, `5434`, `1027` u `8027` podrian estar tomados. Verificar con `docker ps` y `ss -tulpn | grep <puerto>` antes de levantar. Si hay choque, cambiar **solo** la variable de puerto correspondiente en `.env` (nunca tocar `docker-compose.yml` para esto — ver `AGENTS.md`).
   - **La base de datos no viaja con el `.env`:** los datos viven en el volumen Docker `retail_saas_postgres_data`, que en un dispositivo nuevo arranca vacio aunque el `.env` sea identico. Hace falta migrar y sembrar de nuevo (paso 5).
4. **Levantar:**
   ```bash
   docker compose up -d --build
   ```
5. **Base de datos (solo la primera vez en este dispositivo):**
   ```bash
   docker compose exec app php artisan migrate --seed
   ```
6. **Verificar:** abrir `http://localhost:8088` (o el puerto que haya quedado en `.env` si se cambio por conflicto) y confirmar que carga el login.

## Variables que debe tener el `.env`

Referencia rapida — si falta alguna de estas, algo no va a funcionar:

- **App:** `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_PORT`, `VITE_PORT`
- **Locale:** `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`
- **Docker/Compose:** `COMPOSE_PROJECT_NAME`, `POSTGRES_VOLUME`
- **Base de datos:** `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_FORWARD_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE`
- **Cache/Sesion/Cola:** `CACHE_STORE`, `SESSION_DRIVER`, `SESSION_LIFETIME`, `QUEUE_CONNECTION`
- **Correo (SMTP real de Gmail, credencial sensible):** `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- **Mailpit (fallback/dev, aunque hoy se usa Gmail real):** `MAILPIT_SMTP_PORT`, `MAILPIT_UI_PORT`

`MAIL_PASSWORD` es la contraseña de aplicacion real de Gmail (`jupazago11@gmail.com`) — es del propio usuario, esta bien que quede en ambos dispositivos, pero sigue siendo un secreto real: nunca debe terminar en un commit ni pegarse fuera de `.env`.

## Nota

Si algo no arranca en el dispositivo nuevo y no es ninguno de los puntos de arriba, revisar `docker-local.md` (puertos y convenciones del proyecto) y `comandos-wsl.md` (comandos sueltos para reconstruir assets, ver logs, etc.) antes de asumir que es un problema nuevo.
