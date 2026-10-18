<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Optica;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Optica>
 */
class OpticaFactory extends Factory
{
    protected $model = Optica::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->company().' Optica';

        return [
            'nombre' => $nombre,
            'slug' => fake()->unique()->slug(2),
            'correlacion_identidad' => fake()->unique()->slug(3),
            'zona_horaria' => 'America/Santiago',
            'activo' => true,
        ];
    }

    /**
     * Optica desactivada, para probar el filtro del selector de ingreso.
     */
    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}