<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resultado del control de calidad de un pedido al recibirlo.
 *
 * El punto de este enum es el tercero. Cuando la graduacion que llega del
 * laboratorio no es la que se pidio, hay tres salidas y son distintas:
 *
 *  - `conforme`: lo que llego sirve.
 *  - `con_diferencias`: llego casi, hay diferencias y la persona las acepta.
 *  - `no_conforme`: no sirve, hay que devolverlo o rehacerlo.
 *
 * Guardar un solo campo booleano `ok` perderia la diferencia entre las dos
 * ultimas, que es justo la que sirve para pedirle al laboratorio una
 * correccion en vez de entregarlo. Ver docs/02.
 */
enum ResultadoControlCalidad: string
{
    case Conforme = 'conforme';
    case ConDiferencias = 'con_diferencias';
    case NoConforme = 'no_conforme';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Conforme => 'Conforme',
            self::ConDiferencias => 'Con diferencias aceptadas',
            self::NoConforme => 'No conforme',
        };
    }

    /**
     * Si el pedido puede entregarse al cliente con este resultado.
     *
     * Un `no_conforme` bloquea la entrega: es el motivo por el que existe el
     * control de calidad. Detenerse aca es lo que evita el reclamo.
     */
    public function permiteEntregar(): bool
    {
        return $this !== self::NoConforme;
    }
}