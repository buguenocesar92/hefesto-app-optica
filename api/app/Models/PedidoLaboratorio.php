<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPedido;
use App\Enums\VeredictoCompatibilidad;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pedido a laboratorio.
 *
 * LA OPTICA NO FABRICA: ENCARGA
 * -----------------------------
 * Por eso el pedido tiene estados que son del mundo externo y una FECHA
 * PROMETIDA que la negocia la optica con su laboratorio.
 *
 * `fecha_prometida` NACE EN NULL. El plazo es COMERCIAL y no se pudo verificar
 * ningun valor, asi que no se escribe ninguno.
 * `config('optica.laboratorio.dias_promesa_por_omision')` tampoco tiene valor,
 * y sirve como propuesta, no como plazo. Ver docs/06.
 *
 * `compatibilidad_resultado` y `compatibilidad_desglose` guardan el veredicto
 * del momento en que se creo el pedido, con el calculo completo. Se guardan
 * incluso cuando el resultado es `no_verificable`, porque el calculo geometrico
 * que si se pudo hacer queda registrado.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $codigo
 * @property string $paciente_id
 * @property string|null $receta_id
 * @property string|null $armazon_id
 * @property string|null $lente_id
 * @property string|null $laboratorio
 * @property EstadoPedido $estado
 * @property \Illuminate\Support\Carbon|null $fecha_envio
 * @property \Illuminate\Support\Carbon|null $fecha_prometida
 * @property \Illuminate\Support\Carbon|null $fecha_recepcion
 * @property string $origen_fecha_prometida
 * @property string|null $compatibilidad_resultado
 * @property array<string, mixed>|null $compatibilidad_desglose
 * @property string|null $notas
 */
class PedidoLaboratorio extends Model
{
    /** @use HasFactory<\Database\Factories\PedidoLaboratorioFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'pedidos_laboratorio';

    protected $fillable = [
        'codigo',
        'paciente_id',
        'receta_id',
        'armazon_id',
        'lente_id',
        'laboratorio',
        'estado',
        'fecha_envio',
        'fecha_prometida',
        'fecha_recepcion',
        'origen_fecha_prometida',
        'compatibilidad_resultado',
        'compatibilidad_desglose',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'fecha_envio' => 'date',
            'fecha_prometida' => 'date',
            'fecha_recepcion' => 'date',
            'compatibilidad_resultado' => VeredictoCompatibilidad::class,
            'compatibilidad_desglose' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Paciente, $this>
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * @return BelongsTo<Receta, $this>
     */
    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class);
    }

    /**
     * @return BelongsTo<Armazon, $this>
     */
    public function armazon(): BelongsTo
    {
        return $this->belongsTo(Armazon::class);
    }

    /**
     * @return BelongsTo<Lente, $this>
     */
    public function lente(): BelongsTo
    {
        return $this->belongsTo(Lente::class);
    }

    /**
     * @return HasMany<PedidoItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    /**
     * @return HasOne<ControlCalidad, $this>
     */
    public function controlCalidad(): HasOne
    {
        return $this->hasOne(ControlCalidad::class);
    }

    /**
     * @return HasOne<Entrega, $this>
     */
    public function entrega(): HasOne
    {
        return $this->hasOne(Entrega::class);
    }

    /**
     * Si el pedido quedo atrasado respecto de la fecha prometida.
     *
     * Con la fecha en NULL devuelve `false`: sin promesa no hay atraso que
     * medir. Devolver `true` seria inventar un incumplimiento.
     */
    public function estaAtrasado(): bool
    {
        if ($this->fecha_prometida === null) {
            return false;
        }

        if (in_array($this->estado, EstadoPedido::cerrados(), true)) {
            return false;
        }

        return $this->fecha_prometida->isBefore(now()->startOfDay());
    }

    /**
     * Dias que faltan para la fecha prometida. Null si no hay promesa.
     */
    public function diasParaPromesa(): ?int
    {
        if ($this->fecha_prometida === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->fecha_prometida->startOfDay(), false);
    }

    /**
     * Si el pedido se puede entregar: tiene que haber pasado el control de
     * calidad y no puede haber salido no conforme.
     */
    public function puedeEntregarse(): bool
    {
        $control = $this->controlCalidad;

        if ($control === null) {
            return false;
        }

        return $control->resultado->permiteEntregar();
    }

    /**
     * @param  Builder<PedidoLaboratorio>  $consulta
     * @return Builder<PedidoLaboratorio>
     */
    public function scopeEnCurso(Builder $consulta): Builder
    {
        return $consulta->whereIn('estado', array_map(
            static fn (EstadoPedido $caso): string => $caso->value,
            EstadoPedido::enCurso()
        ));
    }

    /**
     * @param  Builder<PedidoLaboratorio>  $consulta
     * @return Builder<PedidoLaboratorio>
     */
    public function scopeRecibidos(Builder $consulta): Builder
    {
        return $consulta->where('estado', EstadoPedido::Recibido->value);
    }
}