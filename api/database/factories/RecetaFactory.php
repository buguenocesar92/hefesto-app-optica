<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoReceta;
use App\Models\Receta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receta>
 */
class RecetaFactory extends Factory
{
    protected $model = Receta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => 'R-'.fake()->unique()->numerify('#####'),
            'fecha_emision' => now()->toDateString(),

            /*
            | La fecha de vencimiento NACE EN NULL en la migracion y tambien
            | nace en null aca. No se pone un plazo de ejemplo: el plazo legal
            | por franja etaria no se pudo verificar, y un factory que
            | inventa plazos acabaria metiendolos en las pruebas.
            */
            'fecha_vencimiento' => null,
            'motivo_sin_vencimiento' => 'Plazo legal pendiente de verificacion contra la norma vigente.',
            'estado' => EstadoReceta::Emitida->value,
            'distancia_interpupilar_mm' => 63.0,
            'observaciones' => null,
        ];
    }

    /**
     * Receta con fecha de vencimiento cargada.
     */
    public function vigente(int $dias): static
    {
        return $this->state(fn (): array => [
            'fecha_vencimiento' => now()->addDays($dias)->toDateString(),
            'motivo_sin_vencimiento' => null,
        ]);
    }

    /**
     * Receta cuya fecha de vencimiento ya paso.
     */
    public function vencida(): static
    {
        return $this->state(fn (): array => [
            'fecha_emision' => now()->subYear()->toDateString(),
            'fecha_vencimiento' => now()->subMonth()->toDateString(),
            'motivo_sin_vencimiento' => null,
        ]);
    }

    public function anulada(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoReceta::Anulada->value]);
    }
}