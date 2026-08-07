# Deploy Railway

## Objetivo

Desplegar la aplicacion Laravel en Railway sin depender de nombres internos de Docker local.

## Requisitos

- `PORT` configurable.
- `APP_URL` configurable.
- `DATABASE_URL` o variables `PG*` compatibles con PostgreSQL.
- Logs a stdout/stderr.
- Worker separado para colas si el volumen de trabajo lo requiere.
- Scheduler separado o comando recurrente.
- Health check HTTP.
- Archivos persistentes en storage compatible con S3.

## Estrategia recomendada

- Un servicio web para Laravel.
- Un servicio worker para `queue:work`.
- Un servicio scheduler con `schedule:work` o cron equivalente.
- Una base PostgreSQL administrada por Railway.

## Notas

- No depender de volumen local para `storage/app/public`.
- Ejecutar migraciones de forma controlada durante despliegue, no implicitamente en cada arranque si eso arriesga concurrencia.
- Preparar soporte para `DATABASE_URL` y variables separadas.
