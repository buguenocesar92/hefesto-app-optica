# Respaldos

## Qué protege un respaldo

La base de un sistema de óptica contiene **recetas y graduaciones**. Son datos de
salud de pacientes que cree en un lugar, y además el historial comercial de una
óptica que necesita para facturar y para responder.

Por eso el respaldo no es una comodidad: es la única copia de algo que no se
puede volver a conseguir.

## Los dos scripts

```
deploy/respaldos/respaldo.sh
deploy/respaldos/restauracion.sh
```

Ambos se ejecutan **en Linux**, dentro o junto a un contenedor. En Windows se
usan desde WSL o desde un contenedor.

## Respaldo

```
./respaldo.sh /var/backups/optica
```

### Qué produce

```
/var/backups/optica/optica-20261018T143000Z/
    optica-20261018T143000Z.dump    el volcado de PostgreSQL
    manifiesto.txt                   SHA-256 y datos del volcado
    COMPLETO                         marca de que terminó bien
```

### Las reglas que respeta, y por qué

#### 1. Manifiesto con SHA-256 de cada archivo

Un respaldo sin verificar es una caja fuerte con la llave adentro.

El hash permite saber si lo que se guardó es lo mismo que se respaldó, y la
restauración lo comprueba **antes de tocar la base**.

#### 2. La marca `COMPLETO` se escribe AL FINAL, y solo al final

Si el script se corta a mitad de camino, no queda `COMPLETO`.

Un respaldo a medias y marcado como bueno es **peor que no tener respaldo**:
uno se tranquiliza creyéndolo entero. La restauración se niega a restaurar si
falta ese archivo.

#### 3. Valida rutas y espacio antes de escribir

- El destino tiene que **existir**. El script no crea directorios de destino,
  porque un error de tipeo en la ruta es mejor que un respaldo en el lugar
  equivocado.
- El destino tiene que tener **permiso de escritura**.
- Tiene que haber **espacio disponible**. Por omisión exige 256 MB, ajustable
  con `ESPACIO_MINIMO_MB`.

Un respaldo que falla por disco lleno a mitad de camino deja un volcado
truncado que parece bueno.

#### 4. Rechaza el destino ocupado, salvo `--force`

```
./respaldo.sh /var/backups/optica --force
```

Sin `--force`, si la carpeta del respaldo ya existe, el script para. Un
`rm -rf` sobre un directorio equivocado no tiene vuelta atrás.

Con `--force` la reemplaza, porque a veces hay que rehacer. Nunca por omisión.

#### 5. Nunca `DROP`, nunca sobrescritura automática

Este script **solo lee** de la base. No borra nada, no altera nada.

La limpieza vive en la restauración, y usa `TRUNCATE`. Ver abajo.

#### 6. El volcado a medias nunca queda con el nombre definitivo

Se escribe primero como `.partial` y se mueve al terminar. Un volcado truncado
nunca aparece como `optica-....dump`.

#### 7. Verifica el hash recién escrito

Después de escribir el manifiesto, el script vuelve a calcular el hash y lo
compara. Si no coincide, borra la carpeta y falla.

### Desde un contenedor

Si PostgreSQL no está publicado en el host, hay que correr `pg_dump` dentro de
la red:

```bash
DB_HOST=pgsql \
DB_PORT=5432 \
DB_DATABASE=optica \
DB_USERNAME=optica \
DB_PASSWORD='...' \
RED=optica-hefesto-interna \
./respaldo.sh /var/backups/optica
```

Con `RED` definida, el script usa un contenedor efímero de esa red en lugar del
`pg_dump` del host.

## Restauración

```
./restauracion.sh /var/backups/optica/optica-20261018T143000Z --confirmar
```

Y después escribe `SIEMPRE` cuando lo pide.

### Las reglas que respeta, y por qué

#### 1. NUNCA `DROP`

Esta es la más importante.

`DROP TABLE` borra la tabla, sus índices, sus restricciones, **sus políticas de
Row Level Security y sus funciones de apoyo**.

Si el respaldo falla a mitad de camino, queda una base **sin RLS**: el
aislamiento entre ópticas desaparece y no hay forma de saber que pasó. Eso es un
desastre silencioso.

Con `TRUNCATE`, la estructura y las políticas siguen en pie, y un error de
restauración deja datos incompletos, no una base desprotegida.

Se limpia con:

