<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoReceta;
use App\Models\Concerns\PerteneceAOptica;
use App\Services\Recetas\CalculadoraDeVencimiento;
use App\Services\Recetas\Vencimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Receta optica.
 *
 * UNA RECETA ES UN DOCUMENTO, NO UNA MEDIDA
 * -----------------------------------------
 * Se emite en una fecha, tiene un plazo de vigencia y sirve para despachar
 * lentes. Por eso tiene numero, fecha de emision y estado, y por eso las
 * graduaciones viven aparte, en `graduaciones`.
 *
 * LO QUE NO ESTA GUARDADO Y POR QUE
 * ---------------------------------
 * El estado de vigencia NO es una columna. `vigente` y `vencida` se CALCULAN
 * cada vez que se preguntan, con App\Services\Recetas\CalculadoraDeVencimiento.
 * Guardar eso como columna seria duplicar un dato que se puede calcular y que
 * se desactualizaria solo.
 *
 * `fecha_vencimiento` NACE EN NULL porque el plazo legal por franja etaria es
 * norma sanitaria chilena y NO se pudo verificar en la maquina donde se escribio
 * esta aplicacion. Ver docs/06.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $paciente_id
 * @property string $optometrista_id
 * @property string $numero
 * @property \Illuminate\Support\Carbon $fecha_emision
 * @property \Illuminate\Support\Carbon|null $fecha_vencimiento
 * @property string|null $motivo_sin_vencimiento
 * @property EstadoReceta $estado
 * @property float|null $distancia_interpupilar_mm
 * @property string|null $observaciones
 */
class Receta extends Model
{
    /** @use HasFactory<\Database\Factories\RecetaFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'recetas';

    protected $fillable = [
        'paciente_id',
        'optometrista_id',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'motivo_sin_vencimiento',
        'estado',
        'distancia_interpupilar_mm',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'estado' => EstadoReceta::class,
            'distancia_interpupilar_mm' => 'float',
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
     * @return BelongsTo<Usuario, $this>
     */
    public function optometrista(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'optometrista_id');
    }

    /**
     * @return HasMany<Graduacion, $this>
     */
    public function graduaciones(): HasMany
    {
        return $this->hasMany(Graduacion::class);
    }

    /**
     * @return HasMany<PedidoLaboratorio, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(PedidoLaboratorio::class);
    }

    /**
     * El estado de vigencia, ya calculado.
     */
    public function vencimiento(?\Carbon\CarbonImmutable $hoy = null): Vencimiento
    {
        return app(CalculadoraDeVencimiento::class)->calcular($this, $hoy);
    }

    /**
     * Si la receta sirve para despachar lentes hoy.
     */
    public function permiteDespachar(): bool
    {
        return $this->vencimiento()->permiteDespachar();
    }

    /**
     * Recetas que ya pasaron su fecha de vencimiento.
     *
     * @param  Builder<Receta>  $consulta
     * @return Builder<Receta>
     */
    public function scopeVencidas(Builder $consulta): Builder
    {
        return $consulta->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString());
    }

    /**
     * Recetas que vencen dentro del aviso configurado.
     *
     * @param  Builder<Receta>  $consulta
     * @return Builder<Receta>
     */
    public function scopePorVencer(Builder $consulta): Builder
    {
        $diasAviso = (int) config('optica.receta.dias_de_aviso', 30);

        return $consulta->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
            ->whereDate('fecha_vencimiento', '<=', now()->addDays($diasAviso)->toDateString());
    }

    /**
     * Recetas que no tienen fecha de vencimiento.
     *
     * Son las que el sistema no puede decir si estan vigentes, y las que la
     * interfaz tiene que marcar como pendientes de verificacion. Ver docs/06.
     *
     * @param  Builder<Receta>  $consulta
     * @return Builder<Receta>
     */
    public function scopeSinVencimiento(Builder $consulta): Builder
    {
        return $consulta->whereNull('fecha_vencimiento');
    }

    /**
     * Las graduaciones ordenadas como se muestran: por ojo y por meridiano.
     *
     * @return list<Graduacion>
     */
    public function graduacionesOrdenadas(): array
    {
        return $this->graduaciones()
            ->orderByRaw("CASE WHEN ojo = 'derecho' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN meridiano = 'lejos' THEN 0 ELSE 1 END")
            ->get()
            ->all();
    }
}