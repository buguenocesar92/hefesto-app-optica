<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Armazon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Armazon>
 */
class ArmazonFactory extends Factory
{
    protected $model = Armazon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'AR-'.fake()->unique()->numerify('#####'),
            'nombre' => fake()->randomElement(['Clasico', 'Liviano', 'Panto', 'Aviador', 'Cuadrado']),
            'marca' => fake()->company(),
            'modelo' => strtoupper(fake()->bothify('??###')),
            'material' => fake()->randomElement(['Acetato', 'Titanio', 'Metal semirrigido']),
            'color' => fake()->randomElement(['Negro', 'Carey', 'Dorado', 'Plateado']),

            /*
            | Medidas de caja plausibles de un armazon de verdad, en
            | milimetros. No son valores normativos: son medidas de ejemplo
            | para que las pruebas tengan numeros con los que trabajar, y no
            | una especificacion que el sistema imponga.
            */
            'ancho_varilla_mm' => fake()->randomFloat(1, 46, 56),
            'puente_mm' => fake()->randomFloat(1, 14, 22),
            'alto_mm' => fake()->randomFloat(1, 32, 44),
            'activo' => true,
        ];
    }

    /**
     * Armazon con medidas exactas, para las pruebas de compatibilidad.
     *
     * @param  array{ancho?: float, puente?: float, alto?: float}  $medidas
     */
    public function conMedidas(float $ancho = 52.0, float $puente = 18.0, float $alto = 40.0): static
    {
        return $this->state(fn (): array => [
            'ancho_varilla_mm' => $ancho,
            'puente_mm' => $puente,
            'alto_mm' => $alto,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}