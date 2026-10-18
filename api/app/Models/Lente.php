<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Lente del catalogo.
 *
 * LO QUE HACE FALTA Y NO ESTA PUESTO
 * ----------------------------------
 * `indice_refraccion` y `diametro_corte_mm` son NULL en un catalogo recien
 * cargado. No es un olvido: los valores de indice que ofrece cada fabricante
 * cambian segun el modelo y el mercado, y el diametro de mecanizado depende de
 * que pastilla tenga disponible cada laboratorio.
 *
 * Mientras `diametro_corte_mm` sea NULL, la verificacion de compatibilidad
 * devuelve `no_verificable`: el sistema no puede afirmar que el lente entra en
 * el armazon si no sabe de que diametro se corta. Ver docs/06.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $marca
 * @property string|null $material
 * @property float|null $indice_refraccion
 * @property array<int, string>|null $tratamientos
 * @property float|null $diametro_corte_mm
 * @property bool $es_progresivo
 * @property bool $activo
 */
class Lente extends Model
{
    /** @use HasFactory<\Database\Factories\LenteFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'lentes';

    protected $fillable = [
        'codigo',
        'nombre',
        'marca',
        'material',
        'indice_refraccion',
        'tratamientos',
        'diametro_corte_mm',
        'es_progresivo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'indice_refraccion' => 'float',
            'tratamientos' => 'array',
            'diametro_corte_mm' => 'float',
            'es_progresivo' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Si el catalogo tiene lo necesario para verificar compatibilidad.
     */
    public function tieneDiametroDeCorte(): bool
    {
        return $this->diametro_corte_mm !== null;
    }

    /**
     * Que le falta al registro para estar completo.
     *
     * @return list<string>
     */
    public function datosIncompletos(): array
    {
        $faltantes = [];

        if ($this->indice_refraccion === null) {
            $faltantes[] = 'indice_refraccion';
        }

        if ($this->diametro_corte_mm === null) {
            $faltantes[] = 'diametro_corte_mm';
        }

        return $faltantes;
    }

    /**
     * @param  Builder<Lente>  $consulta
     * @return Builder<Lente>
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
                ->orWhere('material', 'ilike', '%'.$termino.'%');
        });
    }

    /**
     * @param  Builder<Lente>  $consulta
     * @return Builder<Lente>
     */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }
}