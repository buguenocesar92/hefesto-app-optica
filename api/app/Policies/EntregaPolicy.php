<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\Entrega;
use App\Models\Usuario;

/**
 * Quién puede registrar entregas.
 *
 * MOSTRADOR TAMBIEN
 * -----------------
 * A diferencia de la receta, aca el mostrador si opera: la entrega ocurre en
 * el mostrador, con el cliente presente. Es el optometrista quien ve la
 * graduacion, no quien entrega los lentes.
 */
class EntregaPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(Usuario $usuario, Entrega $entrega): bool
    {
        return $usuario->activo;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->opera($usuario);
    }

    public function update(Usuario $usuario, Entrega $entrega): bool
    {
        return $this->opera($usuario);
    }

    /**
     * Entregar de verdad. Exige que el pedido haya pasado el control de
     * calidad sin salir `no_conforme`.
     *
     * Esta es la comprobacion que evita el reclamo: si el laboratorio mando
     * la graduacion equivocada y nadie reviso, el cliente se la lleva.
     */
    public function entregar(Usuario $usuario, Entrega $entrega): bool
    {
        if (! $this->opera($usuario)) {
            return false;
        }

        return $entrega->pedido?->puedeEntregarse() ?? false;
    }

    /**
     * Una entrega no se borra: se devuelve.
     */
    public function delete(Usuario $usuario, Entrega $entrega): bool
    {
        return false;
    }

    private function opera(Usuario $usuario): bool
    {
        return $usuario->activo
            && in_array($usuario->rol, [RolOptica::Mostrador, RolOptica::Optometrista], true);
    }
}