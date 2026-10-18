# Multitenancy y roles

## El aislamiento

Cada fila de datos pertenece a una óptica: la columna `tenant_id`. Una persona
que entra en una óptica **no puede leer los datos de otra**, ni por error de
código, ni por un identificador inventado en la URL.

El patrón es el que ya usan el resto de las aplicaciones del estudio:
**columna `tenant_id` más Row Level Security de PostgreSQL**, no un esquema por
óptica. Se reutiliza tal cual; no se inventó un cuarto enfoque.

## Las cuatro piezas

### 1. La función que falla cerrado

`public.app_tenant_id()` lee la variable de sesión `app.tenant_id`.

Si no está definida, **lanza una excepción**. No devuelve `null`, no devuelve
cadena vacía, no devuelve cero filas.

```sql
CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
LANGUAGE plpgsql
STABLE
AS $$
DECLARE
    valor text;
BEGIN
    valor := current_setting('app.tenant_id', true);

    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION USING
            ERRCODE = 'insufficient_privilege',
            MESSAGE = 'No hay contexto de optica en la conexion ...';
    END IF;

    RETURN valor::uuid;
END
$$;
```

Falla de forma ruidosa, para que en el registro quede claro que una operación
se escapó del middleware. Un aislamiento que en silencio devuelve cero filas es
un aislamiento que nadie depura.

### 2. Las políticas

Cada tabla de negocio lleva:

```sql
CREATE POLICY politica_aislamiento_<tabla> ON public.<tabla>
    FOR ALL
    TO PUBLIC
    USING      (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

`USING` gobierna lo que se **lee**, `WITH CHECK` lo que se **escribe**.

La segunda es la que evita el error más grave: sin `WITH CHECK`, una persona
podría crear una fila dentro de su propia sesión y ponerle el
`tenant_id` de otra óptica. Nadie la vería en los listados, pero el dato
existiría.

### 3. `FORCE ROW LEVEL SECURITY`

```sql
ALTER TABLE public.<tabla> ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.<tabla> FORCE ROW LEVEL SECURITY;
```

`ENABLE` activa la política. `FORCE` hace que **aplique también al dueño de la
tabla**.

Sin `FORCE`, el dueño la salta. Y en desarrollo el rol de la aplicación suele
ser el dueño, o sea que sin `FORCE` el aislamiento sería decorativo justo donde
se prueba: pasarían todas las pruebas y fallaría en producción, que es el peor
lugar para descubrirlo.

### 4. Contexto de transacción

```php
DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $opticaId]);
```

El tercer parámetro, `true`, es lo que hace que el alcance sea de **transacción**.
Al confirmar o revertir, PostgreSQL descarta el valor solo.

Con un `SET` normal, que es de alcance de sesión, el valor sobreviviría a la
transacción. Y en el **worker de colas**, que reutiliza la misma conexión para
trabajos de ópticas distintas, una tarea leería graduaciones de otra óptica.

Esa es exactamente la razón por la que
`App\Jobs\RevisarVencimientosDeRecetas` abre su propio contexto con
`ejecutarAislado()` en vez de confiar en que alguien lo hizo antes.

## Requisito de despliegue

**El rol que usa la aplicación no puede ser superusuario ni tener `BYPASSRLS`.**

Con superusuario, PostgreSQL ignora las políticas por completo. Todo lo de
arriba parecería funcionar y el aislamiento no existiría.

Lo comprueba:

```
php artisan optica:verificar-aislamiento
```

El comando revisa tres cosas, en este orden:

1. **Que el rol conectado no sea superusuario ni tenga `BYPASSRLS`.** Va primero,
   y consulta `pg_roles` directamente por `current_user`. A propósito: no depende
   de que la base tenga datos. Contra una base recién creada y vacía, una
   comprobación indirecta —leer tablas y ver si se filtran— daría verde sin
   comprobar nada, porque no hay filas que se puedan filtrar. Los atributos del
   rol se leen del catálogo, así que la comprobación vale igual con la base vacía.
2. Que las tablas tengan Row Level Security **forzado**.
3. Que una óptica no vea los datos de otra.

La segunda y la tercera escriben filas de prueba y las borran al terminar. No
debe correrse contra una base con pacientes reales en horario de atención.

**Nada de esto se ha ejecutado.** En el equipo donde se escribió este comando no
había PostgreSQL ni Docker, y no hay `php -l` para comprobar la sintaxis. El
código se revisó a mano y no se declara probado. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

## La tabla `opticas` no lleva RLS

Y la vista `vw_opticas_publicas` es su compensación.

Ya se explicó en [02-MODELO-DE-DATOS.md](02-MODELO-DE-DATOS.md). Lo que importa
aquí es que **la vista se usa de verdad**:

```
  GET /v1/acceso/opticas
        |
        v
  Optica::publicas()
        |
        v
  SELECT id, slug, nombre FROM vw_opticas_publicas
        |
        v
  <select> en la pantalla de entrada
