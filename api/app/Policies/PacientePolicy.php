<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\Paciente;
use App\Models\Usuario;

/**
 * Quién puede ver y editar la ficha de un paciente.
 *
 * La ficha es dato de contacto y facturacion, no dato de salud. La ven las
 * tres personas que trabajan en la optica, incluido mostrador y
 * administracion.
 *
 * Lo que NO se abre con esta policy es la graduacion: eso lo decide
 * App\Policies\RecetaPolicy, y es mas restrictivo a proposito.
 */
class PacientePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(Usuario $usuario, Paciente $paciente): bool
    {
        return $usuario->activo;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function update(Usuario $usuario, Paciente $paciente): bool
    {
        return $usuario->activo;
    }

    /**
     * No se borra un paciente: se desactiva.
     *
     * El motivo no es purismo. Un paciente con recetas y pedidos es informacion
     * de salud y un documento comercial. Borrar la fila harija con los pedidos
     * que lo referencian, y la unica forma de "borrarlo de verdad" seria
     * desactivo, que es exactamente lo que hace la columna `activo`.
     */
    public function delete(Usuario $usuario, Paciente $paciente): bool
    {
        return false;
    }

    /**
     * Quien puede desactivar en vez de borrar.
     */
    public function desactivar(Usuario $usuario): bool
    {
        return $usuario->rol !== null;
    }

    /**
     * La graduacion del paciente solo la ve el optometrista.
     */
    public function verGraduaciones(Usuario $usuario): bool
    {
        return in_array($usuario->rol, RolOptica::conAccesoAGraduaciones(), true);
    }
}