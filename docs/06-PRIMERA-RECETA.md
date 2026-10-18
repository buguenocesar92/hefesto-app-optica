# Primera receta

El recorrido completo, del paciente a la entrega. Es la mejor forma de entender
en qué orden encajan las piezas.

## 0. Antes de empezar: qué está pendiente

Esta aplicación se escribió **sin poder verificar** varios valores que en una
óptica son obligatorios. No están adivinados: están vacíos, marcados, y el
sistema avisa.

| Lo que falta verificar | Consecuencia mientras falta |
|---|---|
| Plazo de vigencia por franja etaria | Las recetas nacen sin fecha de vencimiento. El sistema no vence nada por su cuenta. |
| Margen efectivo del lente | La compatibilidad devuelve `no_verificable`, nunca "sí". |
| Tolerancia del control de calidad | El sistema muestra las diferencias, pero no emite veredicto. |
| Días de conservación | La aplicación no purga nada por su cuenta. |

**La aplicación arranca igual y funciona con los huecos a la vista.** Esa es la
decisión: preferir el hueco al dato inventado.

## 1. El paciente

```
POST /v1/pacientes
```

La ficha es dato de contacto y facturación. **No tiene graduaciones**: esas
viven en `recetas`, para que el acceso a la graduación se pueda proteger y
auditar por separado del acceso a la ficha.

La **fecha de nacimiento** importa más de lo que parece: es lo que decide la
franja de vigencia de la receta. Si falta, el cálculo devuelve "sin plazo" y la
interfaz avisa, en vez de adivinar una edad.

## 2. La receta

```
POST /v1/recetas
```

```
{
  "paciente_id": "...",
  "numero": "R-00001",
  "fecha_emision": "2026-10-18",
  "fecha_vencimiento": null,
  "motivo_sin_vencimiento": "Plazo legal pendiente de verificación."
}
```

### Sobre `fecha_vencimiento`

Si la mandas en `null`, la receta queda sin plazo y **exige** un motivo escrito.
Sin ese par, una receta sin fecha sería indistinguible de una receta a la que
se le olvidó la fecha.

Si la mandas con valor, el sistema la respeta y solo informa. No la recalcula.

### Lo que el sistema devuelve

```json
{
  "estado": "sin_plazo",
  "fecha_vencimiento": null,
  "dias_restantes": null,
  "edad_anios": 40,
  "franja_etaria": "De 18 a 59 anios",
  "plazo_dias": null,
  "pendiente_de_verificacion": true,
  "permite_despachar": true,
  "explicacion": "Esta receta no tiene fecha de vencimiento porque el plazo
                  legal por franja etaria todavia no se ha verificado contra
                  la norma vigente."
}
```

Fíjate en `permite_despachar: true`. Con el plazo sin verificar, el sistema
avisa y deja decidir a la persona. Bloquear el despacho dejaría a la óptica sin
poder atender a nadie.

## 3. La graduación

```
PUT /v1/recetas/{id}/graduaciones
```

**Cuatro filas**: dos ojos por dos meridianos. Siempre las cuatro.

```json
{
  "graduaciones": [
    { "ojo": "derecho",  "meridiano": "lejos", "esfera": -2.25, "cilindro": -0.50, "eje": 180 },
    { "ojo": "derecho",  "meridiano": "cerca", "esfera": -1.25, "cilindro": -0.50, "eje": 180, "adiccion": 1.00 },
    { "ojo": "izquierdo", "meridiano": "lejos", "esfera": -1.75, "cilindro": -0.25, "eje": 170 },
    { "ojo": "izquierdo", "meridiano": "cerca", "esfera": -0.75, "cilindro": -0.25, "eje": 170, "adiccion": 1.00 }
  ]
}
```

Los números de arriba son un ejemplo de formato, **no una graduación real** y no
son un dato normativo.

### Las reglas, y quién las hace cumplir

| Regla | Dónde se comprueba |
|---|---|
| Las cuatro filas tienen que estar | `GuardarGraduacionesRequest` |
| El ojo solo puede ser `derecho` o `izquierdo` | Base: `CHECK` |
| El eje va de 0 a 180 | Base: `CHECK` |
| Con cilindro cero, el eje es cero | Base: `CHECK` |
| La adición va solo en el meridiano de cerca | Base: `CHECK` |
| No se repite ojo y meridiano | Base: índice único |

