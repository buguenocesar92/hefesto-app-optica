# Configuración

Toda la configuración de negocio está en `api/config/optica.php`, y lo que no se
pudo verificar está en `null`.

## La regla

> Si un valor no se pudo verificar, se deja en `null` y se marca como
> pendiente. Nunca se escribe un número plausible.

Un plazo de vigencia inventado en una receta es **peor** que una receta sin
plazo: el primero hace creer que hay respaldo normativo y el segundo deja claro
que falta decidirlo.

## El mapa de lo pendiente

Esta es la lista completa de lo que nace vacío.

| Variable | Qué decide | Por qué está en `null` |
|---|---|---|
| `OPTICA_VIGENCIA_HASTA_12_ANIOS` | Vigencia para hasta 12 años | Norma sanitaria chilena. No verificado. |
| `OPTICA_VIGENCIA_13_A_17_ANIOS` | Vigencia para 13 a 17 años | Ídem. |
| `OPTICA_VIGENCIA_18_A_59_ANIOS` | Vigencia para 18 a 59 años | Ídem. |
| `OPTICA_VIGENCIA_DESDE_60_ANIOS` | Vigencia para 60 y más | Ídem. |
| `OPTICA_DIAMETRO_EFECTIVO_MINIMO_MM` | Margen del lente para la verificación de compatibilidad | Depende del tipo de lente y del fabricante. No verificado. |
| `OPTICA_DIAS_LABORATORIO` | Días prometidos por omisión al crear un pedido | Es comercial y cambia por laboratorio y por temporada. |
| `OPTICA_TOLERANCIA_DIOPTRIAS` | Tolerancia del control de calidad | Es una decisión clínica del optometrista. |
| `OPTICA_TOLERANCIA_EJE_GRADOS` | Ídem para el eje | Ídem. |
| `OPTICA_RETENCION_RECETAS_DIAS` | Conservación de recetas | Depende del contrato con el paciente y de la normativa sectorial. |
| `OPTICA_RETENCION_ACCESOS_RECETAS_DIAS` | Conservación de la bitácora | Ídem. |
| `OPTICA_DIAS_DE_GARANTIA_SUGERIDOS` | Días sugeridos para ordenar la cola de ajustes | Decisión comercial, no un plazo legal. |

## La vigencia de la receta

### Por qué depende de la edad

En la gente joven la graduación cambia más rápido. Por eso el plazo que le vale
a un adulto no le sirve a un niño.

El plazo por franja etaria es **norma sanitaria chilena**. En la máquina donde
se escribió esta aplicación no se pudo verificar contra la norma vigente cuál
es ese plazo ni qué franjas usa.

### Qué hace el sistema mientras tanto

| Situación | Qué devuelve | ¿Permite despachar? |
|---|---|---|
| Franjas todas en `null`, receta sin fecha | `sin_plazo` | **Sí**, con aviso. |
| Franja con plazo, receta sin fecha | `sin_plazo`, con el plazo propuesto | **Sí**, con aviso. |
| Receta con fecha futura | `vigente` o `por_vencer` | Sí. |
| Receta con fecha pasada | `vencida` | **No**. |
| Receta anulada o reemplazada | `anulada` | **No**. |

### Por qué `sin_plazo` sí permite despachar

Es una decisión de producto, y hay que tomarla a la vista.

Bloquear el despacho mientras el plazo legal no está verificado dejaría a la
óptica sin poder atender a nadie hasta que alguien definiera una norma que no
hemos verificado. El sistema avisa y deja decidir a la persona, que es quien
responde por la decisión.

### Los límites de las franjas también están pendientes

Las franjas por defecto en `config/optica.php` (0-12, 13-17, 18-59, 60+) son un
molde razonable para cargar el dato, **no una clasificación normativa**. Quien
defina el plazo tiene que decidir también dónde cortan.

Cargar el plazo no requiere tocar código:

```
OPTICA_VIGENCIA_18_A_59_ANIOS=730
```

y el mismo código empieza a proponer fechas, sin cambiar una línea.

## La compatibilidad armazón y lente

### El cálculo

La pastilla del lente es un círculo. La caja del armazón es un rectángulo. Para
que el círculo cubra el rectángulo con una holgura `m` por lado:

```
  ancho_de_caja = ancho_varilla + puente
  alto_de_caja  = alto

  requerido_horizontal = ancho_de_caja + 2 * m
  requerido_vertical   = alto_de_caja  + 2 * m
  requerido            = el mayor de los dos
```

