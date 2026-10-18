#!/bin/sh
# =============================================================================
# Respaldo de OpticaHefesto.
# =============================================================================
#
#     ./respaldo.sh <destino>
#     ./respaldo.sh <destino> --force
#
# QUE PRODUCE
# -----------
# Un directorio con:
#
#   - `optica-<marca>.dump`  el volcado de PostgreSQL en formato propio.
#   - `manifiesto.txt`       SHA-256 de cada archivo y datos del volcado.
#   - `COMPLETO`             marca de que el respaldo termino bien.
#
# LAS REGLAS QUE ESTE SCRIPT RESPETA, Y POR QUE
# --------------------------------------------
# 1. MANIFIESTO CON SHA-256 DE CADA ARCHIVO
#    Un respaldo sin verificar es una caja fuerte con la llave adentro. El
#    hash permite saber si lo que se guardo es lo mismo que se respaldo, y
#    `restauracion.sh` lo comprueba ANTES de tocar la base.
#
# 2. MARCA `COMPLETO` AL FINAL, Y SOLO AL FINAL
#    Si el script se corta a mitad de camino, no queda `COMPLETO`. Un respaldo
#    a medias y marcado como bueno es peor que no tener respaldo: uno se
#   Trueno creyendolo entero.
#
# 3. VALIDAR RUTAS Y ESPACIO ANTES DE ESCRIBIR
#    Se comprueba que el destino existe, que se puede escribir y que hay
#    espacio. Un respaldo que falla por disco lleno a mitad de camino deja un
#    volcado truncado que parece bueno.
#
# 4. RECHAZAR DESTINO OCUPADO, SALVO `--force`
#    Un `rm -rf` sobre un directorio equivocado no tiene vuelta atras. Con
#    `--force` se acepta porque a veces hay que rehacer, pero nunca por
#    defecto.
#
# 5. NUNCA DROP, NUNCA SOBRESCRITURA AUTOMATICA
#    Este script solo LEE de la base. Ver `restauracion.sh`, que es donde si
#    hay que limpiar, y que usa `TRUNCATE`, nunca `DROP`.
#
# Ver docs/08.
# =============================================================================

set -eu

# -----------------------------------------------------------------------------
# Variables
# -----------------------------------------------------------------------------
MARCA="$(date -u '+%Y%m%dT%H%M%SZ')"
NOMBRE="optica-${MARCA}"

ESPACIO_MINIMO_MB="${ESPACIO_MINIMO_MB:-256}"
TIMEOUT_CONGELADO="${TIMEOUT_CONGELADO:-0}"

RED="${RED:-}"
CONTENEDOR="${CONTENEDOR:-optica-hefesto-dev-api-1}"

# -----------------------------------------------------------------------------
# Ayuda
# -----------------------------------------------------------------------------
uso() {
    cat <<'FIN'
Uso: respaldo.sh <directorio-destino> [--force]

  <directorio-destino>   Donde se escribe el respaldo. Tiene que existir.
  --force                Permite reemplazar un destino que ya tiene respaldos.

El script NO borra nada de la base de datos: solo lee.
FIN
}

FORCE=0
DESTINO=""

for argumento in "$@"; do
    case "$argumento" in
        --force) FORCE=1 ;;
        -h|--help) uso; exit 0 ;;
        -*) echo "respaldo.sh: opcion desconocida: $argumento" >&2; uso; exit 2 ;;
        *) DESTINO="$argumento" ;;
    esac
done

if [ -z "$DESTINO" ]; then
    echo "respaldo.sh: falta el directorio de destino." >&2
    uso
    exit 2
fi

# -----------------------------------------------------------------------------
# 1. Validacion del destino
# -----------------------------------------------------------------------------
echo "== 1/6. Validando el destino"

if [ ! -d "$DESTINO" ]; then
    echo "respaldo.sh: el destino '$DESTINO' no existe." >&2
    echo "           Creelo antes de respaldar. El script no crea directorios de destino," >&2
    echo "           porque un typo en la ruta es mejor que un respaldo en el lugar equivocado." >&2
    exit 1
fi

if [ ! -w "$DESTINO" ]; then
    echo "respaldo.sh: el destino '$DESTINO' no tiene permiso de escritura." >&2
    exit 1
fi

CARPETA="$DESTINO/$NOMBRE"

# REGLA 4: destino ocupado se rechaza, salvo que se pida --force.
if [ -e "$CARPETA" ]; then
    if [ "$FORCE" -ne 1 ]; then
        echo "respaldo.sh: ya existe '$CARPETA'." >&2
        echo "           Use otro nombre, borrelo a mano, o pase --force." >&2
        exit 1
    fi

    echo "respaldo.sh: --force, se reemplaza '$CARPETA'."
    rm -rf "$CARPETA"
fi

# REGLA 3: espacio disponible antes de escribir nada.
if command -v df >/dev/null 2>&1; then
    LIBRES_KB="$(df -Pk "$DESTINO" | awk 'NR==2 {print $4}')"

    if [ -z "$LIBRES_KB" ]; then
        echo "respaldo.sh: no se pudo leer el espacio disponible en '$DESTINO'." >&2
        exit 1
    fi

    LIBRES_MB=$((LIBRES_KB / 1024))

    if [ "$LIBRES_MB" -lt "$ESPACIO_MINIMO_MB" ]; then
        echo "respaldo.sh: quedan ${LIBRES_MB} MB en '$DESTINO' y se exigen ${ESPACIO_MINIMO_MB} MB." >&2
        echo "           Un respaldo que falla por disco lleno deja un volcado truncado" >&2
        echo "           que parece bueno. Libere espacio y vuelva a intentarlo." >&2
        exit 1
    fi

    echo "   espacio disponible: ${LIBRES_MB} MB"
