# Comandos WSL

Guia corta para levantar `retail_saas` en WSL sin chocar con otros proyectos.

## Bloque principal

Copia y pega esto en una terminal WSL:

```bash
cd /home/jupazago/Documentos/saas/retail_saas
docker compose up -d
rm -f public/hot
docker compose exec app php artisan optimize:clear
docker compose run --rm --entrypoint /bin/sh vite -lc "npm install && npm run build"
docker compose ps
```

Luego abre:

- App: `http://localhost:8088`
- Mailpit: `http://localhost:8027`

## Si quieres Vite en caliente

Usa este bloque en otra terminal WSL:

```bash
cd /home/jupazago/Documentos/saas/retail_saas
docker compose up -d vite
docker compose exec vite sh -lc "npm install && npm run dev -- --host 0.0.0.0 --port 5174"
```

Verifica:

```bash
cd /home/jupazago/Documentos/saas/retail_saas
cat public/hot
```

Debe mostrar `http://localhost:5174`.

## Si la app sale sin estilos

Pega esto:

```bash
cd /home/jupazago/Documentos/saas/retail_saas
rm -f public/hot
docker compose exec app php artisan optimize:clear
docker compose run --rm --entrypoint /bin/sh vite -lc "npm install && npm run build"
```

## Ver estado

```bash
cd /home/jupazago/Documentos/saas/retail_saas
docker compose ps
```

## Ver logs

```bash
cd /home/jupazago/Documentos/saas/retail_saas
docker compose logs -f
```

## Apagar todo

```bash
cd /home/jupazago/Documentos/saas/retail_saas
docker compose down
```

## Puertos

- App: `http://localhost:8088`
- Vite: `http://localhost:5174`
- Mailpit: `http://localhost:8027`
- PostgreSQL: `127.0.0.1:5434`
