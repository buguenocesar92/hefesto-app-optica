<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lente>
 */
class LenteFactory extends Factory
{
    protected $model = Lente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'LE-'.fake()->unique()->numerify('#####'),
            'nombre' => fake()->randomElement(['Monofocal', 'Bifocal', 'Progresivo']),
            'marca' => fake()->company(),
            'material' => fake()->randomElement(['Cristal', 'Resina', 'Policarbonato']),

            /*
            | El indice de refraccion y el diametro de corte NACEN EN NULL.
            |
            | Los valores de indice que ofrece cada fabricante cambian segun
            | el modelo y el mercado, y el diametro de mecanizado depende de
            | que pastilla tenga cada laboratorio. No se puede verificar
            | ninguno aca, asi que no se escribe. Ver docs/06 y docs/10.
            */
            'indice_refraccion' => null,
            'tratamientos' => ['Antirreflejo'],
            'diametro_corte_mm' => null,
            'es_progresivo' => false,
            'activo' => true,
        ];
    }

    /**
     * Lente con diametro de mecanizado cargado.
     *
     * @param  float|null  $indice  Si se pasa, se usa como indice de
     *                              refraccion. Por omision queda en null.
     */
    public function mecanizable(float $diametro = 75.0, ?float $indice = null): static
    {
        return $this->state(fn (): array => [
            'diametro_corte_mm' => $diametro,
            'indice_refraccion' => $indice,
        ]);
    }

    public function progresivo(): static
    {
        return $this->state(fn (): array => ['es_progresivo' => true]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}