<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\ControlCalidad;
use App\Models\Usuario;

/**
 * Quién puede registrar y ver el control de calidad de un pedido.
 *
 * SOLO EL OPTOMETRISTA
 * -------------------
 * El control de calidad compara graduaciones. Quien lo hace tiene que saber
 * leerlas y decidir si una diferencia es aceptable. Mostrador y
 * administracion quedan fuera.
 *
 * El veredicto NO lo pone el sistema: la tolerancia con que se acepta una
 * diferencia nace en `null` porque es una decision clinica. Ver docs/06.
 */
class ControlCalidadPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $this->esOptometrista($usuario);
    }

    public function view(Usuario $usuario, ControlCalidad $control): bool
    {
        return $this->esOptometrista($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esOptometrista($usuario);
    }

    /**
     * El veredicto se puede corregir mientras el pedido no se entregue.
     *
     * Corregir un control ya entregado permitiria reescribir despues de que
     * el cliente ya recibio los lentes.
     */
    public function update(Usuario $usuario, ControlCalidad $control): bool
    {
        return $this->esOptometrista($usuario)
            && ($control->pedido?->entrega === null);
    }

    public function delete(Usuario $usuario, ControlCalidad $control): bool
    {
        return false;
    }

    private function esOptometrista(Usuario $usuario): bool
    {
        return $usuario->activo
            && in_array($usuario->rol, RolOptica::conAccesoAGraduaciones(), true);
    }
}