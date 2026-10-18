<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Optica;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Todo lo que hace que un modelo pertenezca a una optica.
 *
 * ESTO ES LO QUE SOSTIENE EL AISLAMIENTO
 * --------------------------------------
 * Hay dos capas y conviene no confundirlas:
 *
 *   1. La CAPA DE BASE DE DATOS: la RLS de PostgreSQL (migracion
 *      2026_10_18_000015). Impide leer y escribir filas de otra optica aunque
 *      la consulta este mal escrita. Es la que manda.
 *
 *   2. Esta capa, que es de COMODIDAD Y DE COHERENCIA: `tenant_id` se llena
 *      solo al crear, y la relacion con la optica esta escrita una vez.
 *
 * La capa 2 no reemplaza a la capa 1. Si alguien escribiera un INSERT sin
 * `tenant_id`, la RLS lo rechazaria con WITH CHECK. El `creating` de este trait
 * evita el error antes de que llegue a la base, no despues.
 */
trait PerteneceAOptica
{
    /**
     * Fija la optica al crear la fila.
     *
     * Sale de la conexion, no de la sesion de Laravel: la optica de la
     * peticion ya esta fijada como `app.tenant_id` por
     * App\Http\Middleware\ResolverTenant, y esa es la misma que leen las
     * politicas. Si se leyera de otro lado, se podria crear una fila en una
     * optica distinta a la que la RLS cree.
     */
    public static function bootPerteneceAOptica(): void
    {
        static::creating(function ($modelo): void {
            if ($modelo->tenant_id !== null) {
                return;
            }

            $opticaId = app(ContextoTenant::class)->opticaIdEnConexion();

            if ($opticaId === null) {
                throw new \RuntimeException(
                    'No se puede crear un registro sin optica: la conexion no tiene app.tenant_id '
                    .'definida. La peticion no paso por App\Http\Middleware\ResolverTenant.'
                );
            }

            $modelo->tenant_id = $opticaId;
        });
    }

    /**
     * La optica a la que pertenece la fila.
     *
     * @return BelongsTo<Optica, static>
     */
    public function optica(): BelongsTo
    {
        return $this->belongsTo(Optica::class, 'tenant_id');
    }

    /**
     * Filtra por una optica concreta.
     *
     * NO hace falta para el aislamiento (eso lo pone la RLS). Sirve para el
     * comando `optica:verificar-aislamiento` y para los procesos por lotes, que
     * corren sin contexto de optica.
     *
     * @param  Builder<static>  $consulta
     * @return Builder<static>
     */
    public function scopeDeOptica(Builder $consulta, string $opticaId): Builder
    {
        return $consulta->where($consulta->qualifyColumn('tenant_id'), $opticaId);
    }
}