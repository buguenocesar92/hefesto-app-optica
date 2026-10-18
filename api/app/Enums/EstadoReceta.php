<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de ciclo de vida de una receta.
 *
 * OJO CON LO QUE NO ESTA ACA: `vigente` y `vencida` NO son estados guardados.
 *
 * Una receta esta vigente o vencida segun su `fecha_vencimiento` y la fecha
 * de hoy. Guardar eso como estado seria duplicar un dato que se puede calcular
 * y que ademas se desactualiza solo: dejarias de ver como vencida una receta
 * que venció mientras el sistema estaba apagado.
 *
 * `estado` es para lo que la persona DECIDE: si la receta se emite, se anula
 * o queda reemplazada por otra posterior. El vencimiento se calcula. Ver
 * App\Services\Recetas\CalculadoraDeVencimiento.
 */
enum EstadoReceta: string
{
    case Emitida = 'emitida';
    case Anulada = 'anulada';
    case Reemplazada = 'reemplazada';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Emitida => 'Emitida',
            self::Anulada => 'Anulada',
            self::Reemplazada => 'Reemplazada',
        };
    }

    /**
     * Una receta en estos estados ya no se usa para despachar lentes.
     *
     * @return array<int, self>
     */
    public static function queNoAutorizanPedido(): array
    {
        return [self::Anulada, self::Reemplazada];
    }
}