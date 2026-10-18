<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\Graduacion;
use App\Models\Usuario;

/**
 * Quién puede leer y escribir una fila de graduacion.
 *
 * Mismas reglas que App\Policies\RecetaPolicy, y a proposito: la graduacion es
 * la parte sensible de la receta. Un endpoint que abre graduaciones sin pasar
 * por esta policy seria una fuga, asi que el middleware `receta.auditada` la
 * revisa otra vez antes de que la peticion llegue al controlador.
 */
class GraduacionPolicy
{
    public function view(Usuario $usuario, Graduacion $graduacion): bool
    {
        return $this->esOptometrista($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esOptometrista($usuario);
    }

    /**
     * Una graduacion emitida no se corrige en el lugar.
     *
     * El motivo es el mismo por el que una receta no se edita: la graduacion
     * forma parte de un documento. Para cambiarla se emite otra receta y se
     * marca la anterior como reemplazada.
     */
    public function update(Usuario $usuario, Graduacion $graduacion): bool
    {
        return $this->esOptometrista($usuario);
    }

    public function delete(Usuario $usuario, Graduacion $graduacion): bool
    {
        return false;
    }

    private function esOptometrista(Usuario $usuario): bool
    {
        return $usuario->activo
            && in_array($usuario->rol, RolOptica::conAccesoAGraduaciones(), true);
    }
}