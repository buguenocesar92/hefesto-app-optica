<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Veredicto de la verificacion de compatibilidad entre un armazon y un lente.
 *
 * EL TERCER VALOR ES EL IMPORTANTE
 * --------------------------------
 * Lo normal seria un si o un no. Aqui hay tres respuestas porque hay una
 * tercera situacion real y frecuente: NO SE SABE.
 *
 * El veredicto `no_verificable` existe cuando falta un dato: que de diametro
 * se mecaniza ese lente, o cual es el margen efectivo que necesita. Devolver
 * "compatible" sin ese dato seria adivinar, y adivinar aca significa montar
 * una lente que no entra en el armazon.
 *
 * Por eso `no_verificable` NO es un caso borde: es la respuesta por omision
 * mientras la optica no haya cargado su catalogo completo. Ver docs/06.
 */
enum VeredictoCompatibilidad: string
{
    case Compatible = 'compatible';
    case Incompatible = 'incompatible';
    case NoVerificable = 'no_verificable';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Compatible => 'Compatible',
            self::Incompatible => 'No compatible',
            self::NoVerificable => 'No verificable todavia',
        };
    }

    /**
     * Si el veredicto permite despachar el pedido sin mas revisacion.
     *
     * `no_verificable` NO permite: la persona tiene que mirar el desglose y
     * decidir. Es la unica forma de que el sistema no tape el dato que falta.
     */
    public function permiteDespachar(): bool
    {
        return $this === self::Compatible;
    }
}