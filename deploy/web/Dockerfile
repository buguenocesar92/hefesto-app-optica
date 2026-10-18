# =============================================================================
# Imagen de la interfaz de OpticaHefesto (Nuxt 3).
# =============================================================================
#
# SE CONSTRUYE EN VARIAS ETAPAS
# -----------------------------
# La etapa final no tiene ni Node ni las dependencias de desarrollo. Solo los
# archivos que el servidor de Nuxt necesita para responder.
#
# SSR APAGADO
# -----------
# El nucleo de Nuxt es el cliente, asi que el servidor de Nuxt sirve los
# estaticos y reenvia las llamadas a la API. La razon de apagar SSR esta en
# `nuxt.config.ts` y en docs/01: con datos de salud, el HTML con el contenido
# ya puesto queda en el historial y en las caches intermedias.
#
# LA BASE
# -------
# `node:22-alpine`, que es lo que declara `package.json` en `engines`.
# =============================================================================

# -----------------------------------------------------------------------------
# Etapa 1: dependencias
# -----------------------------------------------------------------------------
FROM node:22-alpine AS dependencias

WORKDIR /app

# Solo los manifiestos primero, para que la capa de `node_modules` se reutilice
# mientras el codigo cambie.
COPY web/package.json web/package-lock.json* ./

RUN npm ci --no-audit --no-fund

# -----------------------------------------------------------------------------
# Etapa 2: construccion
# -----------------------------------------------------------------------------
FROM node:22-alpine AS construccion

WORKDIR /app

COPY --from=dependencias /app/node_modules ./node_modules
COPY web/ ./

RUN npm run build

# -----------------------------------------------------------------------------
# Etapa 3: ejecucion
# -----------------------------------------------------------------------------
FROM node:22-alpine AS final

# `wget` sirve para el HEALTHCHECK. `tini` es el init que reenvia senales, para
# que el contenedor se detenga limpio al recibir un SIGTERM.
RUN apk add --no-cache wget tini

WORKDIR /app

ENV NODE_ENV=production
ENV NITRO_HOST=0.0.0.0
ENV NITRO_PORT=3000

COPY --from=construccion /app/.output ./.output

# El contenedor corre sin privilegios: no necesita ninguno para servir.
USER node

EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1:3000/ || exit 1

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["node", ".output/server/index.mjs"]