Las de la base no dependen de que la aplicación se porte bien. Ver
`docs/02-MODELO-DE-DATOS.md`.

### El signo del cilindro

El sistema **no decide la convención de signo**. Se guarda la que use la óptica.
Lo que sí se comprueba es la coherencia interna: un cilindro cero con un eje de
180 no significa nada y no se puede guardar.

## 4. El catálogo

Antes del pedido hacen falta el armazón y el lente.

### El armazón: las medidas de caja

```
POST /v1/armazones
{
  "codigo": "AR-0001",
  "nombre": "Clasico acetato",
  "ancho_varilla_mm": 52.00,
  "puente_mm": 18.00,
  "alto_mm": 40.00
}
```

Las tres medidas son **obligatorias**. Un armazón guardado sin medidas es un
armazón que va a fallar en el taller.

Con esos números, el ancho de caja es `52 + 18 = 70` mm.

### El lente

```
POST /v1/lentes
{
  "codigo": "LE-0001",
  "nombre": "Monofocal resina",
  "material": "Resina",
  "indice_refraccion": null,
  "diametro_corte_mm": 75.00
}
```

`indice_refraccion` y `diametro_corte_mm` nacen en `null` porque los valores los
carga la óptica desde la ficha del fabricante.

## 5. La verificación de compatibilidad

```
POST /v1/compatibilidad
{ "armazon_id": "...", "lente_id": "..." }
```

Es la comprobación que sostiene el producto: si la pastilla del lente no cubre
la caja del armazón más el borde, la hecha no sirve.

### Lo que devuelve con el margen sin configurar

```json
{
  "veredicto": "no_verificable",
  "motivo": "Falta el margen efectivo minimo del lente
             (config optica.compatibilidad.diametro_efectivo_minimo_mm).
             El calculo geometrico del armazon esta hecho, pero sin ese
             margen no se puede afirmar que la pastilla cubra la caja.",
  "datos_faltantes": ["margen_efectivo_minimo_mm"],
  "desglose": {
    "armazon":  { "ancho_varilla_mm": 52, "puente_mm": 18, "alto_mm": 40 },
    "ancho_de_caja_mm": 70,
    "alto_de_caja_mm": 40,
    "margen_efectivo_minimo_mm": null
  }
}
```

**Devuelve el desglose, no un sí o un no.** La parte geométrica está calculada;
lo que falta es el margen.

### Lo que devuelve con el margen configurado

Con `OPTICA_DIAMETRO_EFECTIVO_MINIMO_MM=5`:

```
  ancho_de_caja = 52 + 18 = 70
  alto_de_caja  = 40

  requerido_horizontal = 70 + 2*5 = 80
  requerido_vertical   = 40 + 2*5 = 50
  requerido            = max(80, 50) = 80      <- manda el ancho

  holgura = 75 - 80 = -5   ->  incompatible
```

```json
{
  "veredicto": "incompatible",
  "motivo": "La pastilla del lente es 75.00 mm y el armazon necesita al menos
             80.00 mm. Faltan 5.00 mm de diametro: la hecha no alcanza.",
  "datos_faltantes": [],
  "desglose": {
    "ancho_de_caja_mm": 70,
    "diametro_minimo_requerido_mm": 80,
    "eje_que_manda": "ancho_de_caja",
    "holgura_mm": -5
  }
}
```

### Por qué los dos ejes

Un armazón angosto y alto pide una pastilla distinta a uno ancho y bajo. Quedarse
solo con el ancho deja pasar combinaciones imposibles:

```
  armazon: ancho 40, puente 18, alto 60
  -> ancho_de_caja = 58, alto_de_caja = 60
  -> requerido = max(58+10, 60+10) = 70      <- manda el ALTO

  una pastilla de 69 serviria para el ancho y no para el alto
```

## 6. El pedido al laboratorio

```
POST /v1/pedidos
{
  "codigo": "P-00001",
  "paciente_id": "...",
  "receta_id": "...",
  "armazon_id": "...",
  "lente_id": "...",
  "laboratorio": "Laboratorio del sector",
  "fecha_envio": "2026-10-18",
  "fecha_prometida": null
}
```

