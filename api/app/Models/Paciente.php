<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAOptica;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persona que usa lentes.
 *
 * NO TIENE GRADUACIONES
 * ---------------------
 * Las graduaciones viven en `recetas` y `graduaciones`, no aca. La separacion
 * no es estetica: permite que el acceso a la graduacion se proteja y se
 * audite por separado del acceso a la ficha del paciente, y que borrar una
 * ficha no arrastre la historia optica. Ver docs/02 y docs/07.
 *
 * `fecha_nacimiento` es nullable y es lo que decide la franja de vigencia de
 * la receta. Si falta, el calculo devuelve "sin plazo" y avisa, en vez de
 * adivinar una edad. Ver App\Services\Recetas\CalculadoraDeVencimiento.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $rut
 * @property string $nombres
 * @property string $apellidos
 * @property \Illuminate\Support\Carbon|null $fecha_nacimiento
 * @property string|null $telefono
 * @property string|null $correo
 * @property bool $activo
 */
class Paciente extends Model
{
    /** @use HasFactory<\Database\Factories\PacienteFactory> */
    use HasFactory;
    use HasUuids;
    use PerteneceAOptica;

    protected $table = 'pacientes';

    protected $fillable = [
        'rut',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'telefono',
        'correo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Receta, $this>
     */
    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class);
    }

    /**
     * Nombre completo, como se muestra en las listas.
     */
    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /**
     * Busqueda por nombre o por RUT.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Paciente>  $consulta
     * @return \Illuminate\Database\Eloquent\Builder<Paciente>
     */
    public function scopeBuscar($consulta, string $termino)
    {
        $termino = trim($termino);

        if ($termino === '') {
            return $consulta;
        }

        $conSignos = str_replace(['.', '-', ' '], '', $termino);

        return $consulta->where(function ($interna) use ($termino, $conSignos): void {
            $interna->where('nombres', 'ilike', '%'.$termino.'%')
                ->orWhere('apellidos', 'ilike', '%'.$termino.'%')
                ->orWhere('nombres', 'ilike', '%'.$conSignos.'%')
                ->orWhere('apellidos', 'ilike', '%'.$conSignos.'%');

            if (preg_match('/^\d{7,8}$/', $conSignos) === 1) {
                $interna->orWhere('rut', 'ilike', '%'.$conSignos.'%');
            }
        });
    }
}