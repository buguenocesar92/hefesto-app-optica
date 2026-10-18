<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Meridiano;
use App\Enums\Ojo;
use App\Models\Graduacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Graduacion>
 */
class GraduacionFactory extends Factory
{
    protected $model = Graduacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ojo' => Ojo::Derecho->value,
            'meridiano' => Meridiano::Lejos->value,
            'esfera' => fake()->randomFloat(2, -10, 10),
            'cilindro' => 0,
            'eje' => 0,

            /*
            | La adicion va solo en el meridiano de cerca, y la migracion lo
            | prohibe en el de lejos con una restriccion CHECK. Por eso el
            | valor por omision depende del meridiano.
            */
            'adiccion' => null,
        ];
    }

    /**
     * Fila del meridiano de cerca, con la adicion que exige la base.
     */
    public function deCerca(): static
    {
        return $this->state(fn (): array => [
            'meridiano' => Meridiano::Cerca->value,
            'adiccion' => fake()->randomFloat(2, 0.5, 3.0),
        ]);
    }

    public function ojoDerecho(): static
    {
        return $this->state(fn (): array => ['ojo' => Ojo::Derecho->value]);
    }

    public function ojoIzquierdo(): static
    {
        return $this->state(fn (): array => ['ojo' => Ojo::Izquierdo->value]);
    }

    /**
     * Con astigmatismo. El eje tiene que ser distinto de cero cuando el
     * cilindro no lo es: la base lo comprueba con un CHECK.
     */
    public function conAstigmatismo(): static
    {
        return $this->state(fn (): array => [
            'cilindro' => fake()->randomFloat(2, -2.5, -0.25),
            'eje' => fake()->numberBetween(1, 180),
        ]);
    }
}