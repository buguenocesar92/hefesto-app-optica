<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoPedido;
use App\Enums\RolOptica;
use App\Models\PedidoLaboratorio;
use App\Models\Usuario;

/**
 * Quién puede ver y mover un pedido a laboratorio.
 *
 * Mostrador y optometrista mueven pedidos: es el trabajo de ambos. El
 * administrador no los mueve, pero si los VE, porque tiene que poder ver el
 * catalogo en uso y el estado de los encargos.
 */
class PedidoLaboratorioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(Usuario $usuario, PedidoLaboratorio $pedido): bool
    {
        return $usuario->activo;
    }

    public function create(Usuario $usuario): bool
    {
        return $this->opera($usuario);
    }

    public function update(Usuario $usuario, PedidoLaboratorio $pedido): bool
    {
        return $this->opera($usuario)
            && ! in_array($pedido->estado, EstadoPedido::cerrados(), true);
    }

    /**
     * Verificar si un armazon y un lente encajan.
     *
     * No muta nada: es una consulta. Por eso la puede hacer tambien
     * administracion, que necesita medir antes de decidir que cargar en el
     * catalogo.
     */
    public function verificarCompatibilidad(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * Los pedidos no se borran.
     *
     * Son un documento comercial y ademas referencian recetas y pacientes.
     * Se cancelan.
     */
    public function delete(Usuario $usuario, PedidoLaboratorio $pedido): bool
    {
        return false;
    }

    /**
     * Cancelar un pedido que todavia no llego.
     */
    public function cancelar(Usuario $usuario, PedidoLaboratorio $pedido): bool
    {
        return $this->opera($usuario)
            && ! in_array($pedido->estado, EstadoPedido::cerrados(), true);
    }

    private function opera(Usuario $usuario): bool
    {
        return $usuario->activo
            && in_array($usuario->rol, [RolOptica::Mostrador, RolOptica::Optometrista], true);
    }
}