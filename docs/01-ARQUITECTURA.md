# Arquitectura

## Qué es esta aplicación

OpticaHefesto maneja el ciclo de un par de lentes en una óptica pequeña: la
receta del paciente, el armazón y el lente que le encajan, el encargo al
laboratorio, lo que llega de vuelta, la revisión antes de entregarlo y los
ajustes posteriores.

El ciclo completo, en ASCII:

```
  Paciente          Receta            Pedido          Recepcion        Entrega
     |                 |                 |                 |               |
     v                 v                 v                 v               v
  ficha de     4 filas de        encargo al        llega de        se le da
  contacto     graduacion        laboratorio      vuelta          al cliente
               (2 ojos x           |                  |               |
                2 meridianos)      |           control de         se ajusta
                                  v           calidad            en el local
                            fecha prometida      |
                            (si la hay)     conforme?
                                                  |
                                              si no conforme:
                                              vuelve al laboratorio
                                              con un pedido nuevo
```

## Las tres piezas

```
   Navegador
       |
       |  mismo origen, cookie de sesion
       v
  +------------------+
  | Caddy (proxy)    |  el UNICO que publica puertos
  +------------------+
       |            \
       v             v
  +--------+     +--------+
  |  Nuxt  |     | Laravel |
  |  SPA   | --> |  API   |
  +--------+     +--------+
                      |
                      v
                +----------+
                | Postgres  |  Row Level Security
                +----------+
```

### El proxy

Caddy es el único servicio con puertos publicados. Todo lo demás vive en la red
interna de Compose y no se alcanza desde el exterior. Ver
[04-INSTALACION.md](04-INSTALACION.md).

### La interfaz

Nuxt 3 con **renderizado en el servidor apagado** (`ssr: false`).

La razón no es rendimiento. La aplicación maneja recetas y graduaciones: con
renderizado en el servidor, cada página llega al navegador como HTML ya
lleno, y ese HTML queda en el historial del navegador, en su caché y en los
proxies intermedios. Sin renderizado en el servidor, el HTML que llega está
vacío y los datos los pide el navegador después.

Además el ingreso es con cookie de sesión. Con renderizado en el servidor, cada
petición del servidor de Nuxt tendría que reenviar esa cookie hacia adelante, y
un fallo de reenvío se traduce en una pantalla vacía sin explicación.

### La API

Laravel sobre PHP 8.3. Corre en un solo contenedor con tres procesos
coordinados por `supervisord`: nginx, php-fpm y el worker de colas.

## El proxy de datos

El navegador **nunca** habla directo con la API. Habla con `/api/...` en su
propio origen, y el servidor de Nuxt reenvía:

```
  navegador  -->  /api/v1/pacientes
                      |
                      v
              server/api/[...ruta].ts
                      |  quita el prefijo /api
                      v
              http://api:8000/v1/pacientes
```

Dos razones, y la segunda es la importante:

1. **En desarrollo** el navegador está en `localhost:3000` y la API en
   `localhost:8000`. Son dos orígenes y el navegador lo bloquea.
2. **En producción** la sesión viaja en una cookie de la API. Si el navegador
   hablara directo con la API, la cookie tendría que ir con `SameSite=None` y
   quedaría expuesta a cualquier sitio. Con el proxy, el navegador nunca ve el
   origen de la API y la cookie puede seguir siendo `HttpOnly` y
   `SameSite=Lax`.

## Por qué PostgreSQL y no SQLite

El aislamiento entre ópticas está implementado con **Row Level Security**, que
es una función de PostgreSQL. No hay equivalente en SQLite.

Por eso las pruebas de aislamiento **exigen** una base PostgreSQL de verdad: en
SQLite pasarían sin comprobar nada, porque un sistema sin aislamiento alguno
también daría verde. Ver [10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

## Por qué la base impone la estructura

En una óptica, el peor error posible es despachar lentes con una graduación que
no tiene sentido. Eso se descubre cuando el cliente ya se los puso.

Por eso la estructura de la graduación no depende de que la aplicación se porte
bien: la tabla `graduaciones` tiene restricciones `CHECK` que impiden un eje
fuera de rango, una adición en el meridiano equivocado, o un eje distinto de
cero con un cilindro cero. Ver [02-MODELO-DE-DATOS.md](02-MODELO-DE-DATOS.md).

## Dónde vive cada decisión de negocio

Todo lo que **no es código** está en `config/optica.php`, y lo que no se pudo
verificar está en `null`:

- plazos de vigencia por franja etaria,
- margen efectivo del lente,
- fecha prometida por omisión del laboratorio,
- tolerancias del control de calidad,
- días de conservación.

Ver [05-CONFIGURACION.md](05-CONFIGURACION.md).

## La bitácora usa otra conexión

Una petición denegada termina con su transacción revertida. El registro de
"esta persona quiso ver esta receta y no pudo" se guardaría en esa misma
transacción, es decir, no se guardaría nunca.

Por eso `App\Services\Recetas\BitacoraRecetas` escribe en la conexión
`auditoria`, que es independiente de la de la petición. Un `INSERT` en
PostgreSQL no depende de la transacción que esté abierta, así que el registro
sobrevive a la reversa. Ver [07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

## Cola de trabajo

Hay un solo trabajo diferido: `RevisarVencimientosDeRecetas`. Cuenta recetas por
tipo de vigencia y **no modifica ninguna**.

En una instalación con una sola óptica corre en línea una vez al día. Con varias,
cada óptica se revisa en su propio trabajo encolado. Ver
[04-INSTALACION.md](04-INSTALACION.md).

## Mapas de archivos

| Ruta | Qué hay |
|---|---|
| `api/app/Models/` | Los quince modelos |
| `api/app/Services/Tenancy/` | El contexto de óptica |
| `api/app/Services/Optica/` | La verificación de compatibilidad |
| `api/app/Services/Recetas/` | Vencimiento y bitácora |
| `api/app/Services/Calidad/` | La comparación de graduaciones |
| `api/app/Services/Auth/` | El ingreso con Authentik |
| `api/app/Policies/` | Quién puede ver qué |
| `api/app/Http/Middleware/` | El contexto, la auditoría y el rol |
| `api/database/migrations/` | El esquema |
| `web/app/pages/` | Las pantallas |
| `web/app/tipos/api.ts` | El contrato con la API |
| `web/server/api/` | El proxy de datos |
| `deploy/` | Imágenes, compose y respaldos |