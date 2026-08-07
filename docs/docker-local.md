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
