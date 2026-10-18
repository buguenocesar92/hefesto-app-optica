# OpticaHefesto

Óptica y contactología para una pyme chilena. Aplicación propia de Hefesto Studio.

No es la clínica de salud que ya existe en el estudio. El problema técnico que
resuelve es otro: **una receta óptica tiene una vida útil, y produce un producto
que se fabrica con meses de anticipación**.

## Qué resuelve

- **Receta con vencimiento.** Cada receta se emite con una fecha de vencimiento.
  El plazo depende de la edad del paciente. Ese plazo es **norma sanitaria
  chilena**, y en la máquina donde se escribió esta aplicación **no se pudo
  verificar contra la norma vigente**. Por eso nace en `null`, es configurable, y
  el sistema avisa que está pendiente de verificación. Ver
  [`docs/06-PRIMERA-RECETA.md`](docs/06-PRIMERA-RECETA.md).
- **Graduación por ojo y por meridiano.** Esfera, cilindro, eje y adición, con
  una fila por ojo y por meridiano: dos ojos por dos meridianos. La estructura
  la impone la base de datos con restricciones `CHECK`, no solo la aplicación.
  Ver [`docs/02-MODELO-DE-DATOS.md`](docs/02-MODELO-DE-DATOS.md).
- **Catálogo propio de armazones y lentes.** Un armazón tiene medidas de caja y
  un lente tiene material, índice de refracción, tratamiento y **diámetro de
  corte**. La combinación tiene que caber ópticamente.
- **Verificación de compatibilidad.** El servicio devuelve el **desglose del
  cálculo**, no un sí o un no. Cuando falta un dato devuelve
  `no_verificable`, nunca "sí".
- **Pedido a laboratorio.** La óptica no fabrica: encarga. Fecha prometida,
  recepción y control de calidad.
- **Control de calidad al recibir.** Si el laboratorio mandó la graduación
  equivocada, se detecta **antes** de entregarle los lentes al cliente.
- **Entrega y ajustes.** Todo lente se ajusta. La entrega abre la cola de
  ajustes, no la cierra.

## Estado de los datos: la regla del estudio

Cada valor que no se pudo verificar queda en `null` y marcado como pendiente.
Nunca se escribe un número plausible.

| Dato | Estado | Por qué |
|---|---|---|
| Plazo de vigencia por franja etaria | `null` | Es norma sanitaria chilena. No verificado. |
| Márgen efectivo del lente | `null` | Depende del fabricante y del tipo de lente. No verificado. |
| Fecha prometida de laboratorio | `null` | Es comercial. Lo negocia la óptica con su laboratorio. |
| Tolerancia del control de calidad | `null` | Es una decisión clínica del optometrista. |
| Días de retención de recetas | `null` | Depende del contrato con el paciente y de la normativa sectorial. |
| Índice de refracción del catálogo | `null` | Cambia por modelo y por mercado. Lo carga la óptica. |
| Precios | no existe | Esta versión no hace facturación. |

La lista completa está en
[`docs/05-CONFIGURACION.md`](docs/05-CONFIGURACION.md).

## Estado de ejecución

**Nada se ha ejecutado.** En la máquina donde se escribió esta aplicación no hay
PHP, Composer, Node, Docker ni PostgreSQL. Cero migraciones aplicadas, cero
pruebas corridas, cero imágenes construidas, cero contenedores levantados.

Las pruebas existen como código y **no se han corrido nunca**. Ver
[`docs/10-PRUEBAS-Y-ESTADO.md`](docs/10-PRUEBAS-Y-ESTADO.md).

## Estructura

```
api/        Laravel + PHP 8.3. Modelos, policies, servicios y pruebas.
web/        Nuxt 3 + TypeScript. Interfaz, sin renderizado en servidor.
deploy/     Dockerfiles, compose, proxy y respaldos.
docs/       Documentación numerada.
```

## Documentación

| Documento | De qué trata |
|---|---|
| [`00-INDICE.md`](docs/00-INDICE.md) | Índice y orden de lectura |
| [`01-ARQUITECTURA.md`](docs/01-ARQUITECTURA.md) | Cómo encajan las piezas |
| [`02-MODELO-DE-DATOS.md`](docs/02-MODELO-DE-DATOS.md) | Las quince tablas y por qué |
| [`03-MULTITENANCY-Y-ROLES.md`](docs/03-MULTITENANCY-Y-ROLES.md) | Aislamiento entre ópticas |
| [`04-INSTALACION.md`](docs/04-INSTALACION.md) | Levantar la aplicación |
| [`05-CONFIGURACION.md`](docs/05-CONFIGURACION.md) | Variables de entorno |
| [`06-PRIMERA-RECETA.md`](docs/06-PRIMERA-RECETA.md) | Del paciente a la entrega |
| [`07-SEGURIDAD-Y-LEY-19628.md`](docs/07-SEGURIDAD-Y-LEY-19628.md) | La graduación es información de salud |
| [`08-RESPALDOS.md`](docs/08-RESPALDOS.md) | Respaldo y restauración |
| [`09-API.md`](docs/09-API.md) | Endpoints |
| [`10-PRUEBAS-Y-ESTADO.md`](docs/10-PRUEBAS-Y-ESTADO.md) | Qué se probó y qué no |

## Empezar

1. Copiar `api/.env.example` a `api/.env` y `web/.env.example` a `web/.env`.
2. Levantar en desarrollo: `cd deploy && docker compose -f docker-compose.dev.yml up -d`.
3. Revisar [`docs/04-INSTALACION.md`](docs/04-INSTALACION.md) para el alta de
   cuentas y equipos en Authentik.

**Antes de atender pacientes reales**, hay que verificar los plazos que quedaron
en `null` y correr `php artisan optica:verificar-aislamiento`.

---

Software original de Hefesto Studio. Aplicación propia: no es un despliegue de
una herramienta de terceros ni una reetiqueta de un producto existente.