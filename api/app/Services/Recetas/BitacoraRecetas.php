<?php

declare(strict_types=1);

namespace App\Services\Recetas;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bitacora de accesos a recetas y graduaciones.
 *
 * POR QUE UNA CONEXION APARTE
 * ---------------------------
 * Una peticion denegada termina con su transaccion REVERTIDA: la politica no
 * dejo leer la receta, y Eloquent no encontro nada que escribir. El registro
 * de "esta persona quiso ver esto y no pudo" tendria que guardarse en la misma
 * transaccion que se acaba de revertir, o sea que no se guardaria nunca.
 *
 * Por eso esta clase escribe en la conexion `auditoria`, que es independiente
 * de la de la peticion. Un `INSERT` en PostgreSQL no depende de la transaccion
 * que este abierta, asi que el registro sobrevive a la reversa.
 *
 * Ver config/database.php.
 *
 * QUE SE REGISTRA
 * ---------------
 * Tanto lo permitido como lo denegado. Un intento denegado es tan
 * informativo como uno permitido: es la senal de que alguien esta probando el
 * sistema con el rol equivocado, y es la unica forma de detectarlo despues.
 *
 * QUE NO SE HACE
 * -------------
 * No se guarda el CONTENIDO de la receta ni ningun valor de graduacion en la
 * bitacora. Solo queda el id del recurso, quien lo pidio, cuando y si se le
 * permitio. La bitacora no puede convertirse en una segunda copia de la
 * informacion de salud sin proteccion. Ver docs/07.
 */
class BitacoraRecetas
{
    /**
     * Registra un intento de acceso a una receta o a una graduacion.
     *
     * @param  string  $recurso  `receta` o `graduacion`.
     * @param  string|null  $recursoId
     * @param  string  $accion  `ver`, `crear`, `actualizar` o `eliminar`.
     * @param  bool  $permitido
     * @param  string|null  $motivo  Por que se denego, si se denego.
     * @param  Request|null  $peticion  Para la IP y el agente.
     */
    public function registrar(
        string $recurso,
        ?string $recursoId,
        string $accion,
        bool $permitido,
        ?string $motivo = null,
        ?Request $peticion = null,
        ?string $usuarioId = null,
    ): void {
        try {
            DB::connection('auditoria')->table('accesos_recetas')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->opticaDeLaPeticion($peticion),
                'usuario_id' => $usuarioId,
                'recurso' => $recurso,
                'recurso_id' => $recursoId,
                'accion' => $accion,
                'permitido' => $permitido,
                'motivo' => $motivo,
                'ip' => $peticion?->ip(),
                'agente' => $peticion === null ? null : $this->agente($peticion),
                'ocurrido_en' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            /*
            | Si la bitacora falla, la peticion CONTINUA.
            |
            | Un registro de bitacora que tumba el sistema es un problema mas
            | grave que un registro que falta. Y bloquear el acceso a una
            | receta por fallar la bitacora seria dejar a la optica sin poder
            | trabajar por un problema de disco.
            |
            | El fallo queda en el registro de Laravel, que si va a `stderr`.
            */
            report($e);
        }
    }

    /**
     * Registra varios accesos de una vez.
     *
     * @param  list<array{recurso: string, recurso_id: string|null, accion: string, permitido: bool, motivo?: string|null}>  $accesos
     */
    public function registrarVarios(array $accesos, ?Request $peticion = null, ?string $usuarioId = null): void
    {
        foreach ($accesos as $acceso) {
            $this->registrar(
                $acceso['recurso'],
                $acceso['recurso_id'] ?? null,
                $acceso['accion'],
                $acceso['permitido'],
                $acceso['motivo'] ?? null,
                $peticion,
                $usuarioId,
            );
        }
    }

    /**
     * La optica de la peticion.
     *
     * Si todavia no se sabe (por ejemplo, un intento sobre un recurso que no
     * existe), se deja en NULL. Un registro sin optica es preferible a un
     * registro con una optica inventada: la columna tiene Foreign Key, y mentir
     * ahi seria peor que dejar el hueco.
     */
    private function opticaDeLaPeticion(?Request $peticion): ?string
    {
        if ($peticion === null) {
            return null;
        }

        $usuario = $peticion->user();

        if ($usuario === null) {
            return null;
        }

        return $usuario->tenant_id;
    }

    /**
     * Agente del navegador, recortado.
     *
     * El campo es de 255 caracteres y un agente puede venir con miles: sin
     * recortar, un INSERT largo y la peticion cae.
     */
    private function agente(Request $peticion): ?string
    {
        $agente = $peticion->userAgent();

        if ($agente === null) {
            return null;
        }

        return substr($agente, 0, 255);
    }
}