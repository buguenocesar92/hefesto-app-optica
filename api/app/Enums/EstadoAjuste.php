<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un ajuste posterior a la entrega.
 *
 * Todo lente se ajusta. Un armazon que queda apretado o que se desliza no es
 * un defecto de fabricacion: es una entrega sin terminar. Este enum es la
 * razon por la que el sistema no da por cerrada una entrega al entregarla.
 *
 * `Plazo` NO es la vida de la garantia. Los dias de garantia sugeridos estan
 * en `config('optica.entrega.dias_de_garantia_sugeridos')` y NACEN EN NULL:
 * son una sugerencia para ordenar la cola, no un plazo legal. Ver docs/07.
 */
enum EstadoAjuste: string
{
    case Pendiente = 'pendiente';
    case Realizado = 'realizado';
    case NoRequerido = 'no_requerido';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Realizado => 'Realizado',
            self::NoRequerido => 'No se requeria',
        };
    }

    /**
     * Estados en los que el ajuste sigue abierto.
     *
     * @return array<int, self>
     */
    public static function abiertos(): array
    {
        return [self::Pendiente];
    }
}