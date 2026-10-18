#!/bin/sh
# =============================================================================
# Restauracion de OpticaHefesto.
# =============================================================================
#
#     ./restauracion.sh <carpeta-respaldo>
#     ./restauracion.sh <carpeta-respaldo> --confirmar
#
# QUE HACE
# --------
# 1. Verifica que el respaldo esta COMPLETO y que el SHA-256 coincide.
# 2. Verifica que el destino este DETENIDO.
# 3. Pide confirmacion explicita.
# 4. Limpia las tablas con TRUNCATE, JAMAS con DROP.
# 5. Restaura con pg_restore.
# 6. Deja el servicio DETENIDO.
#
# LAS REGLAS QUE ESTE SCRIPT RESPETA, Y POR QUE
# --------------------------------------------
# 1. NUNCA DROP
#    `DROP TABLE` borra la tabla, sus indices, sus restricciones, sus
#    politicas de RLS y sus funciones de apoyo. Si el respaldo falla a mitad de
#    camino, queda una base sin RLS: el aislamiento entre opticas desaparece y
#    no hay forma de saber que paso. Con TRUNCATE, la estructura y las
#    politicas siguen en pie, y un error de restauracion deja datos
#    incompletos, no una base desprotegida.
#
#    Para que el TRUNCATE funcione sin bloquear en cascada se usa
#    `CASCADE`, que recorre las tablas que dependen de estas.
#
# 2. NUNCA SOBRESCRIBIR SIN CONFIRMAR
#    Restaurar REEMPLAZA los datos. Es la operacion mas destructiva que hay en
#    este proyecto, y por eso tiene que llevar `--confirmar` explicito.
#
# 3. VERIFICAR EL SHA-256 ANTES DE ESCRIBIR
#    Un volcado danado se restaura sin quejarse y deja una base incoherente.
#    El hash se comprueba ANTES de limpiar nada.
#
# 4. DEJAR EL SERVICIO DETENIDO AL TERMINAR
#    Un despliegue se levanta con el servicio apagado. Si la restauracion
#    levanta la API, alguien puede entrar y leer una base a medio restaurar.
#
# 5. NO HAY `--force` QUE SALTE LA VERIFICACION
#    Si el hash no coincide, el script para. El hash es la unica prueba de que
#    lo guardado sirve.
#
# Ver docs/08.
# =============================================================================

set -eu

RED="${RED:-}"
SERVICIO="${SERVICIO:-api}"
COMPOSE="${COMPOSE:-docker-compose.prod.yml}"
RUTA_COMPOSE="${RUTA_COMPOSE:-deploy}"

ESPACIO_MINIMO_MB="${ESPACIO_MINIMO_MB:-256}"

# Tablas que se limpian. TODAS las de negocio, para que no queden restos de una
# restauracion anterior a medias.
TABLAS="accesos_recetas ajustes controles_calidad entregas pedido_items \
pedidos_laboratorio graduaciones recetas armazones lentes pacientes usuarios"

uso() {
    cat <<'FIN'
Uso: restauracion.sh <carpeta-respaldo> --confirmar

  <carpeta-respaldo>   Carpeta que contiene el .dump, el manifiesto.txt y COMPLETO.
  --confirmar           Obligatorio. Sin esto no se toca la base.

La restauracion REEMPLAZA todos los datos de negocio. Hago un respaldo antes.
FIN
}

CONFIRMAR=0
ORIGEN=""

for argumento in "$@"; do
    case "$argumento" in
        --confirmar) CONFIRMAR=1 ;;
        -h|--help) uso; exit 0 ;;
        -*) echo "restauracion.sh: opcion desconocida: $argumento" >&2; uso; exit 2 ;;
        *) ORIGEN="$argumento" ;;
    esac
done

if [ -z "$ORIGEN" ]; then
    echo "restauracion.sh: falta la carpeta del respaldo." >&2
    uso
    exit 2
fi

if [ "$CONFIRMAR" -ne 1 ]; then
    echo "restauracion.sh: falta --confirmar." >&2
    echo "             Restaurar reemplaza todos los datos de negocio." >&2
    exit 2
fi

# -----------------------------------------------------------------------------
# 1. El respaldo tiene que estar COMPLETO
# -----------------------------------------------------------------------------
echo "== 1/6. Verificando que el respaldo este completo"

if [ ! -d "$ORIGEN" ]; then
    echo "restauracion.sh: la carpeta '$ORIGEN' no existe." >&2
    exit 1
fi

if [ ! -f "$ORIGEN/COMPLETO" ]; then
    echo "restauracion.sh: el respaldo NO tiene el archivo COMPLETO." >&2
    echo "             El respaldo quedo a medias. No se restaura." >&2
    exit 1
fi

DUMP="$(find "$ORIGEN" -maxdepth 1 -name '*.dump' -type f | head -1)"

if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
    echo "restauracion.sh: no hay ningun archivo .dump en '$ORIGEN'." >&2
    exit 1
fi

MANIFIESTO="$ORIGEN/manifiesto.txt"

if [ ! -f "$MANIFIESTO" ]; then
    echo "restauracion.sh: no hay manifiesto. Un respaldo sin hash no es restaurable" >&2
    echo "             con seguridad. Ver docs/08." >&2
    exit 1
fi

# REGLA 3: el hash se comprueba ANTES de limpiar nada.
ESPERADO="$(awk 'NF==2 {print $1}' "$MANIFIESTO" | tail -1)"

