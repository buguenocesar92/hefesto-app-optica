<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Optica;
use App\Models\Paciente;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba, desde la consola, que la RLS esta puesta y funciona.
 *
 * Sirve para lo mismo que la suite `Aislamiento`, pero en el servidor: antes
 * de atender pacientes reales, o despues de tocar una migracion.
 *
 *     php artisan optica:verificar-aislamiento
 *
 * NO es una prueba unitaria: escribe filas de prueba y las borra al final.
 * No debe correrse contra una base con datos reales de pacientes.
 *
 * QUE COMPRUEBA, EN ORDEN
 * -----------------------
 *   1. Que el rol con el que conecta la aplicación NO sea superusuario ni
 *      tenga BYPASSRLS. Va primero, y antes de escribir nada.
 *   2. Que las tablas de negocio tengan RLS ACTIVADO y FORZADO. Sin FORCE, el
 *      dueño de la tabla se salta la política, que es justo el caso del rol
 *      de la aplicación en desarrollo.
 *   3. Que una óptica NO vea los pacientes de otra.
 *   4. Que una consulta SIN contexto de óptica falle, en vez de devolver
 *      filas. Este es el que importa: el aislamiento tiene que fallar cerrado.
 *
 * POR QUÉ EL ROL VA PRIMERO
 * ------------------------
 * Con un rol superusuario, o con BYPASSRLS, PostgreSQL ignora todas las
 * políticas: `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla, pero no al
 * superusuario. Las demás comprobaciones seguirían dando verde, porque todas se
 * ejecutan con ese mismo rol. Es el fallo más grave y el más silencioso, y por
 * eso se comprueba solo, directo contra `pg_roles`.
 *
 * NADA DE ESTO SE HA EJECUTADO
 * ----------------------------
 * En el equipo donde se escribió este comando no había PostgreSQL ni Docker.
 * El código se revisó a mano y no hay `php -l` disponible, así que no se
 * declara probado: no ha corrido contra ninguna base real. Ver `docs/10`.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'optica:verificar-aislamiento
        {--optica : Correlacion de una optica existente para usar en la prueba. Si no se indica, se crea una optica temporal.}';

    protected $description = 'Comprueba que el aislamiento entre opticas funciona a nivel de base de datos.';

    /**
     * Las mismas tablas de la migracion 2026_10_18_000015. Si se agregan tablas
     * nuevas, hay que agregarlas en los dos lados o esta comprobacion deja de
     * cubrir el aislamiento.
     *
     * @var list<string>
     */
    private const TABLAS_PROTEGIDAS = [
        'usuarios',
        'pacientes',
        'recetas',
        'graduaciones',
        'armazones',
        'lentes',
        'pedidos_laboratorio',
        'pedido_items',
        'controles_calidad',
        'entregas',
        'ajustes',
        'accesos_recetas',
    ];

    public function handle(ContextoTenant $contexto): int
    {
        // El rol va PRIMERO, antes de mirar las tablas y, sobre todo, antes de
        // escribir las filas temporales. Con un rol que salta RLS, esas filas se
        // escribirían en una base sin aislamiento y el resto de las
        // comprobaciones pasarían en verde sin estar mirando nada.
        if (! $this->revisarRol()) {
            return self::FAILURE;
        }

        $this->components->info('Revisando que las tablas de negocio tengan RLS forzado.');

        $fallos = 0;

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $fila = DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzado FROM pg_class WHERE relname = ?',
                [$tabla]
            );

            if ($fila === null) {
                $this->components->error("La tabla {$tabla} no existe.");

                $fallos++;

                continue;
            }

            if (! $fila->rls || ! $fila->forzado) {
                $this->components->error("La tabla {$tabla} no tiene RLS forzado.");

                $fallos++;
            }
        }

        if ($fallos > 0) {
            $this->components->error('La instalacion NO tiene el aislamiento activo. No siga.');

            return self::FAILURE;
        }

        $this->components->info('Las tablas tienen RLS forzado.');

        return $this->comprobarLecturaCruzada($contexto);
    }

    /**
     * QUE EL ROL CONECTADO NO SE SALTE LAS POLÍTICAS
     * -----------------------------------------------
     * Consulta `pg_roles` directamente por `current_user` y NO depende de que la
     * base tenga datos: contra una base recién creada y vacía, una comprobación
     * indirecta daría verde sin comprobar nada. Por eso esta corre contra el
     * catálogo de PostgreSQL y no contra las tablas de la aplicación.
     *
     * Antes el comando no miraba el rol en absoluto: lo detectaba solo de forma
     * indirecta, por la lectura cruzada entre dos ópticas, y esa lectura depende
     * de que las filas temporales se escriban y se lean, no de los atributos del
     * rol. Con la base vacía, ese camino no probaba nada.
     *
     * Es el fallo más grave y el más silencioso: un superusuario, o un rol con
     * `BYPASSRLS`, ignora todas las políticas de Row Level Security, y
     * `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla pero **no** al
     * superusuario. Con ese rol, una óptica vería las recetas y los pacientes de
     * las demás, sin error y sin aviso.
     */
    private function revisarRol(): bool
    {
        try {
            $rol = DB::selectOne(
                'SELECT rolname AS nombre, rolsuper AS superusuario, rolbypassrls AS bypass
                 FROM pg_roles
                 WHERE rolname = current_user'
            );
        } catch (Throwable $e) {
            $this->components->error('No se pudo leer el rol de la conexión: ' . $e->getMessage());
            $this->components->error('Sin ese dato no se puede afirmar nada sobre el aislamiento.');

            return false;
        }

        if ($rol === null) {
            $this->components->error('No se pudo determinar el rol de la conexión.');

            return false;
        }

        if ($rol->superusuario) {
            $this->components->error("El rol \"{$rol->nombre}\" ES SUPERUSUARIO.");
            $this->components->error('Un superusuario ignora TODAS las políticas de Row Level Security.');
            $this->components->error('`FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla, pero NO al');
            $this->components->error('superusuario: con este rol el aislamiento entre ópticas no existe.');
            $this->components->error('Qué hacer: `POSTGRES_USER` debe ser el rol de arranque');
            $this->components->error('`optica_admin`, que la aplicación nunca usa, y el rol de la');
            $this->components->error('aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->components->error('`deploy/postgres/inicializar-rol.sh`.');
            $this->components->error('Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return false;
        }

        if ($rol->bypass) {
            $this->components->error("El rol \"{$rol->nombre}\" tiene BYPASSRLS.");
            $this->components->error('Con BYPASSRLS, PostgreSQL ignora TODAS las políticas de Row Level Security,');
            $this->components->error('aunque las tablas tengan `FORCE ROW LEVEL SECURITY` puesto.');
            $this->components->error('Qué hacer: el mismo de arriba. `POSTGRES_USER` debe ser el rol de');
            $this->components->error('arranque `optica_admin`, que la aplicación nunca usa, y el rol de la');
            $this->components->error('aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->components->error('`deploy/postgres/inicializar-rol.sh`.');
            $this->components->error('Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return false;
        }

        $this->components->info("El rol \"{$rol->nombre}\" no es superusuario ni tiene BYPASSRLS.");

        return true;
    }

    private function comprobarLecturaCruzada(ContextoTenant $contexto): int
    {
        $opticaA = $this->opticaA();

        if ($opticaA === null) {
            $this->components->error(
                'La optica indicada con --optica no existe. '
                .'Se busca por correlacion_identidad.'
            );

            return self::FAILURE;
        }

        $opticaB = $this->obtenerOCrearOptica('verificacion-b');

        $pacienteB = $contexto->ejecutarAislado($opticaB->id, fn (): Paciente => Paciente::query()->create([
            'nombres' => 'Paciente',
            'apellidos' => 'DeVerificacion',
            'fecha_nacimiento' => '1990-01-01',
        ]));

        $vistos = $contexto->ejecutarAislado($opticaA->id, fn (): int => Paciente::query()
            ->whereKey($pacienteB->id)
            ->count());

        try {
            if ($vistos !== 0) {
                $this->components->error('La optica A alcanzo a ver un paciente de la optica B.');
                $this->components->error('El aislamiento NO funciona. Revise FORCE ROW LEVEL SECURITY.');

                return self::FAILURE;
            }

            $this->components->info('A no ve pacientes de B. Correcto.');
        } finally {
            $contexto->ejecutarAislado($opticaB->id, fn () => $pacienteB->delete());
        }

        /*
        | La comprobacion que mas importa: sin contexto, la consulta tiene que
        | FALLAR. Si devolviera filas, existiria una ventana por la que un
        | proceso que seolvide del middleware leeria datos de todas las
        | opticas.
        */
        try {
            Paciente::query()->count();

            $this->components->error('Una consulta sin contexto devolvio filas en vez de fallar.');
            $this->components->error('La funcion public.app_tenant_id() deberia lanzar excepcion.');

            return self::FAILURE;
        } catch (Throwable) {
            $this->components->info('Sin contexto, la consulta falla. El aislamiento falla cerrado.');
        }

        $this->components->info('Aislamiento verificado.');

        return self::SUCCESS;
    }

    /**
     * La optica con la que se prueba la lectura cruzada.
     *
     * Con `--optica` se usa una optica real, para comprobar contra datos de
     * verdad que A no ve los de B. Sin la opcion, se crea una temporal, que
     * es lo correcto contra una base de produccion: no se tocan los datos de
     * nadie.
     */
    private function opticaA(): ?Optica
    {
        $correlacion = $this->option('optica');

        if (! is_string($correlacion) || $correlacion === '') {
            return $this->obtenerOCrearOptica('verificacion-a');
        }

        return Optica::query()
            ->where('correlacion_identidad', $correlacion)
            ->first();
    }

    private function obtenerOCrearOptica(string $sufijo): Optica
    {
        $correlacion = 'optica-verificacion-'.str_replace('verificacion-', '', $sufijo);

        return Optica::query()->firstOrCreate(
            ['correlacion_identidad' => $correlacion],
            [
                'slug' => $correlacion,
                'nombre' => 'Temporal de verificacion ('.$sufijo.')',
                'zona_horaria' => 'America/Santiago',
                'activo' => true,
            ]
        );
    }
}