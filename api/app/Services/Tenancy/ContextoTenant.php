<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de optica (tenant) de la peticion.
 *
 * La RLS de PostgreSQL (migracion 2026_10_18_000015) lee la optica desde la
 * variable de sesion `app.tenant_id`. Esta clase es la UNICA que la escribe.
 * Si alguien la fija por otro lado, el comportamiento deja de estar
 * garantizado: por eso `establecer()` es el unico punto de entrada y exige
 * estar dentro de una transaccion.
 *
 * MISMO PATRON DEL PORTAL DE AUTOSERVICIO
 * ---------------------------------------
 * Columna `tenant_id` mas Row Level Security, no un esquema por optica. La
 * justificacion completa esta en
 * apps/portal-autoservicio/docs/03-MULTITENANCY.md y se repite, adaptada a
 * este dominio, en docs/03-MULTITENANCY-Y-ROLES.md.
 *
 * ALCANCE DE TRANSACCION
 * ----------------------
 * Se usa `set_config(..., true)`: el valor desaparece al confirmar o revertir
 * la transaccion. Con un `SET` normal (ambito de sesion) el valor sobreviviria
 * a la transaccion y, en el worker de colas, que reutiliza la misma conexion
 * para trabajos de opticas distintas, una tarea podria leer graduaciones de
 * otra optica. Ese es exactamente el fallo que este diseno evita, y en una
 * optica ese fallo seria una filtracion de datos de salud.
 */
class ContextoTenant
{
    /** Variable de sesion de PostgreSQL que leen las politicas RLS. */
    public const VARIABLE = 'app.tenant_id';

    /**
     * Fija la optica de la peticion en la conexion actual.
     *
     * @throws RuntimeException si no hay una transaccion abierta.
     */
    public function establecer(string $opticaId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de optica fuera de una transaccion. '
                . 'Use App\Services\Tenancy\ContextoTenant::ejecutarAislado().'
            );
        }

        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE, $opticaId]);
    }

    /**
     * Ejecuta una operacion dentro de una transaccion con la optica fija.
     * Al salir, PostgreSQL descarta el valor de la variable.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAislado(string $opticaId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($opticaId, $operacion) {
            $this->establecer($opticaId);

            return $operacion();
        });
    }

    /**
     * Lee la variable de sesion sin pasar por `public.app_tenant_id()`, que
     * lanza excepcion cuando no hay contexto.
     *
     * Sirve para diagnostico: si devuelve null, ninguna politica RLS de la
     * base va a devolver filas.
     */
    public function opticaIdEnConexion(): ?string
    {
        $fila = DB::select('SELECT current_setting(?, true) AS valor', [self::VARIABLE]);

        if ($fila === [] || $fila[0]->valor === null || $fila[0]->valor === '') {
            return null;
        }

        return (string) $fila[0]->valor;
    }

    /**
     * Ejecuta una operacion sin contexto de optica.
     *
     * Solo para tareas que por definicion cruzan opticas: la creacion de una
     * optica nueva, el comando `optica:verificar-aislamiento` y la
     * comprobacion de salud. Cualquier consulta a una tabla protegida fallara
     * en RLS, que es el comportamiento correcto.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarSinOptica(Closure $operacion): mixed
    {
        return $operacion();
    }
}