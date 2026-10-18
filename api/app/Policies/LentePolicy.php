<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\Lente;
use App\Models\Usuario;

/**
 * Quién puede ver y editar el catalogo de lentes.
 *
 * Igual que el de armazones: el catalogo se ve en el mostrador, se edita en
 * administracion.
 */
class LentePolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(Usuario $usuario, Lente $lente): bool
    {
        return $usuario->activo;
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->rol === RolOptica::Administrador;
    }

    public function update(Usuario $usuario, Lente $lente): bool
    {
        return $usuario->activo && $usuario->rol === RolOptica::Administrador;
    }

    /**
     * No se borra un lente que ya se pidio a un laboratorio.
     *
     * `pedido_items.lente_id` usa `nullOnDelete`, asi que borrarlo no rompe el
     * pedido, pero dejaria un renglon sin lente. Es mejor desactivar.
     */
    public function delete(Usuario $usuario, Lente $lente): bool
    {
        return false;
    }
}