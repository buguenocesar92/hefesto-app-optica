# Seguridad y Ley 19.628

## Lo que es información de salud en esta aplicación

La **graduación de una receta**. Es decir: cómo ve el paciente.

Eso no es un dato comercial, aunque la aplicación sea comercial. Y el hecho de
que quien la escribe sea un optometrista, no un médico, no la cambia.

También es información de salud, aunque con menos sensibilidad:

- la existencia de una receta y su fecha de emisión,
- el hecho de que una persona usa lentes y por qué,
- los ajustes posteriores a la entrega, que dicen qué le molestó de unos lentes.

La ficha del paciente (nombre, RUT, teléfono, correo) es dato personal y de
contacto, no de salud. Por eso la ven las tres personas que trabajan en el
local.

## Acceso por rol

| Rol | Pacientes | Recetas y graduaciones | Bitácora | Cuentas |
|---|---|---|---|---|
| `mostrador` | sí | **no** | no | no |
| `optometrista` | sí | **sí** | no | no |
| `administrador` | sí | **no** | **sí** | **sí** |

### La regla

**La graduación solo la ve el optometrista.**

Ni el mostrador, que atiende al cliente y ve su ficha. Ni la administración, que
maneja el catálogo, las cuentas y la bitácora.

Que una persona maneje la caja no la convierte en alguien que deba leer
graduaciones. Y una cuenta de administración no es una cuenta de superusuario:
tiene más accesos **administrativos** y menos **de salud**.

### Doble capa

Las rutas de receta llevan el middleware `receta.auditada` **y** la policy de
Laravel. Si alguien agrega una ruta nueva y olvida una, la otra sigue.

El middleware además registra el intento en la bitácora, permitido o denegado.

### La bitácora también está protegida

Solo la administración puede leerla. Ver quién abrió qué receta es, en la
práctica, ver quién anda mirando las graduaciones de los pacientes: es
información de salud sobre el comportamiento de las personas.

## Un rechazo es 404, no 403

Cuando una policy rechaza, la respuesta es **404**.

En un sistema de inventario la diferencia es poca. Acá es más fuerte: un `403`
le confirmaría a quien pregunta que **el paciente existe** en otra óptica. Con
`404` no se confirma nada.

Está en `bootstrap/app.php`, que traduce la excepción de autorización a `404`
para todas las rutas de la API.

## El rol se resuelve en cada ingreso

El rol se deduce de los **equipos de Authentik** en cada ingreso, no de una
columna de la base.

Si a alguien le quitan el equipo de optometristas, pierde el acceso a las
graduaciones **en la sesión siguiente**. Guardar el rol para siempre dejaría
ventanas de acceso que nadie cerraba.

## No hay contraseñas

La tabla `usuarios` no tiene columna de contraseña, y **no se debe agregar**.

La identidad la emite Authentik con OpenID Connect. La aplicación recibe un
token, verifica la firma, y abre una sesión.

Consecuencias:

- una filtración de la base de datos **no entrega credenciales**,
- la contraseña de una persona nunca está en este sistema,
- no hay restablecimiento de contraseña que atacar.

El bloque `passwords` de `config/auth.php` existe porque Laravel lo espera, y
**no debe habilitarse** sin una decisión de seguridad previa.

## El token se verifica como tiene que ser

`App\Services\Auth\VerificadorIdToken` comprueba:

1. **el algoritmo, antes de nada.** Si leyera el algoritmo de la cabecera del
   token y usara ese, un atacante podría cambiarlo a `HS256` y firmar el token
   con la clave **pública** del proveedor. Por eso el algoritmo se compara con
   `config('authentik.algoritmo')` y si no coincide se rechaza;
2. **la firma**, con las claves del JWKS del proveedor;
3. **la audiencia**, que tiene que ser el cliente propio de esta aplicación;
4. **el emisor**, que tiene que ser el proveedor configurado;
5. **`exp`, `iat` y `nbf`**, con el margen de reloj configurado.

## La bitácora de accesos

`accesos_recetas` registra **tanto lo permitido como lo denegado**.

Un intento denegado es tan informativo como uno permitido: es la señal de que
alguien está probando el sistema con el rol equivocado.

### Por qué otra conexión

Una petición denegada termina con su transacción **revertida**. El registro de
"esta persona quiso ver esto y no pudo" se guardaría en esa misma transacción,
es decir, no se guardaría nunca.

Por eso `App\Services\Recetas\BitacoraRecetas` escribe en la conexión
`auditoria`, independiente de la de la petición. Un `INSERT` en PostgreSQL no
depende de la transacción que esté abierta, así que el registro sobrevive a la
reversa.

### Qué NO se guarda en la bitácora

