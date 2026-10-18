<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultadoControlCalidad;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Control de calidad de un pedido, al recibirlo del laboratorio.
 *
 * ESTA TABLA ES LA QUE EVITA EL RECLAMO
 * -------------------------------------
 * Si el laboratorio manda la graduacion equivocada, el momento de enterarse es
 * ANTES de entregarle los lentes al cliente. Un `no_conforme` bloquea la
 * entrega: por eso existe el registro.
 *
 * UN CONTROL POR PEDIDO
 * ---------------------
 * El indice unico en `pedido_laboratorio_id` obliga a esto. Si un control da
 * `no_conforme`, la correccion es un pedido NUEVO, no un segundo control sobre
 * el mismo pedido. Asi queda un veredicto por pedido y nadie tiene que decidir
 * cual de los dos vale.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $pedido_laboratorio_id
 * @property string $revisado_por_usuario_id
 * @property ResultadoControlCalidad $resultado
 * @property \Illuminate\Support\Carbon $fecha_revision
 * @property list<array<string, mixed>>|null $diferencias
 * @property string|null $observaciones
 */
class ControlCalidad extends Model
{
    /** @use HasFactory<\Database\Factories\ControlCalidadFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'controles_calidad';

    protected $fillable = [
        'pedido_laboratorio_id',
        'revisado_por_usuario_id',
        'resultado',
        'fecha_revision',
        'diferencias',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'resultado' => ResultadoControlCalidad::class,
            'fecha_revision' => 'date',
            'diferencias' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PedidoLaboratorio, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoLaboratorio::class, 'pedido_laboratorio_id');
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisado_por_usuario_id');
    }

    /**
     * Cuantas diferencias se encontraron.
     */
    public function cantidadDeDiferencias(): int
    {
        return is_array($this->diferencias) ? count($this->diferencias) : 0;
    }

    /**
     * Las diferencias agrupadas por ojo, para mostrarlas en la interfaz.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function diferenciasPorOjo(): array
    {
        $porOjo = [];

        foreach ((array) $this->diferencias as $diferencia) {
            $ojo = (string) ($diferencia['ojo'] ?? 'desconocido');

            $porOjo[$ojo][] = $diferencia;
        }

        return $porOjo;
    }
}