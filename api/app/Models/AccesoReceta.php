<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de la bitacora de accesos a recetas y graduaciones.
 *
 * NO HAY MODELO DE LECTURA COMPLETA
 * --------------------------------
 * La interfaz de administracion la lee, pero no hay endpoint para crearla a
 * mano: los registros los escribe App\Services\Recetas\BitacoraRecetas en una
 * conexion aparte, para que sobrevivan a la reversa de una peticion denegada.
 * Ver docs/07.
 *
 * NO HAY VALORES DE GRADUACION ACA
 * -------------------------------
 * Solo queda el id del recurso, quien lo pidio, cuando y si se le permitio.
 * La bitacora no puede convertirse en una segunda copia de la informacion de
 * salud sin proteccion.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $usuario_id
 * @property string $recurso
 * @property string|null $recurso_id
 * @property string $accion
 * @property bool $permitido
 * @property string|null $motivo
 * @property string|null $ip
 * @property string|null $agente
 */
class AccesoReceta extends Model
{
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'accesos_recetas';

    protected $fillable = [
        'usuario_id',
        'recurso',
        'recurso_id',
        'accion',
        'permitido',
        'motivo',
        'ip',
        'agente',
    ];

    protected function casts(): array
    {
        return [
            'permitido' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}