Se toman los dos ejes porque la caja no es cuadrada. Quedarse solo con el ancho
deja pasar combinaciones imposibles.

### Lo que no se sabe

El valor `m`, el margen efectivo mínimo. Depende del tipo de lente y de la
distancia interpupilar del paciente, y varía entre fabricantes. **No se pudo
verificar** contra la norma ni contra las fichas técnicas de los fabricantes.

Mientras `m` sea `null`, el servicio devuelve `no_verificable` y **nunca** "sí"
o "no". Ver `docs/06-PRIMERA-RECETA.md`.

## El pedido a laboratorio

La fecha prometida **nace en `null`**.

Es un valor comercial: lo negocia la óptica con su laboratorio, cambia por
material y por temporada. El software no lo inventa.

`OPTICA_DIAS_LABORATORIO` es lo único que puede usarse como propuesta al crear
un pedido, y también nace vacío. Si tampoco hay ese, el pedido se guarda con la
fecha prometida vacía.

`origen_fecha_prometida` deja escrito de dónde salió: `configuracion`, `manual`
o `pendiente`.

## El control de calidad

La tolerancia **nace en `null`** porque es una decisión clínica del
optometrista. Hay tolerancias que el laboratorio considera correctas y que el
profesional no acepta.

Mientras esté vacía:

- el control de calidad **sí** se registra,
- **sí** compara valor por valor y muestra las diferencias,
- **no** emite un veredicto automático de conforme o no conforme.

El veredicto lo pone la persona.

## La conservación

Los días de retención **nacen en `null`**.

Cuánto tiempo se conserva una receta depende del contrato de tratamiento de datos
que la óptica firmó con sus pacientes y de la normativa sectorial que le aplique.
No se pudo verificar el plazo que corresponde, así que no se escribe.

**Con las variables vacías, la aplicación no purga nada por su cuenta.**

Borrar la graduación de un paciente porque pasó un número inventado es peor que
no borrar nunca. Ver `docs/07-SEGURIDAD-Y-LEY-19628.md`.

## El mapa de equipos a roles

`api/config/authentik.php` trae el mapa **vacío**, y a propósito:

```php
'grupos_por_rol' => [
    // 'Administradores de OpticaHefesto' => 'administrador',
    // 'Optometristas de OpticaHefesto' => 'optometrista',
    // 'Mostrador de OpticaHefesto' => 'mostrador',
],
```

Mientras no se llene, no se puede iniciar sesión. La alternativa sería dejar un
rol por omisión, y con eso cualquier persona que consiga autenticarse entra con
ese rol.

No se pasa por entorno: es código, y se cambia revisando. Ver
`docs/04-INSTALACION.md`.

## El resto de la configuración

### `api/.env`

| Variable | Por omisión | Nota |
|---|---|---|
| `APP_TIMEZONE` | `America/Santiago` | La zona se guarda además por óptica. |
| `SESSION_EXPIRACION_MINUTOS` | `120` | En una óptica, una sesión abierta mucho rato es una sesión abierta a las graduaciones. |
| `SESSION_SECURE_COOKIE` | `false` | En producción tiene que ser `true`. |
| `LOG_LEVEL` | `info` | `debug` deja el cuerpo de las peticiones en el registro. |
| `AUTHENTIK_ALGORITMO` | `RS256` | Se comprueba **antes** de verificar la firma. |
| `AUTHENTIK_LEEWAY` | `60` | Margen de reloj, en segundos. |
| `OPTICA_DIAS_DE_AVISO` | `30` | Días antes del vencimiento en que se avisa. |

### `web/.env`

| Variable | Para qué |
|---|---|
| `NUXT_API_BASE` | **Config privada.** Solo la usa el servidor de Nuxt para reenviar a la API. El navegador nunca la ve, y por eso no se expone como `NUXT_PUBLIC_*`. |
| `NUXT_PUBLIC_BASE` | La URL pública, para el navegador. |

## Leer y verificar un valor en `null` desde el código

En PHP:

```php
$margen = config('optica.compatibilidad.diametro_efectivo_minimo_mm');

if ($margen === null) {
    // el sistema no sabe: hay que decirlo, no suponer
}
```

En la interfaz, los tipos de `web/app/tipos/api.ts` declaran esos campos como
`| null` a propósito. Escribirlos como `string` obligaría a la interfaz a
inventar un valor de pantalla.

**Nunca uses `env()` directamente fuera de `config/`.** Después del
`config:cache`, `env()` devuelve `null` y el valor se pierde.