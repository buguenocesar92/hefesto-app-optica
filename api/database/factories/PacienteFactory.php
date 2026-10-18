<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rut' => fake()->numerify('##.###.###-#'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'telefono' => fake()->numerify('+56 9 #### ####'),
            'correo' => fake()->unique()->safeEmail(),
            'activo' => true,
        ];
    }

    /**
     * Nino, para probar la franja etaria mas joven del vencimiento.
     */
    public function nino(): static
    {
        return $this->state(fn (): array => [
            'fecha_nacimiento' => fake()->dateTimeBetween('-12 years', 'now'),
        ]);
    }

    /**
     * Sin fecha de nacimiento, que es una de las razones por las que el
     * sistema devuelve "sin plazo".
     */
    public function sinFechaNacimiento(): static
    {
        return $this->state(fn (): array => ['fecha_nacimiento' => null]);
    }
}