# Instalación

## Lo que hace falta

- Docker y Docker Compose.
- Un servidor PostgreSQL accesible, o el contenedor que trae el compose.
- Una instancia del kit de identidad del estudio, con un cliente de OpenID
  Connect para esta aplicación.

No hace falta instalar PHP, Composer ni Node en la máquina: los contenedor ya
los traen. Ver `apps/optica/docs/10-PRUEBAS-Y-ESTADO.md` para lo que se puede y
no se puede hacer sin ellos.

## Desarrollo

### 1. Configurar

```bash
cd apps/optica/api
cp .env.example .env
php artisan key:generate      # si se tiene PHP local

cd ../web
cp .env.example .env
```

En `api/.env`, lo mínimo indispensable:

```
APP_KEY=
DB_HOST=pgsql
DB_DATABASE=optica
DB_USERNAME=optica
DB_PASSWORD=optica
```

### 2. Levantar

```bash
cd apps/optica/deploy
docker compose -f docker-compose.dev.yml up -d
```

El compose de desarrollo pública puertos en el host, **incluido `55432` para
PostgreSQL**. Eso es correcto en una máquina de desarrollo y es un desastre en
un servidor. El compose de producción no pública ninguno.

### 3. Migrar y verificar

```bash
cd apps/optica/deploy
docker compose -f docker-compose.dev.yml exec api php artisan migrate --force
docker compose -f docker-compose.dev.yml exec api php artisan optica:verificar-aislamiento
```

El segundo comando **tiene que decir que el aislamiento está verificado**. Si
no lo dice, no sigas: revisar `docs/03-MULTITENANCY-Y-ROLES.md`.

### 4. Datos de ejemplo

```bash
docker compose -f docker-compose.dev.yml exec api php artisan db:seed
```

Crea una óptica y las tres cuentas de los tres roles.

**No crea recetas ni graduaciones**, y eso es deliberado: sembrar recetas de
ejemplo obligaría a inventar plazos de vigencia y graduaciones, y quedaría en la
base algo que parece real y no lo es. La graduación es información de salud.

## Producción

### 1. Preparar el entorno

```bash
cd apps/optica/deploy
cp .env.prod.example .env.prod     # completar TODOS los valores
```

El archivo `.env.prod` **no se versiona**. `docker compose config` imprime los
valores resueltos: no hay que pegarlo en ningún lado, ni en un ticket, ni en un
chat.

Los valores marcados como obligatorios en el compose se avisan solos: si falta
uno, el compose se niega a arrancar.

### 2. La clave de la aplicación

```bash
docker compose --env-file .env.prod run --rm api php artisan key:generate --show
```

El resultado va en `APP_KEY` dentro de `.env.prod`.

La clave **no se genera en la imagen**. Generarla en cada despliegue dejaría
invalidas las sesiones y las cookies firmadas, y toda la gente tendría que
volver a entrar cada vez que se despliega.

### 3. Levantar

```bash
docker compose --env-file .env.prod up -d
```

Las migraciones corren solas en el arranque del contenedor de la API. Es lo
correcto con una sola réplica.

**Con varias réplicas**, todas intentarían migrar al mismo tiempo. En ese caso:

```
MIGRAR_AL_ARRANCAR=false
```

en el `.env.prod`, y las migraciones se corren una vez a mano antes de levantar
el resto.

### 4. El rol de la base no puede ser superusuario

Este es el requisito que más se olvida y el que más caro sale.

El rol que usa la aplicación **no puede ser superusuario ni tener `BYPASSRLS`**.
Con superusuario, PostgreSQL ignora las políticas de Row Level Security por
completo: todo lo de `docs/03-MULTITENANCY-Y-ROLES.md` parecería funcionar y
el aislamiento no existiría.

```bash
docker compose --env-file .env.prod exec api php artisan optica:verificar-aislamiento
```

Si el comando pasa, la base no es superusuario. Es la comprobación.

### 5. La primera cuenta de administración

El seeder crea la óptica y las cuentas, pero **no alcanza para entrar**: falta
lo de Authentik.

```bash
docker compose --env-file .env.prod exec api php artisan optica:crear-administrador \
    --optica=centro --nombre="Administración" --correo=persona@ejemplo.cl
```

## Configurar Authentik

### El cliente de OpenID Connect

Cada aplicación de Hefesto tiene su **propio cliente** en Authentik, apuntando
a su propia URL de retorno. Así se puede revocar el acceso a una aplicación sin
tocar las demás.

