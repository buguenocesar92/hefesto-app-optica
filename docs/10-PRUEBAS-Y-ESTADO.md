# Pruebas y estado

## Lo primero: nada se ha ejecutado

En la máquina donde se escribió esta aplicación **no hay PHP, ni Composer, ni
Node, ni Docker, ni PostgreSQL**.

Cero migraciones aplicadas.
Cero pruebas corridas.
Cero imágenes construidas.
Cero contenedores levantados.
Cero peticiones a la API.
Cero pacientes reales.
Cero recetas cargadas.

Todo lo que hay en `api/tests/` es **código escrito y nunca ejecutado**.

Este documento existe para que nadie lo lea como si estuviera probado. En
aplicaciones anteriores del estudio se presentó código sin ejecutar como
"probado", y el costo lo pagó quien lo desplegó.

## Números

| Concepto | Cantidad |
|---|---|
| Archivos escritos | 175 |
| | 124 en `api/`, 26 en `web/`, 12 en `deploy/`, 11 en `docs/`, 2 en la raíz |
| Migraciones | 16 |
| Tablas | 15 |
| Modelos | 13 |
| Policies | 10 |
| Endpoints | 49 |
| Comandos de consola | 4 |
| Trabajos en cola | 1 |
| Archivos de prueba | 6 |
| Casos de prueba escritos | 54 |
| Pruebas **ejecutadas** | **0** |

## Lo que sí se ejecutó

Solamente dos cosas, y ninguna toca el código de la aplicación:

| Herramienta | Qué comprobó | Resultado |
|---|---|---|
| `tools/acentuar.py` | Acentuación en los `.md` | 0 pendientes tras la corrección |
| `tools/validar_apps.py` | Caracteres, secretos, RLS, YAML, shell, puertos | 0 errores, 0 avisos |
| `bash -n` | Sintaxis de los tres `.sh` | Los tres válidos |
| `python` | Lectura del árbol de archivos y conteos | Correcto |

**Eso es todo.** Nada de la aplicación se ejecutó.

## Por qué no se pudo ejecutar

El entorno de escritura no tenía las herramientas. No es una omisión: instalar
un lenguaje de programación y un motor de base de datos sin que lo pida quien
encarga el trabajo no corresponde.

Lo que **sí** se revisó a mano, y conviene saber:

- que cada columna usada por un modelo existe en su migración;
- que las rutas apuntan a métodos que existen en los controladores;
- que las rutas citadas en la documentación existen en `routes/web.php`;
- que las tablas de la lista de RLS coinciden con las migraciones;
- que los nombres de tabla, columna y enum coinciden entre migraciones,
  modelos, factories y controladores.

Esa revisión **no sustituye** a correr las pruebas. Un error de tipeo en un
nombre de columna se ve leyendo y se detecta corriendo.

## Las pruebas que existen

### `tests/Unit/VerificadorDeCompatibilidadTest.php`

La más importante del proyecto. Comprueba que la verificación de compatibilidad:

- con el margen sin configurar devuelve `no_verificable` y **nunca** "sí";
- con la pastilla exacta da compatible sin holgura;
- con la pastilla grande da compatible con holgura;
- con la pastilla chica da incompatible;
- **el alto puede mandar sobre el ancho** (el error clásico);
- solo `compatible` autoriza despachar;
- el desglose trae todos los números del cálculo.

Es una prueba ** pura**: no toca la base, y por eso puede correr sin PostgreSQL.

### `tests/Feature/Aislamiento/AislamientoEntreOpticasTest.php`

Comprueba que el aislamiento entre ópticas funciona a nivel de base:

- todas las tablas de negocio tienen Row Level Security **forzado**;
- `opticas` **no** tiene RLS (y por eso existe la vista);
- una óptica no ve los pacientes de otra, ni filtrando ni sin filtrar;
- no se puede insertar una fila identificada como de otra óptica;
- **sin contexto, la consulta falla** en vez de devolver filas;
- el contexto desaparece al terminar la transacción.

### `tests/Feature/Aislamiento/VistaOpticasPublicasTest.php`

Comprueba que `vw_opticas_publicas` existe, expone **exactamente** tres
columnas, no deja pasar `correlacion_identidad`, filtra las ópticas inactivas y
que `Optica::publicas()` la lee de verdad.

Existe porque en aplicaciones anteriores del estudio se creó la vista y nunca
se consultó, mientras la documentación afirmaba que sí.

### `tests/Feature/Dominio/VencimientoDeRecetaTest.php`

Comprueba que el sistema **no inventa plazos**:

- una instalación nueva no declara ningún plazo;
- una receta sin fecha devuelve `sin_plazo` con explicación;
- la edad y la franja se calculan aunque no haya plazo;
- sin fecha de nacimiento no hay franja;
- `sin_plazo` **sí** permite despachar, y es una decisión explícita;
- con fecha cargada el sistema solo informa;
- una receta vencida no permite despachar;
- la edad se cuenta por años cumplidos, no dividiendo días;
- cuando se configura un plazo, el mismo código propone la fecha.

### `tests/Feature/Dominio/GraduacionPorOjoYMeridianoTest.php`

Comprueba la estructura de la graduación, y sobre todo que **la base la
impone**:

- una receta completa son cuatro filas;
- los dos ojos pueden tener valores distintos;
- la adición solo existe en el meridiano de cerca;
- no se puede repetir ojo y meridiano;
- **no se puede** cargar la adición en el meridiano de lejos;
- **no se puede** dejar la fila de cerca sin adición;
- **no se puede** tener un eje distinto de cero con un cilindro cero;
- **no se puede** tener un eje fuera de rango;
- **no se puede** usar un ojo desconocido;
- con astigmatismo el eje se guarda;
- las filas se pueden volcar a la foto del pedido.

