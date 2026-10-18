<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un pedido a laboratorio.
 *
 * La optica no fabrica: encarga. Por eso el pedido tiene estados que son del
 * mundo externo (lo que el laboratorio confirmo) y no solo internos.
 *
 * El control de calidad NO es un estado de este enum, a proposito. Que el
 * laboratorio mande el pedido no significa que este listo para entregar: hay
 * un paso intermedio, en App\Models\ControlCalidad, que es donde se decide si
 * lo que llego sirve. Ver docs/02.
 */
enum EstadoPedido: string
{
    case Borrador = 'borrador';
    case Enviado = 'enviado';
    case Aceptado = 'aceptado';
    case EnProduccion = 'en_produccion';
    case Recibido = 'recibido';
    case Cancelado = 'cancelado';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviado => 'Enviado al laboratorio',
            self::Aceptado => 'Aceptado por el laboratorio',
            self::EnProduccion => 'En produccion',
            self::Recibido => 'Recibido',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * Estados en los que el pedido todavia esta en manos del laboratorio.
     *
     * @return array<int, self>
     */
    public static function enCurso(): array
    {
        return [self::Enviado, self::Aceptado, self::EnProduccion];
    }

    /**
     * Estados en los que no se puede cambiar nada del pedido.
     *
     * @return array<int, self>
     */
    public static function cerrados(): array
    {
        return [self::Recibido, self::Cancelado];
    }

    /**
     * A partir de que estado se puede registrar la recepcion.
     *
     * @return array<int, self>
     */
    public static function quePermitenRecepcion(): array
    {
        return [self::Enviado, self::Aceptado, self::EnProduccion];
    }
}