<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Meridiano de la graduacion.
 *
 * Una graduacion tiene dos meridianos: el de lejos y el de cerca. Cada uno
 * lleva su propia esfera, su propio cilindro y su propio eje.
 *
 * POR QUE SON DOS FILAS Y NO UNA
 * -------------------------------
 * El error tentador es guardar esfera, cilindro y eje en columnas con nombre
 * `esfera_lejes`, `cilindro_lejes`, `eje_lejes`, `esfera_cerca`, y asi. Se ve
 * mas comodo, pero tiene dos costos: obliga a repetir el nombre del meridiano
 * en cada columna, y cuando aparezca un tercer meridiano habria que agregar
 * tres columnas mas y otra migracion.
 *
 * Con una fila por ojo y por meridiano, agregar un meridiano es agregar un
 * valor a este enum. Ver docs/02.
 */
enum Meridiano: string
{
    case Lejos = 'lejos';
    case Cerca = 'cerca';

    /**
     * Etiqueta para mostrar en la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Lejos => 'Vision de lejos',
            self::Cerca => 'Vision de cerca',
        };
    }

    /**
     * Abreviatura usada en las recetas impresas.
     */
    public function abreviatura(): string
    {
        return match ($this) {
            self::Lejos => 'Lejos',
            self::Cerca => 'Cerca',
        };
    }

    /**
     * Es el meridiano donde tiene sentido cargar la adicion.
     *
     * La adicion es la diferencia entre el meridiano de cerca y el de lejos: es
     * lo que se agrega para ver de cerca. Si se cargara en la fila de lejos,
     * el significado seria ambiguo, asi que la base de datos lo impide con
     * una restriccion. Ver la migracion de `graduaciones`.
     */
    public function admiteAdicion(): bool
    {
        return $this === self::Cerca;
    }

    /**
     * Los dos meridianos, en el orden en que se muestran.
     *
     * @return array<int, self>
     */
    public static function todos(): array
    {
        return [self::Lejes, self::Cerca];
    }
}