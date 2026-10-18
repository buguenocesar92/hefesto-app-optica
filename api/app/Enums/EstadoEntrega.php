<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una entrega de lentes al cliente.
 *
 * El paso de `registrada` a `entregada` es el que abre la cola de ajustes: si
 * el lente no calza, el cliente vuelve. Ver docs/02.
 */
enum EstadoEntrega: string
{
    case Registrada = 'registrada';
    case Entregada = 'entregada';
    case Devuelta = 'devuelta';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Registrada => 'Registrada, pendiente de entrega',
            self::Entregada => 'Entregada',
            self::Devuelta => 'Devuelta por el cliente',
        };
    }

    /**
     * Solo una entrega ya hecha admite ajustes posteriores.
     *
     * @return array<int, self>
     */
    public static function queAdmitenAjuste(): array
    {
        return [self::Entregada];
    }
}