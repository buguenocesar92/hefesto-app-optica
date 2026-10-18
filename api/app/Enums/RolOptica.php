<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de la persona dentro de la optica.
 *
 * El rol es la SEMILLA del permiso, no el permiso. Lo que decide si alguien
 * puede leer una graduacion es App\Services\Permisos\CapacidadesDeUsuario,
 * que ademas mira el rol con reglas propias del estudio.
 *
 * La diferencia con un sistema de inventario esta en `mostrador` y
 * `administrador`: ninguno de los dos lee graduaciones. La graduacion es
 * informacion de salud, y el cargo de una persona no la vuelve menos sensible.
 * Ver docs/07.
 */
enum RolOptica: string
{
    case Mostrador = 'mostrador';
    case Optometrista = 'optometrista';
    case Administrador = 'administrador';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Mostrador => 'Mostrador',
            self::Optometrista => 'Optometrista',
            self::Administrador => 'Administracion',
        };
    }

    /**
     * El nombre del equipo en Authentik que otorga este rol.
     *
     * Sirve para la guia de alta de cuentas (docs/04). La comparacion es
     * insensible a mayusculas porque Authentik entrega los equipos con el
     * nombre tal como se escribio en su consola.
     */
    public function equipoAuthentik(): string
    {
        return match ($this) {
            self::Mostrador => 'Mostrador de OpticaHefesto',
            self::Optometrista => 'Optometristas de OpticaHefesto',
            self::Administrador => 'Administradores de OpticaHefesto',
        };
    }

    /**
     * Orden de precedencia al leer los equipos de Authentik.
     *
     * Si una persona esta en varios equipos gana el primero de esta lista, asi
     * que el mas permisivo va primero: Administracion puede administeringar el
     * catalogo, pero sigue sin leer graduaciones.
     *
     * @return array<int, self>
     */
    public static function porPrecedencia(): array
    {
        return [self::Administrador, self::Optometrista, self::Mostrador];
    }

    /**
     * Los roles que pueden leer y escribir graduaciones.
     *
     * @return array<int, self>
     */
    public static function conAccesoAGraduaciones(): array
    {
        return [self::Optometrista];
    }

    /**
     * Los roles que pueden emitir una receta.
     *
     * @return array<int, self>
     */
    public static function queEmitenRecetas(): array
    {
        return [self::Optometrista];
    }
}