**Ningún valor de graduación.** Solo el identificador del recurso, quién lo
pidió, cuándo y si se le permitió.

La bitácora no puede convertirse en una segunda copia de la información de
salud sin protección.

### Si la bitácora falla, la petición continúa

Un registro de bitácora que tumba el sistema es un problema más grave que un
registro que falta. Y bloquear el acceso a una receta por fallar la bitácora
dejaría a la óptica sin poder trabajar por un problema de disco.

El fallo queda en el registro de Laravel, que sí va a `stderr`.

## Conservación: los plazos nacen en `null`

`OPTICA_RETENCION_RECETAS_DIAS` y `OPTICA_RETENCION_ACCESOS_RECETAS_DIAS`
**nacen vacías**.

Cuánto tiempo se conserva una receta depende del contrato de tratamiento de
datos que la óptica firmó con sus pacientes y de la normativa sectorial que le
aplique. **No se pudo verificar el plazo que corresponde**, así que no se
escribe.

**Con las variables vacías, la aplicación no purga nada por su cuenta.**

Borrar la graduación de un paciente porque pasó un número inventado es peor que
no borrar nunca.

La hora de eliminarla la define la óptica, con su criterio legal, y no este
software.

## Otras medidas de la aplicación

| Medida | Dónde |
|---|---|
| Sin renderizado en el servidor | `nuxt.config.ts`. El HTML que llega no lleva graduaciones. |
| El navegador nunca ve el origen de la API | `server/api/[...ruta].ts`. La cookie puede seguir siendo `HttpOnly` y `SameSite=Lax`. |
| Cookie de sesión `HttpOnly` y `SameSite=Lax` | `deploy/api/docker/php.ini` |
| `SESSION_SECURE_COOKIE=true` en producción | `docker-compose.prod.yml` |
| Token CSRF reenviado por el proxy | `server/api/[...ruta].ts` |
| Estado aleatorio en el flujo OIDC | `App\Http\Controllers\AuthController` |
| Sesión expira en 120 minutos | `config/auth.php` |
| Sin carga de archivos | `php.ini` y `deploy/api/docker/nginx.conf` |
| `display_errors = Off` | `php.ini`. Una excepción en pantalla puede incluir contenido de una petición. |
| Registro a `stderr`, nunca a un archivo | `php.ini`, `nginx.conf`, `supervisord.conf` |
| `LOG_LEVEL=info`, no `debug` | En producción `debug` deja el cuerpo de las peticiones en el registro. |
| Solo el proxy pública puertos | `docker-compose.prod.yml`. PostgreSQL y Redis no se alcanzan desde fuera. |
| Cabeceras de seguridad | `deploy/caddy/Caddyfile` |
| Sin SSR, sin caché de HTML con datos | `nuxt.config.ts` |
| No se indexa por buscadores | `nuxt.config.ts`. `noindex, nofollow`. |
| Las recetas no se borran | `RecetaPolicy`. Se anulan, y queda constancia. |
| Los pacientes no se borran | `PacientePolicy`. Se desactivan. |
| Las cuentas no se borran | `UsuarioPolicy`. Se desactivan, y quedan en recetas y bitácora. |
| Nadie se desactiva a sí mismo | `UsuarioPolicy::desactivarSelf`. Un administrador podría dejar la óptica sin cuentas válidas. |

## Lo que falta y hay que decidir

Esta aplicación **no resuelve** el cumplimiento de la Ley 19.628. Entre otras
cosas, no tiene:

- registro de consentimiento del paciente,
- autorización del titular para el tratamiento de sus datos,
- procedimiento de acceso, rectificación, supresión y oposición del titular,
- manual de procedimientos y máquina de eventos,
- contrato de tratamiento de datos con el proveedor de identidad,
- canal de notificación de vulneraciones.

Todo eso depende de la óptica, de su asesoría legal y de su situación concreta, y
**no se puede decidir desde el software**.

Lo que sí hace el software es dejar las herramientas: aislamiento por
óptica, control de acceso por rol, bitácora de accesos y una separación clara
entre lo administrativo y lo de salud.

## Sobre la Ley 19.628

La Ley 19.628 regula el tratamiento de datos personalesreferentes a la salud,
entre otras materias. **Este documento no es una interpretación legal** y no
sustituye la asesoría de un abogado.

Lo que sí hace esta aplicación es señalar dónde el cumplimiento depende de
decisiones que no son técnicas: qué plazos de conservación corresponden, qué
consentimiento se pide, qué se le responde a un titular que pide ver sus datos.

Ver `docs/05-CONFIGURACION.md` para las variables de conservación y
`docs/08-RESPALDOS.md` para el resguardo de los datos.