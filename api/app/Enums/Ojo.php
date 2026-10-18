<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ojo al que pertenece una graduacion.
 *
 * Se guardan como cadenas y no como numeros porque una receta se lee, se
 * imprime y se manda al laboratorio: un `0` y un `1` obliga a recordar cual
 * era cual, y ese error se paga con un lente montado al reves.
 */
enum Ojo: string
{
    case Derecho = 'derecho';
    case Izquierdo = 'izquierdo';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Derecho => 'Derecho (OD)',
            self::Izquierdo => 'Izquierdo (OI)',
        };
    }

    /**
     * Abreviatura usada en las recetas impresas.
     */
    public function abreviatura(): string
    {
        return match ($this) {
            self::Derecho => 'OD',
            self::Izquierdo => 'OI',
        };
    }

    /**
     * El otro ojo.
     *
     * Se usa al cargar una receta: casi siempre los dos ojos llevan valores
     * parecidos, y esa simetria se propone sola para que la persona la
     * confirme en vez de escribirla dos veces.
     */
    public function contrario(): self
    {
        return match ($this) {
            self::Derecho => self::Izquierdo,
            self::Izquierdo => self::Derecho,
        };
    }

    /**
     * Los dos ojos, en el orden en que se muestran.
     *
     * @return array<int, self>
     */
    public static function ambos(): array
    {
        return [self::Derecho, self::Izquierdo];
    }
}