# Documentacion Base

## Objetivo

Esta carpeta centraliza las decisiones del proyecto antes de generar migraciones y codigo de negocio. La documentacion debe mantenerse consistente con la implementacion.

## Orden minimo de lectura

1. `../AGENTS.md`
2. `README.md`
3. `arquitectura-saas.md`
4. `modelo-datos.md`
5. `decisiones-tecnicas.md`

## Documentos disponibles

- `arquitectura-saas.md`: arquitectura objetivo, capas y limites.
- `modelo-datos.md`: modelo conceptual y tablas previstas por dominio.
- `matriz-planes-modulos.md`: propuesta inicial de planes, modulos, features y limites.
- `roles-y-permisos.md`: permisos tecnicos y roles empresariales.
- `estados-del-sistema.md`: enums y transiciones esperadas.
- `flujo-pos.md`: flujo funcional del POS y puntos de validacion.
- `reglas-inventario.md`: reglas del stock, costo promedio y kardex.
- `configuracion-empresa.md`: configuracion tipada por empresa.
- `decisiones-tecnicas.md`: stack, versiones, tradeoffs y decisiones abiertas.
- `registro-cambios-ia.md`: bitacora de cambios por fase.
- `roadmap.md`: fases de implementacion.
- `docker-local.md`: lineamientos de Docker local.
- `comandos-wsl.md`: comandos listos para pegar en WSL y operar este proyecto.
- `entornos-de-trabajo.md`: dispositivos donde trabaja el usuario (laptop/PC de escritorio), como levantar el proyecto en cada uno y pasos de primera vez en un dispositivo nuevo.
- `deploy-railway.md`: lineamientos de despliegue en Railway.

## Regla operativa

Si un cambio afecta tablas, estados, permisos, inventario, POS, planes o despliegue, el documento correspondiente debe actualizarse en el mismo cambio.
