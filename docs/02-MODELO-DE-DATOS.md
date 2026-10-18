# Modelo de datos

Quince tablas. Doce con `tenant_id` y con Row Level Security; dos sin `tenant_id`
(`opticas` y `sessions`) por razones que se explican abajo.

```
  opticas                    (tenant, sin RLS)
     |
     +-- usuarios
     +-- pacientes
     |     +-- recetas
     |     |     +-- graduaciones        (2 ojos x 2 meridianos)
     |     +-- pedidos_laboratorio
     |           +-- pedido_items       (1 por ojo, con foto de la graduacion)
     |           +-- controles_calidad   (1 por pedido)
     |           +-- entregas           (1 por pedido)
     |                 +-- ajustes
     +-- armazones
     +-- lentes
     +-- accesos_recetas                 (bitacora)
```

## Las dos tablas sin `tenant_id`

### `opticas` es la empresa

Es el tenant. Se consulta en el **selector de ingreso**, antes de que exista
sesión y por lo tanto antes de que exista `app.tenant_id`.

Si llevara Row Level Security, la función `app_tenant_id()` lanzaría excepción
y el listado de ópticas para entrar devolvería un error siempre.

Como compensación existe la vista `vw_opticas_publicas`, que expone **tres
columnas** y solo ópticas activas. La usa `Optica::publicas()`, que es lo que
consume `GET /v1/acceso/opticas`, el endpoint que llena el selector de la
pantalla de entrada.

Lo que la vista **no** expone, y por qué:

| Columna | Por qué no |
|---|---|
| `correlacion_identidad` | Es la llave que liga a una persona con una óptica en Authentik. Publicarla sin autenticar sería entregarle a cualquiera el mapa de las empresas del sistema. |
| `zona_horaria` | No hace falta para elegir dónde entrar. |

### `sessions`

La sesión se crea **antes** de saber en qué óptica se entra: el ingreso empieza
en Authentik, sin sesión de la aplicación. Con Row Level Security, la escritura
de la sesión fallaría en el primer ingreso de la historia.

El contenido de la sesión tampoco tiene datos de salud: guarda el identificador
de usuario, el nombre de la conexión y un token CSRF.

## La graduación: por qué una fila por ojo y por meridiano

Una receta completa son **cuatro filas** de `graduaciones`: dos ojos por dos
meridianos (lejos y cerca).

La alternativa tentadora es una fila por ojo con columnas `esfera_lejos`,
`cilindro_lejos`, `eje_lejos`, `esfera_cerca` y así. Se ve más cómoda y es una
mala idea por dos motivos:

1. **Repite el nombre del meridiano en cada columna**, y a la mitad de las
   columnas se les olvida el sufijo.
2. **Agregar un meridiano después** sería agregar tres columnas y otra
   migración, sobre una tabla que ya tiene datos de pacientes.

Con una fila por combinación, el meridiano es un dato y no parte del nombre.
Agregar uno es agregar un valor a `App\Enums\Meridiano`.

### Las restricciones que impone la base

| Restricción | Qué impide |
|---|---|
| `eje >= 0 and eje <= 180` | Un eje fuera del rango en que se puede escribir un ángulo. |
| `cilindro <> 0 or eje = 0` | Un eje distinto de cero con un cilindro cero, que no significa nada y se imprime en la receta como si significara algo. |
| `adiccion is null or adiccion > 0` | Una adición negativa, que haría ver peor de cerca que de lejos. |
| `meridiano = 'cerca' and adiccion is not null, o meridiano = 'lejos' and adiccion is null` | La adición en el meridiano equivocado, donde el mismo número significaría dos cosas distintas. |
| `ojo in ('derecho', 'izquierdo')` | Un ojo escrito abreviado como `OD` en vez de `derecho`, que rompería las consultas. |
| `unique (receta_id, ojo, meridiano)` | Un doble envío que cree dos filas de lejos para el mismo ojo con valores distintos, sin forma de saber cuál se leyó en el mostrador. |

**Estas restricciones no dependen de que la aplicación se porte bien.** Un
`INSERT` escrito a mano, o un error en el código, no puede dejarlas pasar.

## Las recetas

### Una receta es un documento

Tiene número, fecha de emisión y estado. No se edita una vez emitida: se anula
o se reemplaza. Eso es lo que permite decir, meses después, qué se despachó y
sobre qué.

### El estado de vigencia NO es una columna

`vigente` y `vencida` **se calculan** cada vez que se preguntan, con
`App\Services\Recetas\CalculadoraDeVencimiento`.

Guardar eso como columna sería duplicar un dato que se puede calcular y que se
desactualizaría solo: dejarías de ver como vencida una receta que venció
mientras el sistema estaba apagado.

La columna `estado` es para lo que la persona **decide**: si la receta se emite,
se anula o queda reemplazada.

### `fecha_vencimiento` nace en `null`

Es la columna más importante de la tabla, y nace vacía a propósito.