```sql
TRUNCATE TABLE accesos_recetas ajustes controles_calidad entregas
    pedido_items pedidos_laboratorio graduaciones recetas armazones
    lentes pacientes usuarios RESTART IDENTITY CASCADE;
```

`CASCADE` recorre las tablas que dependen de estas (`pedido_items` depende del
pedido, por ejemplo), y `RESTART IDENTITY` deja los contadores en cero.

#### 2. Verifica el SHA-256 ANTES de limpiar nada

Si el hash no coincide, el script para. **No hay ninguna opción para saltarse
esta comprobación.**

Un volcado dañado se restaura sin quejarse y deja una base incoherente. El hash
es la única prueba de que lo guardado sirve.

#### 3. Verifica que el respaldo está COMPLETO

Sin el archivo `COMPLETO`, no se restaura. Ver la regla 2 del respaldo.

#### 4. Nunca sobrescribir sin `--confirmar`

Restaurar **reemplaza** todos los datos de negocio. Es la operación más
destructiva del proyecto, y por eso lleva `--confirmar` explícito y una
confirmación escrita.

#### 5. Deja el servicio DETENIDO

Un despliegue se levanta con el servicio apagado. Si la restauración levanta la
API, alguien puede entrar y leer una base a medio restaurar.

El script detiene el servicio antes de empezar y **no lo levanta al terminar**.
Imprime los comandos para hacerlo, pero no los ejecuta.

## El orden correcto de un despliegue con restauración

```
1.  ./respaldo.sh /var/backups/optica
        Respaldar ANTES de tocar nada. Aunque parezca redundante.

2.  docker compose -f docker-compose.prod.yml stop api

3.  ./restauracion.sh /var/backups/optica/optica-... --confirmar

4.  docker compose -f docker-compose.prod.yml up -d

5.  docker compose -f docker-compose.prod.yml exec api \
        php artisan optica:verificar-aislamiento

6.  Comprobar a mano:
      - entrar a la aplicación
      - abrir un paciente
      - ver que sus recetas están
      - revisar que las recetas que faltan son las que faltaban
```

El paso 5 no es opcional. Si la restauración se cortó a mitad de camino, la
comprobación del aislamiento puede seguir dando verde (la estructura existe)
mientras los datos están incompletos. Por eso hay que mirar también los datos.

## Probar un respaldo

Un respaldo que nunca se restauró es una hipótesis.

```bash
# En un entorno de pruebas, nunca en produccion:
./restauracion.sh /var/backups/optica/optica-20261018T143000Z --confirmar
```

Si el entorno de pruebas es una base vacía, el resultado esperado es cero
filas. Si trae filas que no deberían, el respaldo no es de esta base.

## Automatizar

```cron
0 3 * * *  DB_HOST=pgsql DB_PORT=5432 DB_DATABASE=optica \
          DB_USERNAME=optica DB_PASSWORD='...' RED=optica-hefesto-interna \
          /ruta/a/respaldo.sh /var/backups/optica >> /var/log/respaldo-optica.log 2>&1
```

Y una retención:

```bash
find /var/backups/optica -maxdepth 1 -type d -name 'optica-*' -mtime +30 -exec rm -rf {} +
```

**Los respaldos contienen datos de pacientes.** Aunque sean réplicas, el
directorio de respaldos tiene que estar en un disco cifrado, con el acceso
restringido a quien corresponde, y **no se versiona en git**. El `.gitignore` de
la aplicación lo excluye.

## Por qué no hay respaldos incrementales

Esta versión hace un volcado completo cada vez.

Para una óptica, con una base de pocos megabytes, el volcado completo es simple,
rápido y verificable. Los incrementales agrega una capa de complejidad y una
forma más de que la restauración falle.

Si alguna vez hicieran falta, la decisión es de la persona que opera el sistema,
no del software, y debería anotarse aquí.

## Qué queda sin respaldar

El script respalda **la base de datos**. No respalda:

- los archivos que sube el usuario por la interfaz: **no hay carga de archivos**,
  así que no hay nada que respaldar ahí.
- la configuración: está en git y en `.env.prod`, que se respalda por separado
  según la política de la óptica.
- el volumen de Redis: es caché y cola. La sesión vive en PostgreSQL, no en
  Redis. Perder Redis pierde tareas encoladas, no datos de pacientes.

**`.env.prod` tiene el secreto del cliente OIDC.** Si se pierde, la aplicación no
puede validar los ingresos. Hay que respaldarlo por separado, cifrado, y fuera
del directorio de respaldos de la base.