```

En aplicaciones anteriores del estudio se creó la vista y nunca se consultó,
mientras la documentación afirmaba que sí. Eso es prometer una protección que no
existe. Acá hay una prueba que falla si la vista expone más de tres columnas, y
que comprueba que `Optica::publicas()` la lee.

## Los tres roles

| Rol | Pacientes | Recetas y graduaciones | Pedidos | Entregas y ajustes | Catálogo y cuentas |
|---|---|---|---|---|---|
| `mostrador` | sí | **no** | sí | sí | no |
| `optometrista` | sí | sí | sí | sí | no |
| `administrador` | sí | **no** | lectura | no | sí |

### La graduación es el dato diferente

Es información de salud: dice cómo ve el paciente. Solo el optometrista la lee.

Ni el mostrador, que atiende al cliente y ve su ficha. Ni la administración, que
maneja el catálogo y las cuentas. **Que una persona maneje la caja no la
convierte en alguien que deba leer graduaciones.**

### El rol es la semilla, la capacidad es el permiso

`App\Services\Permisos\CapacidadesDeUsuario` calcula lo que cada rol puede hacer.
El rol solo no alcanza, porque no todos los roles hacen lo mismo con los mismos
datos.

### Las capacidades no son seguridad

La interfaz usa las capacidades para **esconder botones**. Es una cortesía: si
alguien se salta el botón, la API sigue devolviendo 404 y no entrega el dato.

## El rol se resuelve en cada ingreso

En `App\Services\Auth\ResolutorDeSesion`, el rol se deduce de los **equipos de
Authentik** a los que pertenece la persona, no de una columna.

Si a alguien le quitan el equipo de optometristas en Authentik, en esta
aplicación pierde el acceso a las graduaciones **en la sesión siguiente**. La
alternativa, guardar el rol para siempre en la base, deja ventanas de acceso que
nadie cerraba.

### Tres preguntas, en orden

1. **¿De qué óptica es?** Del equipo `OpticaHefesto:<correlacion>`, donde la
   correlación se busca en `opticas.correlacion_identidad`. Sin equipo de
   óptica no hay ingreso.
2. **¿Qué rol tiene?** De los equipos de rol, con el mapa de
   `config/authentik.php`. Gana el primero que coincide, y el mapa **arranca
   vacío**: mientras no se llene, nadie entra.
3. **¿Existe la cuenta?** Si existe se actualiza el rol con el que dice
   Authentik. Si no, se crea.

### No se crean cuentas a voluntad

Sin equipo de óptica, no hay ingreso. Y no se crea un administrador: esa cuenta
la deja el despliegue. Ver
[04-INSTALACION.md](04-INSTALACION.md).

## Un rechazo es 404, no 403

Cuando una policy rechaza, la respuesta es **404, no 403**.

En un sistema de inventario la diferencia es poco. Acá es más fuerte: la
graduación de una receta es información de salud, y un `403` le confirmaría a
quien pregunta que **el paciente existe** en otra óptica. Con `404` no se
confirma nada.

Está en `bootstrap/app.php`, que traduce la excepción de autorización a `404`
para todas las rutas de la API.

## La bitácora también está protegida

`accesos_recetas` lleva `tenant_id` y Row Level Security como cualquier tabla de
negocio.

Solo la administración puede leerla, y solo ve quién, cuándo, sobre qué recurso
y si se le permitió. **Ningún valor de graduación.** Ver
[07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

## Doble capa en las rutas de receta

Las rutas de receta y graduación llevan el middleware `receta.auditada` **y** la
policy de Laravel.

Son dos capas a propósito: si alguien agrega una ruta nueva y olvida una, la
otra sigue. El middleware además registra el intento en la bitácora, tanto si se
permitió como si se denegó.

El nombre de la ruta lleva el sufijo `.auditada` a propósito: si alguien copia
una de esas rutas sin el sufijo, deja de auditarse, y el nombre lo recuerda.

## Cómo comprobar el aislamiento

```
php artisan optica:verificar-aislamiento
```

Y la suite de pruebas:

```
php artisan test --testsuite=Aislamiento
```

Las dos hacen lo mismo en lugares distintos: el comando desde el servidor, la
suite en el desarrollo. Ver [10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).