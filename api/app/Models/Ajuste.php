<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAjuste;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ajuste posterior a la entrega.
 *
 * Un armazon que aprieta, que se desliza o que le queda alto no es un defecto
 * de fabricacion: es una entrega que todavia no termino.
 *
 * NO HAY COLUMNA DE DIAS DE GARANTIA
 * ---------------------------------
 * Los dias de garantia sugeridos estan en
 * `config('optica.entrega.dias_de_garantia_sugeridos')` y NACEN EN NULL porque
 * son una decision comercial de la optica y no un plazo legal verificable. El
 * ajuste se puede registrar en cualquier momento. Ver docs/07.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $entrega_id
 * @property \Illuminate\Support\Carbon $fecha
 * @property string $motivo
 * @property string|null $descripcion
 * @property EstadoAjuste $estado
 * @property bool $lente_sustituido
 * @property string|null $observaciones
 */
class Ajuste extends Model
{
    /** @use HasFactory<\Database\Factories\AjusteFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'ajustes';

    protected $fillable = [
        'entrega_id',
        'fecha',
        'motivo',
        'descripcion',
        'estado',
        'lente_sustituido',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoAjuste::class,
            'lente_sustituido' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Entrega, $this>
     */
    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    /**
     * El paciente, a traves de la entrega.
     */
    public function paciente(): ?Paciente
    {
        return $this->entrega?->paciente;
    }

    /**
     * Ajustes que siguen abiertos.
     *
     * @param  Builder<Ajuste>  $consulta
     * @return Builder<Ajuste>
     */
    public function scopeAbiertos(Builder $consulta): Builder
    {
        return $consulta->whereIn('estado', array_map(
            static fn (EstadoAjuste $caso): string => $caso->value,
            EstadoAjuste::abiertos()
        ));
    }

    /**
     * El motivo mas frecuente en los ajustes.
     *
     * Sirve para que la optica vea que le falla mas a menudo: si casi todos los
     * ajustes son de un tipo, el problema es del armazon que se eligio.
     */
    public static function motivoMasFrecuente(): ?string
    {
        $fila = static::query()
            ->select('motivo')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('motivo')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        if ($fila === null) {
            return null;
        }

        return (string) $fila->getAttribute('motivo');
    }
}