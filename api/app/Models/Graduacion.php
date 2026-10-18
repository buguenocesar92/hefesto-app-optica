<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Meridiano;
use App\Enums\Ojo;
use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila de graduacion: un ojo, un meridiano.
 *
 * POR QUE UNA FILA Y NO UNA COLUMNA POR MERIDIANO
 * ----------------------------------------------
 * Una receta completa son 4 filas de esta tabla: dos ojos por dos meridianos.
 * La alternativa (columnas `esfera_lejes`, `esfera_cerca`, `eje_cerca`...) se
 * ve mas comoda y es mala idea: obliga a repetir el nombre del meridiano en
 * cada columna, y agregar un meridiano despues seria otra migracion sobre una
 * tabla que ya tiene datos de pacientes. Ver la migracion de `graduaciones`.
 *
 * LA ESTRUCTURA LA IMPONE LA BASE
 * ------------------------------
 * Las restricciones CHECK de la migracion garantizan que no exista una fila sin
 * meridiano valido, con un eje fuera de rango, con un eje distinto de cero
 * teniendo cilindro cero, o con la adicion cargada en el meridiano equivocado.
 * Eso no depende de que la aplicacion se porte bien.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $receta_id
 * @property Ojo $ojo
 * @property Meridiano $meridiano
 * @property float $esfera
 * @property float $cilindro
 * @property int $eje
 * @property float|null $adiccion
 */
class Graduacion extends Model
{
    /** @use HasFactory<\Database\Factories\GraduacionFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'graduaciones';

    protected $fillable = [
        'receta_id',
        'ojo',
        'meridiano',
        'esfera',
        'cilindro',
        'eje',
        'adiccion',
    ];

    protected function casts(): array
    {
        return [
            'ojo' => Ojo::class,
            'meridiano' => Meridiano::class,
            'esfera' => 'float',
            'cilindro' => 'float',
            'eje' => 'integer',
            'adiccion' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Receta, $this>
     */
    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class);
    }

    /**
     * Si esta fila tiene astigmatismo.
     *
     * Cuando no lo tiene, el eje vale cero y no significa nada.
     */
    public function tieneAstigmatismo(): bool
    {
        return abs((float) $this->cilindro) > 0.0001;
    }

    /**
     * La graduacion en la forma en que la guarda `pedido_items`.
     *
     * Es la foto de lo que se pidio al laboratorio, y la base contra la que se
     * compara lo que llega de vuelta en el control de calidad.
     *
     * @return array<string, mixed>
     */
    public function aComponentes(): array
    {
        $valores = [
            'esfera' => (float) $this->esfera,
            'cilindro' => (float) $this->cilindro,
            'eje' => (int) $this->eje,
        ];

        if ($this->meridiano->admiteAdicion() && $this->adiccion !== null) {
            $valores['adiccion'] = (float) $this->adiccion;
        }

        return $valores;
    }

    /**
     * Una fila como se muestra en la interfaz: con el valor en notacion
     * chilena, que es como la lee y la escribe el optico.
     *
     * @return array<string, mixed>
     */
    public function aFilaLegible(): array
    {
        $formato = static fn (float $valor): string => number_format($valor, 2, ',', '.');

        $fila = [
            'ojo' => $this->ojo->value,
            'ojo_etiqueta' => $this->ojo->etiqueta(),
            'meridiano' => $this->meridiano->value,
            'meridiano_etiqueta' => $this->meridiano->etiqueta(),
            'esfera' => $formato((float) $this->esfera),
            'cilindro' => $formato((float) $this->cilindro),
            'eje' => (int) $this->eje,
        ];

        if ($this->meridiano->admiteAdicion()) {
            $fila['adiccion'] = $this->adiccion === null
                ? null
                : $formato((float) $this->adiccion);
        }

        return $fila;
    }
}