### `tests/Feature/Permisos/CapacidadesPorRolTest.php`

Comprueba que la graduación la ve solo el optometrista: ni el mostrador ni la
administración. Y que una cuenta inactiva no tiene ninguna capacidad.

## Cómo correrlas

### Con Docker

```bash
cd deploy
docker compose -f docker-compose.dev.yml up -d pgsql

docker compose -f docker-compose.dev.yml run --rm api php artisan test
docker compose -f docker-compose.dev.yml run --rm api \
    php artisan test --testsuite=Aislamiento
docker compose -f docker-compose.dev.yml run --rm api \
    php artisan test --testsuite=Permisos
docker compose -f docker-compose.dev.yml run --rm api \
    php artisan test --testsuite=Dominio
```

### Por qué PostgreSQL y no SQLite

`phpunit.xml` fija `DB_CONNECTION=pgsql` a propósito.

El aislamiento entre ópticas está implementado con Row Level Security, que es
una función de PostgreSQL. **En SQLite esas pruebas pasarían sin comprobar
nada**: un sistema sin aislamiento alguno también daría verde.

Es un caso en que "las pruebas pasan" es peor que "no hay pruebas", porque da
una confianza que no corresponde.

### La base de pruebas

```
DB_DATABASE=optica_test
```

Necesita existir y estar vacía. Las pruebas usan `RefreshDatabase`.

## Las variables de plazo, forzadas a vacío en las pruebas

`phpunit.xml` fuerza a vacío:

```
OPTICA_VIGENCIA_HASTA_12_ANIOS=
OPTICA_VIGENCIA_13_A_17_ANIOS=
OPTICA_VIGENCIA_18_A_59_ANIOS=
OPTICA_VIGENCIA_DESDE_60_ANIOS=
```

Si el `.env` de la máquina trajera los plazos puestos, una prueba de vencimiento
pasaría por la puerta de atrás, y comprobaría un valor inventado en lugar del
comportamiento real.

## Lo que no está probado, porque no existe

| Cosa | Por qué |
|---|---|
| Facturación | No existe en esta versión. |
| Inventario con stock | No existe. |
| Proveedores | No existe. |
| Agenda de atenciones | No existe. |
| Carga de documentos | No existe, y `php.ini` la desactiva. |
| Correos | No hay nenhum envío de correo. |
| Integración con laboratorio | El laboratorio es un texto. No hay conexión con ninguno. |

## Lo que queda pendiente de verificar, y por qué

Esto no son errores: son decisiones que requieren información que no se tenía
al escribir el código.

| Qué | Por qué no se resolvió |
|---|---|
| Plazo de vigencia por franja etaria | Es norma sanitaria chilena. Verificarlo exige la norma vigente y criterio legal. |
| Los límites de las franjas por edad | Son un molde para cargar el dato, no una clasificación normativa. |
| Margen efectivo del lente | Depende del tipo de lente y de las fichas del fabricante. |
| Índice de refracción del catálogo | Cambia por modelo y por mercado. |
| Diámetro de corte por lente | Depende de la pastilla disponible en cada laboratorio. |
| Tolerancia del control de calidad | Es una decisión clínica del optometrista. |
| Días de conservación | Depende del contrato con el paciente y de la normativa sectorial. |
| Días de garantía sugeridos | Es una decisión comercial, no un plazo legal. |
| Precio de los productos | Esta versión no hace facturación. |

**Cada uno de estos aparece en pantalla como pendiente.** La aplicación funciona
con los huecos a la vista.

## Lo que se revisó a mano, y no sustituye a las pruebas

- Que cada modelo use solo columnas que las migraciones crean.
- Que las rutas apunten a métodos existentes.
- Que las tablas de la lista de RLS coincidan con las migraciones.
- Que nombres de tabla, columna y enum coincidan entre capas.

Revisar no es probar. Un error de tipeo se ve leyendo, y se detecta corriendo.

## Honestidad del código

- No hay `TODO` ocultos ni funciones vacías que devuelvan un valor inventado.
- Donde no se sabe, el código **lanza una excepción o devuelve un estado que lo
  dice**: `no_verificable`, `sin_plazo`, `null`.
- Las aleatoriedades no se usan en el código: todo lo aleatorio está en los
  factories y en los identificadores.
- La comprobación de que la vista se usa de verdad está en una prueba, no solo
  en la documentación.

## Cómo seguir

1. Instalar el entorno. Ver `docs/04-INSTALACION.md`.
2. Correr las pruebas. **Empezar por la suite `Aislamiento`.**
3. Correr `php artisan optica:verificar-aislamiento`. Es el paso que revisa el
   rol de la base: que sea superusuario, o que tenga `BYPASSRLS`, significa que
   el aislamiento entre ópticas no existe aunque todo lo demás parezca funcionar.
   La revisión va primera y lee `pg_roles`, así que no necesita que haya datos.
   **No se ha ejecutado en ninguna base real**: en el equipo donde se escribió no
   había PostgreSQL ni Docker.
4. Levantar la aplicación y hacer el recorrido de `docs/06-PRIMERA-RECETA.md`.
5. Recién ahí, verificar los plazos pendientes con la norma vigente y cargar
   los valores.
6. Recién ahora, atender pacientes reales.