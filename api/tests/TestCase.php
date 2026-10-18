<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pruebas de OpticaHefesto.
 *
 * POR QUE LAS PRUEBAS NECESITAN UNA CONEXION CON CONTEXTO
 * -------------------------------------------------------
 * Casi todo lo que se prueba aqui corre DENTRO de una transaccion con
 * `app.tenant_id` fijado, que es lo que leen las politicas RLS. Por eso
 * `RefreshDatabase` no alcanza: hay que abrir la transaccion a mano, como lo
 * hace el middleware en produccion.
 *
 * Ver App\Services\Tenancy\ContextoTenant.
 *
 * POR QUE PostgreSQL Y NO SQLite
 * -----------------------------
 * Porque la RLS es una funcion de PostgreSQL. En SQLite estas pruebas pasarian
 * sin comprobar nada: un sistema sin aislamiento alguno tambien daria verde.
 * Ver phpunit.xml y docs/10.
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Corre la operacion con una optica fija, como en una peticion real.
     *
     * @template T
     *
     * @param  callable(): T  $operacion
     * @return T
     */
    protected function enOptica(string $opticaId, callable $operacion): mixed
    {
        return DB::transaction(function () use ($opticaId, $operacion) {
            DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $opticaId]);

            return $operacion();
        });
    }

    /**
     * Ejecuta una operacion SIN contexto de optica.
     *
     * Es el caso que tiene que FALLAR: si una consulta a una tabla protegida
     * devolviera filas sin contexto, existiria una ventana por la que un
     * proceso que se olvide del middleware leeria datos de todas las opticas.
     */
    protected function sinOptica(callable $operacion): mixed
    {
        return $operacion();
    }

    /**
     * El tenant de la peticion en curso, para diagnostico.
     */
    protected function opticaEnConexion(): ?string
    {
        $fila = DB::select('SELECT current_setting(?, true) AS valor', ['app.tenant_id']);

        if ($fila === [] || $fila[0]->valor === null || $fila[0]->valor === '') {
            return null;
        }

        return (string) $fila[0]->valor;
    }
}