<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolOptica;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'rol' => RolOptica::Mostrador->value,
            'activo' => true,
            'ultimo_ingreso_en' => null,
        ];
    }

    public function optometrista(): static
    {
        return $this->state(fn (): array => ['rol' => RolOptica::Optometrista->value]);
    }

    public function mostrador(): static
    {
        return $this->state(fn (): array => ['rol' => RolOptica::Mostrador->value]);
    }

    public function administrador(): static
    {
        return $this->state(fn (): array => ['rol' => RolOptica::Administrador->value]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}