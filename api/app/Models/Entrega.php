<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAjuste;
use App\Enums\EstadoEntrega;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entrega de lentes al cliente.
 *
 * ENTREGAR NO ES EL FINAL
 * ----------------------
 * Un lente recien entregado casi nunca esta bien ajustado la primera vez. Por
 * eso la entrega tiene estado, y por eso existe `ajustes` al lado: la entrega
 * abre la cola de ajustes, no la cierra.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $pedido_laboratorio_id
 * @property string $paciente_id
 * @property EstadoEntrega $estado
 * @property \Illuminate\Support\Carbon $fecha_registro
 * @property \Illuminate\Support\Carbon|null $fecha_entrega
 * @property string|null $recibido_por
 * @property string|null $observaciones
 */
class Entrega extends Model
{
    /** @use HasFactory<\Database\Factories\EntregaFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'entregas';

    protected $fillable = [
        'pedido_laboratorio_id',
        'paciente_id',
        'estado',
        'fecha_registro',
        'fecha_entrega',
        'recibido_por',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoEntrega::class,
            'fecha_registro' => 'date',
            'fecha_entrega' => 'date',
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
     * @return BelongsTo<Paciente, $this>
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * @return HasMany<Ajuste, $this>
     */
    public function ajustes(): HasMany
    {
        return $this->hasMany(Ajuste::class);
    }

    /**
     * Dias que han pasado desde la entrega.
     *
     * Con la fecha en NULL devuelve 0: la entrega todavia no se hizo.
     */
    public function diasDesdeEntrega(): int
    {
        if ($this->fecha_entrega === null) {
            return 0;
        }

        return (int) $this->fecha_entrega->startOfDay()->diffInDays(now()->startOfDay(), false);
    }

    /**
     * Si esta entrega todavia admite ajustes.
     */
    public function admiteAjuste(): bool
    {
        return in_array($this->estado, EstadoEntrega::queAdmitenAjuste(), true);
    }

    /**
     * @param  Builder<Entrega>  $consulta
     * @return Builder<Entrega>
     */
    public function scopeEntregadas(Builder $consulta): Builder
    {
        return $consulta->where('estado', EstadoEntrega::Entregada->value);
    }
}