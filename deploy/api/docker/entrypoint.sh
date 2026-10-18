#!/bin/sh
# =============================================================================
# Entrypoint de la API de OpticaHefesto.
# =============================================================================
#
# QUE HACE, EN ORDEN
# ------------------
#   1. Espera a que PostgreSQL acepte conexiones. Sin esto, `migrate` falla al
#      azar en un despliegue recién levantado.
#   2. Sale si el comando es uno de los que se ejecutan SIN base (por ejemplo
#      `composer install`), porque no tiene sentido esperar a PostgreSQL para
#      instalar dependencias.
#   3. Corre las migraciones.
#   4. Limpia caches de Laravel.
#   5. Deja el contenedor esperando a la base, o ejecuta el comando pedido.
#
# LAS MIGRACIONES CORREN EN CADA ARRANQUE, Y ES A PROPOSITO
# ---------------------------------------------------------
# Hay una sola migracion por version y son idempotentes. Levantarlo con el
# esquema desactualizado es peor que correrlas siempre.
#
# EN UN ESCALADO DE VARIAS COPIAS HAY QUE TENER CUIDADO
# ----------------------------------------------------
# Si se corre `api` con varias réplicas, todas intentarán migrar al mismo
# tiempo. Para eso esta `MIGRAR_AL_ARRANCAR=false`: se apagan las migraciones en
# las réplicas y se corre una vez a mano. Ver docs/04.
#
# `set -e` para que cualquier fallo detenga el despliegue en vez de dejar un
# contenedor a medio levantar.
# =============================================================================

set -e

ESPERA_MAXIMA="${ESPERA_MAXIMA_SEGUNDOS:-60}"

esperar_base() {
    intentos=0

    while [ "$intentos" -lt "$ESPERA_MAXIMA" ]; do
        if PGPASSWORD="$DB_PASSWORD" pg_isready \
            --host="${DB_HOST:-pgsql}" \
            --port="${DB_PORT:-5432}" \
            --username="${DB_USERNAME:-optica}" \
            --dbname="${DB_DATABASE:-optica}" \
            >/dev/null 2>&1; then
            return 0
        fi

        intentos=$((intentos + 1))
        sleep 1
    done

    echo "optica: la base de datos no respondio en ${ESPERA_MAXIMA} segundos." >&2
    return 1
}

comando="$1"

# Comandos que NO necesitan la base de datos: no tiene sentido esperar a
# PostgreSQL para instalar dependencias o arrancar un shell.
case "$comando" in
    composer|"")
        exec "$@"
        ;;
    "php artisan serve"|"php artisan tinker"|"php artisan about")
        exec "$@"
        ;;
esac

esperar_base

if [ "${MIGRAR_AL_ARRANCAR:-true}" = "true" ]; then
    echo "optica: aplicando migraciones."
    php artisan migrate --force --no-interaction
else
    echo "optica: migraciones omitidas (MIGRAR_AL_ARRANCAR=false)."
fi

# Los caches se limpian SIEMPRE, y antes de cualquier comando artisan que los
# use. Un `config:cache` de un despliegue anterior puede seguir apuntando a un
# entorno anterior.
php artisan config:clear --quiet || true
php artisan cache:clear --quiet || true

echo "optica: ejecutando '${comando}'."

exec "$@"