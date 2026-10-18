<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RolOptica;
use App\Models\Armazon;
use App\Models\Usuario;

/**
 * Quién puede ver y editar el catalogo de armazones.
 *
 * El catalogo lo ve todo el mundo, porque es un dato comercial: es lo que se
 * le muestra al cliente en el mostrador. Lo que cambia es editarlo, que es
 * tarea de administracion.
 */
class ArmazonPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(Usuario $usuario, Armazon $armazon): bool
    {
        return $usuario->activo;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->esAdministrador($usuario);
    }

    public function update(Usuario $usuario, Armazon $armazon): bool
    {
        return $this->esAdministrador($usuario);
    }

    /**
     * No se borra un armazon que ya se pidio a un laboratorio.
     *
     * La base de datos lo impide ademas, con `restrictOnDelete` en
     * `pedidos_laboratorio.armazon_id`. Esta policy devuelve false siempre: lo
     * que se hace es desactivar.
     */
    public function delete(Usuario $usuario, Armazon $armazon): bool
    {
        return false;
    }

    private function esAdministrador(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->rol === RolOptica::Administrador;
    }
}