<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PedidoLaboratorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoLaboratorio>
 */
class PedidoLaboratorioFactory extends Factory
{
    protected $model = PedidoLaboratorio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'P-'.fake()->unique()->numerify('#####'),
            'laboratorio' => fake()->company(),

            /*
            | La fecha prometida NACE EN NULL. El plazo de un laboratorio es
            | COMERCIAL y no se pudo verificar ningun valor, asi que no se
            | escribe ninguno. Ver docs/06.
            */
            'fecha_prometida' => null,
            'origen_fecha_prometida' => 'pendiente',
            'compatibilidad_resultado' => null,
            'compatibilidad_desglose' => null,
            'notas' => null,
        ];
    }

    public function enviado(): static
    {
        return $this->state(fn (): array => [
            'estado' => \App\Enums\EstadoPedido::Enviado->value,
            'fecha_envio' => now()->toDateString(),
        ]);
    }

    public function recibido(): static
    {
        return $this->state(fn (): array => [
            'estado' => \App\Enums\EstadoPedido::Recibido->value,
            'fecha_envio' => now()->subWeek()->toDateString(),
            'fecha_recepcion' => now()->toDateString(),
        ]);
    }

    /**
     * Pedido con la promesa puesta a mano, para las pruebas de atraso.
     */
    public function prometidoEn(int $dias): static
    {
        return $this->state(fn (): array => [
            'fecha_prometida' => now()->addDays($dias)->toDateString(),
            'origen_fecha_prometida' => 'manual',
        ]);
    }
}