| Campo | Valor |
|---|---|
| URL de retorno | `https://<dominio>/api/v1/acceso/callback` |
| Tipo de cliente | Público, con `PKCE` |
| Flujo | `code` |
| Alcances | `openid`, `profile`, `email` |

Ese cliente va en `AUTHENTIK_CLIENT_ID` y `AUTHENTIK_CLIENT_SECRET`.

### Los equipos

La persona tiene que pertenecer a **dos** equipos:

1. **Un equipo de rol.** El mapa está en `config/authentik.php` y **arranca
   vacío**:

   ```php
   'grupos_por_rol' => [
       // 'Administradores de OpticaHefesto' => 'administrador',
       // 'Optometristas de OpticaHefesto' => 'optometrista',
       // 'Mostrador de OpticaHefesto' => 'mostrador',
   ],
   ```

   Descomentar y poner exactamente los nombres que se crearon en Authentik.

2. **Un equipo que declara la óptica**, con el formato
   `OpticaHefesto:<correlacion>`, donde la correlación es el valor de
   `opticas.correlacion_identidad`.

   Para la óptica del seeder, la correlación es `centro`, así que el equipo se
   llama **`OpticaHefesto:centro`**.

### Por qué el mapa arranca vacío

Mientras no se llene, no se puede iniciar sesión.

La alternativa sería dejar un rol por omisión, y con eso cualquier persona que
consiga autenticarse entra con ese rol. Preferimos que no entre nadie.

### Después de cambiar la configuración

```bash
docker compose --env-file .env.prod exec api php artisan optica:olvidar-cache-oidc
docker compose --env-file .env.prod restart api
```

La metadata del proveedor se cachea una hora y las claves un día. Sin este
comando, un cambio de emisor o de cliente no se ve.

## Verificar que todo quedó bien

En este orden:

```
1. GET /v1/salud                -> {"ok":true,...}
2. GET /v1/acceso/opticas       -> lista con id, slug y nombre. NADA MAS.
3. Pantalla de entrada          -> el selector muestra las ópticas
4. Ingresar                     -> vuelve a la aplicación
5. optica:verificar-aislamiento -> dice que el aislamiento está verificado
6. optica:revisar-vencimientos  -> cuenta las recetas
```

El paso 2 es el que comprueba que la vista se está usando de verdad. Si la
respuesta trae `correlacion_identidad` o cualquier columna extra, hay un
problema. Ver `docs/03-MULTITENANCY-Y-ROLES.md`.

## Antes de atender pacientes reales

Esto no es opcional, y no lo hace el software:

1. **Verificar el plazo de vigencia** contra la norma sanitaria vigente y
   cargar el valor. Ver `docs/06-PRIMERA-RECETA.md`.
2. **Verificar el margen efectivo del lente** y cargar
   `OPTICA_DIAMETRO_EFECTIVO_MINIMO_MM`. Mientras esté vacío, la verificación
   de compatibilidad responde `no_verificable`.
3. **Definir la tolerancia del control de calidad** según el criterio clínico de
   la óptica.
4. **Cargar el catálogo real** de armazones con sus medidas de caja y de lentes
   con su diámetro de corte.
5. **Revisar los días de conservación** de recetas y bitácora. Con las
   variables vacías, la aplicación no purga nada por su cuenta.
6. **Desactivar las cuentas de ejemplo** que dejó el seeder.

## Problemas frecuentes

### El ingreso dice que no pertenece a ninguna óptica

La persona no está en el equipo `OpticaHefesto:<correlacion>`, o la correlación
no coincide con `opticas.correlacion_identidad`.

### El ingreso dice que no pertenece a ningún equipo de rol

El mapa `grupos_por_rol` de `config/authentik.php` está vacío, o el nombre del
equipo en Authentik no coincide exactamente.

### El aislamiento no verifica

Tres causas posibles:

1. El rol de la base es superusuario o tiene `BYPASSRLS`.
2. Falta `FORCE ROW LEVEL SECURITY` en alguna tabla.
3. La conexión no es PostgreSQL.

Las pruebas de aislamiento **exigen PostgreSQL**. En SQLite pasarían sin
comprobar nada. Ver `docs/10-PRUEBAS-Y-ESTADO.md`.

### La verificación de compatibilidad siempre dice que no es verificable

Falta `OPTICA_DIAMETRO_EFECTIVO_MINIMO_MM`, o el lente del catálogo no tiene
`diametro_corte_mm` cargado. Es el comportamiento correcto: el sistema no puede
afirmar que el lente entra sin esos datos. Ver `docs/06-PRIMERA-RECETA.md`.