if [ -z "$ESPERADO" ]; then
    echo "restauracion.sh: el manifiesto no tiene un SHA-256 legible." >&2
    exit 1
fi

OBTENIDO="$(cd "$ORIGEN" && sha256sum "$(basename "$DUMP")" | awk '{print $1}')"

if [ "$ESPERADO" != "$OBTENIDO" ]; then
    echo "restauracion.sh: el SHA-256 NO coincide." >&2
    echo "             Esperado: $ESPERADO" >&2
    echo "             Obtenido: $OBTENIDO" >&2
    echo "             El respaldo esta danado. No se toca la base." >&2
    exit 1
fi

echo "   SHA-256 verificado: $OBTENIDO"
echo "   Volcado: $DUMP"

# -----------------------------------------------------------------------------
# 2. Espacio y servicios
# -----------------------------------------------------------------------------
echo "== 2/6. Comprobando espacio y servicios"

: "${DB_HOST:?falta DB_HOST}"
: "${DB_PORT:?falta DB_PORT}"
: "${DB_DATABASE:?falta DB_DATABASE}"
: "${DB_USERNAME:?falta DB_USERNAME}"
: "${DB_PASSWORD:?falta DB_PASSWORD}"

if [ -n "$RUTA_COMPOSE" ] && [ -f "$RUTA_COMPOSE/$COMPOSE" ]; then
    if command -v df >/dev/null 2>&1; then
        LIBRES_MB="$(df -Pm "$ORIGEN" | awk 'NR==2 {print $4}')"

        if [ "$LIBRES_MB" -lt "$ESPACIO_MINIMO_MB" ]; then
            echo "restauracion.sh: quedan ${LIBRES_MB} MB y se exigen ${ESPACIO_MINIMO_MB} MB." >&2
            exit 1
        fi

        echo "   espacio disponible: ${LIBRES_MB} MB"
    fi

    # REGLA 4: el servicio tiene que estar DETENIDO antes de restaurar.
    echo "   Deteniendo el servicio '$SERVICIO'."

    COMPOSE_PROYECTO="${RUTA_COMPOSE:-.}" docker compose -f "$RUTA_COMPOSE/$COMPOSE" stop "$SERVICIO"
fi

correr() {
    if [ -n "$RED" ]; then
        docker run --rm --network "$RED" \
            -e PGPASSWORD="$DB_PASSWORD" \
            -v "$(cd "$(dirname "$DUMP")" && pwd):/respaldo:ro" \
            postgres:17-alpine "$@"
    else
        PGPASSWORD="$DB_PASSWORD" "$@"
    fi
}

# -----------------------------------------------------------------------------
# 3. Aviso y confirmacion
# -----------------------------------------------------------------------------
echo "== 3/6. Confirmacion"

echo ""
echo "   Se va a REEMPLAZAR el contenido de '$DB_DATABASE' en '$DB_HOST'."
echo "   Se borran las tablas de negocio con TRUNCATE (nunca con DROP)."
echo "   Las recetas y graduaciones de los pacientes quedan como estaban en el"
echo "   respaldo, no como estaban antes."
echo ""
printf "   Escriba SIEMPRE para continuar: "
read -r CONFIRMA

if [ "$CONFIRMA" != "SIEMPRE" ]; then
    echo "restauracion.sh: cancelado. No se toco la base." >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 4. Limpieza con TRUNCATE. NUNCA DROP.
# -----------------------------------------------------------------------------
echo "== 4/6. Limpiando las tablas con TRUNCATE"

# `RESTART IDENTITY CASCADE`: `CASCADE` limpia tambien las tablas que dependen
# de estas (los items dependen del pedido, por ejemplo), y `RESTART IDENTITY`
# deja los contadores en cero.
correr psql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --set=ON_ERROR_STOP=1 \
    --command="TRUNCATE TABLE $TABLAS RESTART IDENTITY CASCADE;"

# -----------------------------------------------------------------------------
# 5. Restauracion
# -----------------------------------------------------------------------------
echo "== 5/6. Restaurando el volcado"

correr pg_restore \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --no-owner \
    --no-privileges \
    --exit-on-error \
    --single-transaction \
    "/respaldo/$(basename "$DUMP")"

# -----------------------------------------------------------------------------
# 6. Comprobacion. El servicio queda DETENIDO.
# -----------------------------------------------------------------------------
echo "== 6/6. Comprobando y dejando el servicio detenido"

TOTAL="$(correr psql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --tuples-only \
    --no-align \
    --command="SELECT COUNT(*) FROM recetas;")"

echo "   Recetas restauradas: $(echo "$TOTAL" | tr -d ' ')"

# REGLA 4 (cont.): el servicio se queda DETENIDO.
if [ -n "$RUTA_COMPOSE" ] && [ -f "$RUTA_COMPOSE/$COMPOSE" ]; then
    echo "   El servicio '$SERVICIO' queda DETENIDO."
    echo "   Cuando se levante:"
    echo "       cd $RUTA_COMPOSE && docker compose -f $COMPOSE up -d"
    echo "   Y despues, antes de recibir pacientes reales:"
    echo "       docker compose -f $COMPOSE exec api php artisan optica:verificar-aislamiento"
fi

echo ""
echo "Restauracion terminada. Revise lo anterior antes de levantar el servicio."