Al crearlo, la aplicación:

1. corre el verificador de compatibilidad **en el servidor**,
2. guarda el veredicto con el desglose, aunque sea `no_verificable`,
3. copia la graduación de la receta a cada renglón, uno por ojo.

### La fecha prometida queda vacía

Es un valor comercial. Si mandas `null`, el pedido se guarda con
`origen_fecha_prometida: "pendiente"` y no hay atraso que medir. Un pedido sin
promesa no está atrasado: no hay con qué compararlo.

### Por qué se copia la graduación

Porque un pedido es un documento. Si se guardara solo el `receta_id`, bastaría
con que alguien corrigiera la receta después de enviado el pedido para que el
control de calidad comparara lo recibido contra algo que nunca se fabricó.

## 7. La recepción

```
POST /v1/pedidos/{id}/recibir
```

Se carga **lo que llegó tal como viene**, sin acomodar ningún valor. Si al
copiar se "arreglara" algo, el comparador nunca encontraría nada y la revisión
sería un trámite.

## 8. El control de calidad

```
POST /v1/controles-calidad/comparar     (no guarda nada)
POST /v1/controles-calidad              (guarda el veredicto)
```

El endpoint de comparar existe para ver las diferencias **antes** de poner el
veredicto. El orden importa.

Si el laboratorio mandó la graduación equivocada, la comparación la muestra
valor por valor:

```
  ojo  meridian  campo    pedido   recibido  diferencia
  der  lejos     esfera   -2.25    -2.75     -0.50
  der  lejos     eje      180      90        -90.00
```

El veredicto lo pone la persona:

- `conforme`: lo que llegó sirve.
- `con_diferencias`: llegó casi, hay diferencias y la persona las acepta.
- `no_conforme`: no sirve. **Bloquea la entrega.**

Con la tolerancia sin configurar, el sistema **no dice** cuáles son aceptables.
Solo las muestra.

## 9. La entrega

```
POST /v1/entregas
POST /v1/entregas/{id}/entregar
```

La entrega exige que el pedido haya pasado el control de calidad y no haya
salido `no_conforme`. Si no, responde **404** y el lente no se entrega.

Este es el momento en que se evita el reclamo: si el laboratorio mandó la
graduación equivocada y nadie revisó, el cliente ya se la llevó puesta.

`recibido_por` es texto libre, no un usuario: las ópticas entregan a un familiar,
a un representante o por retiro de un tercero, y ninguno tiene cuenta.

## 10. El ajuste

```
POST /v1/ajustes
```

**Todo lente se ajusta.** Un armazón que aprieta o que se desliza no es un
defecto de fabricación: es una entrega que no terminó.

El motivo importa más que el resultado: si casi todos los ajustes son por lo
mismo, el problema es del armazón que se eligió, no del lente. Por eso el motivo
es texto libre y la pantalla destaca el motivo más frecuente.

### No hay plazo de garantía que cumplir

El ajuste se registra en cualquier momento. Los días de garantía sugeridos
nacen en `null` porque son una decisión comercial de la óptica y no un plazo
legal verificable. Ver `docs/07-SEGURIDAD-Y-LEY-19628.md`.

## Resumen

```
paciente
   |
   v
receta (sin fecha de vencimiento, con motivo)
   |
   +-- 4 graduaciones (2 ojos x 2 meridianos)
   |
   v
armazon + lente  --->  verificacion de compatibilidad
                            |
                            v
                        pedido al laboratorio
                            |  (fecha prometida: puede quedar vacia)
                            v
                        recepcion: lo que llego tal cual
                            |
                            v
                        control de calidad  ---> no conforme?  --> pedido nuevo
                            |  conforme
                            v
                        entrega   --->  ajustes posteriores
```

## Lo que este recorrido deja claro

1. **El plazo de vigencia no se inventa.** Nace vacío y el sistema avisa.
2. **La compatibilidad devuelve un desglose, no un sí o un no.** Cuando falta un
   dato, dice que falta.
3. **La graduación se copia en el pedido** para que el control de calidad
   compare contra lo que realmente salió de la óptica.
4. **El control de calidad bloquea la entrega** cuando no sirve. Es el momento en
   que se evita el reclamo.
5. **La entrega no es el final.** Abre la cola de ajustes.