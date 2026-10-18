<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Armazon del catalogo.
 *
 * LAS MEDIDAS DE CAJA SON EL DATO
 * ------------------------------
 * De un armazon no interesa el color: interesan las tres medidas que estan
 * grabadas en la patilla y que dicen de que tamano es el lente que entra.
 * Sin ellas el armazon no sirve para el calculo de compatibilidad, y por eso
 * en la migracion son NOT NULL: es preferible no cargar el armazon antes que
 * cargarlo sin medidas.
 *
 * `activo` es distinto de borrar. Un armazon que ya se pidio a un laboratorio
 * no se borra (el pedido lo referencia con `restrictOnDelete`): se desactiva.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $marca
 * @property string|null $modelo
 * @property string|null $material
 * @property string|null $color
 * @property float $ancho_varilla_mm
 * @property float $puente_mm
 * @property float $alto_mm
 * @property bool $activo
 */
class Armazon extends Model
{
    /** @use HasFactory<\Database\Factories\ArmazonFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'armazones';

    protected $fillable = [
        'codigo',
        'nombre',
        'marca',
        'modelo',
        'material',
        'color',
        'ancho_varilla_mm',
        'puente_mm',
        'alto_mm',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'ancho_varilla_mm' => 'float',
            'puente_mm' => 'float',
            'alto_mm' => 'float',
            'activo' => 'boolean',
        ];
    }

    /**
     * El ancho total de la caja, que es la suma del ancho de un lente y el
     * puente.
     *
     * Es el numero con el que arranca el calculo de compatibilidad.
     */
    public function anchoDeCaja(): float
    {
        return (float) $this->ancho_varilla_mm + (float) $this->puente_mm;
    }

    /**
     * Busca por codigo, marca o nombre.
     *
     * @param  Builder<Armazon>  $consulta
     * @return Builder<Armazon>
     */
    public function scopeBuscar($consulta, string $termino)
    {
        $termino = trim($termino);

        if ($termino === '') {
            return $consulta;
        }

        return $consulta->where(function ($interna) use ($termino): void {
            $interna->where('codigo', 'ilike', '%'.$termino.'%')
                ->orWhere('nombre', 'ilike', '%'.$termino.'%')
                ->orWhere('marca', 'ilike', '%'.$termino.'%')
                ->orWhere('modelo', 'ilike', '%'.$termino.'%');
        });
    }

    /**
     * @param  Builder<Armazon>  $consulta
     * @return Builder<Armazon>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}