fi

# El volcado se escribe de a una vez en un archivo temporal y se mueve al
# final. Un volcado a medias NUNCA queda con el nombre definitivo.
mkdir -p "$CARPETA"
TMP_DUMP="$CARPETA/$NOMBRE.dump.partial"

# -----------------------------------------------------------------------------
# 2. Conexion
# -----------------------------------------------------------------------------
echo "== 2/6. Conectando a la base"

# Si hay RED, el psql corre dentro de un contenedor efimero de esa red, que es
# la unica forma de alcanzar PostgreSQL sin publicar el puerto.
correr() {
    if [ -n "$RED" ]; then
        docker run --rm --network "$RED" \
            -e PGPASSWORD="$DB_PASSWORD" \
            postgres:17-alpine "$@"
    else
        PGPASSWORD="$DB_PASSWORD" "$@"
    fi
}

: "${DB_HOST:?falta DB_HOST}"
: "${DB_PORT:?falta DB_PORT}"
: "${DB_DATABASE:?falta DB_DATABASE}"
: "${DB_USERNAME:?falta DB_USERNAME}"
: "${DB_PASSWORD:?falta DB_PASSWORD}"

# -----------------------------------------------------------------------------
# 3. Volcado
# -----------------------------------------------------------------------------
echo "== 3/6. Volcando la base"

# `--format=custom` deja el volcado comprimido y con una tabla de indices que
# permite restaurarlo con `pg_restore` en paralelo.
correr pg_dump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --format=custom \
    --compress=6 \
    --no-owner \
    --no-privileges \
    --file="$TMP_DUMP"

if [ ! -s "$TMP_DUMP" ]; then
    echo "respaldo.sh: el volcado salio vacio. No hay nada que respaldar." >&2
    rm -f "$TMP_DUMP"
    exit 1
fi

mv "$TMP_DUMP" "$CARPETA/$NOMBRE.dump"

# -----------------------------------------------------------------------------
# 4. Manifiesto con el SHA-256 de cada archivo
# -----------------------------------------------------------------------------
echo "== 4/6. Escribiendo el manifiesto"

MANIFIESTO="$CARPETA/manifiesto.txt"

VOLCADO_BYTES="$(wc -c < "$CARPETA/$NOMBRE.dump" | tr -d ' ')"

{
    echo "# Manifiesto de respaldo de OpticaHefesto"
    echo "marca=$MARCA"
    echo "generado=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "servidor=$(hostname)"
    echo "base=$DB_DATABASE"
    echo "usuario=$DB_USERNAME"
    echo "formato=pg_dump custom"
    echo "bytes=$VOLCADO_BYTES"
} > "$MANIFIESTO"

# El hash va de ultimo, sobre el archivo ya terminado. Es lo que permite
# verificar que lo guardado es lo mismo que se respaldo.
if command -v sha256sum >/dev/null 2>&1; then
    (cd "$CARPETA" && sha256sum "$NOMBRE.dump" >> "$MANIFIESTO")
elif command -v shasum >/dev/null 2>&1; then
    (cd "$CARPETA" && shasum -a 256 "$NOMBRE.dump" >> "$MANIFIESTO")
else
    echo "respaldo.sh: no hay sha256sum ni shasum. NO se puede escribir el manifiesto" >&2
    echo "           y un respaldo sin manifiesto no es restaurable con seguridad." >&2
    rm -rf "$CARPETA"
    exit 1
fi

# -----------------------------------------------------------------------------
# 5. Verificacion: el hash recien escrito tiene que coincidir
# -----------------------------------------------------------------------------
echo "== 5/6. Verificando el respaldo"

ESPERADO="$(awk '{print $1}' "$MANIFIESTO" | tail -1)"
OBTENIDO="$(cd "$CARPETA" && sha256sum "$NOMBRE.dump" 2>/dev/null | awk '{print $1}')"

if [ -z "$ESPERADO" ] || [ "$ESPERADO" != "$OBTENIDO" ]; then
    echo "respaldo.sh: el hash del volcado no coincide. El respaldo esta danado." >&2
    rm -rf "$CARPETA"
    exit 1
fi

echo "   SHA-256 verificado: $OBTENIDO"

# -----------------------------------------------------------------------------
# 6. Marca de respaldo completo. AL FINAL, Y SOLO AL FINAL.
# -----------------------------------------------------------------------------
echo "== 6/6. Marcando el respaldo como completo"

cat > "$CARPETA/COMPLETO" <<FIN
Respaldo completo de OpticaHefesto.
Marca: $MARCA
Terminado: $(date -u '+%Y-%m-%dT%H:%M:%SZ')

La restauracion comprueba la existencia de este archivo antes de tocar nada.
Si no esta, el respaldo esta a medias y no se debe restaurar.
FIN

echo ""
echo "Respaldo completo: $CARPETA"
echo "Volcado: $VOLCADO_BYTES bytes"
echo "SHA-256: $OBTENIDO"