Una receta óptica tiene plazo de vigencia, y ese plazo depende de la edad del
paciente. El plazo por franja etaria es **norma sanitaria chilena**, y en la
máquina donde se escribió esta aplicación no se pudo verificar contra la norma
vigente cuál es ese plazo.

Mientras sea `null`:

- el sistema **no vence recetas por su cuenta**,
- la interfaz avisa que el plazo está pendiente de verificación,
- se puede emitir y despachar igual, dejando constancia en
  `motivo_sin_vencimiento` de por qué se emitió sin plazo.

Ver [06-PRIMERA-RECETA.md](06-PRIMERA-RECETA.md).

### `motivo_sin_vencimiento`

Texto libre donde queda escrito por qué se emitió una receta sin plazo. Sin esta
columna, una receta sin fecha sería indistinguible de una receta a la que se le
olvidó la fecha.

## Los pedidos a laboratorio

### La graduación se copia, no se referencia

`pedido_items.graduacion_pedida` guarda una **copia** en JSON de la graduación,
no un identificador a `graduaciones`.

La razón es que un pedido es un documento. Si se guardara solo el
`receta_id`, bastaría con que alguien corrigiera la receta después de enviado el
pedido para que el sistema mostrara una graduación que nunca se fabricó, y el
control de calidad compararía lo recibido contra algo que no es lo que se pidió.

La graduación canónica sigue viviendo en `graduaciones`. En el pedido queda solo
la foto de lo que salió de la óptica.

### `origen_fecha_prometida`

Deja escrito de dónde salió la fecha prometida: `configuracion`, `manual` o
`pendiente`. Para que nadie asuma que el sistema la inventó si en realidad la
puso una persona.

### `compatibilidad_resultado` y `compatibilidad_desglose`

El veredicto del servicio de compatibilidad **con el cálculo completo**, tal
como estaba en el momento de crear el pedido.

Se guardan incluso cuando el resultado es `no_verificable`, porque la parte
geométrica del cálculo sí se hizo y sirve para saber qué falta.

## El control de calidad

Un control por pedido: hay un índice único en `pedido_laboratorio_id`.

Si un control da `no_conforme`, la corrección es un pedido **nuevo**, no un
segundo control sobre el mismo pedido: el laboratorio mecaniza de nuevo y vuelve
a entrar por el mismo camino. Así queda un veredicto por pedido y nadie tiene que
decidir cuál de los dos vale.

## Las entregas y los ajustes

Una entrega por pedido. Si el cliente devuelve los lentes, se cambia el estado
de **esa** entrega, no se crea otra.

El paso de `registrada` a `entregada` es el que abre la cola de ajustes. Un lente
recién entregado casi nunca está bien ajustado la primera vez, y un armazón que
aprieta no es un defecto de fabricación: es una entrega que no terminó.

### `ajustes` no tiene columna de garantía

Los días de garantía sugeridos están en
`config('optica.entrega.dias_de_garantia_sugeridos')` y **nacen en `null`**
porque son una decisión comercial de la óptica y no un plazo legal verificable.

Poner un campo obligatorio con un valor inventado sería exactamente el error que
este producto evita. Ver [07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

## La bitácora

`accesos_recetas` registra **tanto lo permitido como lo denegado**. Un intento
denegado es tan informativo como uno permitido: es la señal de que alguien está
probando el sistema con el rol equivocado.

`usuario_id` es **nullable**, y eso es deliberado: el intento denegado más
importante es el de quien no tiene cuenta o la tiene desactivada. Si la columna
fuera obligatoria, ese intento no se podría registrar y el hueco sería justo el
que interesa.

### Qué NO se guarda en la bitácora

Ningún valor de graduación. Solo el identificador del recurso, quién lo pidió,
cuándo y si se le permitió. La bitácora no puede convertirse en una segunda
copia de la información de salud sin protección.

## Resumen de las quince tablas

| Tabla | `tenant_id` | RLS | Nota |
|---|---|---|---|
| `opticas` | no | no | Es el tenant. Se consulta en el ingreso. |
| `usuarios` | sí | sí | Sin contraseñas. |
| `pacientes` | sí | sí | No tiene graduaciones. |
| `recetas` | sí | sí | `fecha_vencimiento` nace en `null`. |
| `graduaciones` | sí | sí | 4 filas por receta. |
| `armazones` | sí | sí | Medidas de caja obligatorias. |
| `lentes` | sí | sí | Índice y diámetro nacen en `null`. |
| `pedidos_laboratorio` | sí | sí | Guarda el veredicto de compatibilidad. |
| `pedido_items` | sí | sí | 1 por ojo, con foto de la graduación. |
| `controles_calidad` | sí | sí | 1 por pedido. |
| `entregas` | sí | sí | 1 por pedido. |
| `ajustes` | sí | sí | Sin días de garantía. |
| `accesos_recetas` | sí | sí | Registro permitido y denegado. |
| `sessions` | no | no | Tiene `optica_id` como dato de apoyo. |