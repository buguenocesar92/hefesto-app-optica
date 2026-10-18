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
 * QUE COMPRUEBA
 * -------------
 *   1. Que las tablas de negocio tengan RLS ACTIVADO y FORZADO. Sin FORCE, el
 *      dueño de la tabla se salta la politica, que es justo el caso del rol
 *      de la aplicacion en desarrollo.
 *   2. Que una optica NO vea los pacientes de otra.
 *   3. Que una consulta SIN contexto de optica falle, en vez de devolver
 *      filas. Este es el que importa: el aislamiento tiene que fallar cerrado.
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